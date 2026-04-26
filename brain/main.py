import os
import requests
import asyncio
import logging
import json
from fastapi import FastAPI, Request, Response
from telegram import Bot, Update, InlineKeyboardButton, InlineKeyboardMarkup
from telegram.ext import Application, CommandHandler, MessageHandler, filters, ContextTypes, CallbackQueryHandler
from dotenv import load_dotenv

# Enable logging
logging.basicConfig(format='%(asctime)s - %(name)s - %(levelname)s - %(message)s', level=logging.INFO)
logger = logging.getLogger(__name__)
# VERSION: 1.0.9 - TRIPLE FORCE RESET

load_dotenv()

# Config
TELEGRAM_TOKEN = os.getenv("TELEGRAM_TOKEN")
WP_URL = os.getenv("WP_URL")
WP_USERNAME = os.getenv("WP_USERNAME")
WP_APP_PASSWORD = os.getenv("WP_APP_PASSWORD")
CLAUDE_MODEL = os.getenv("CLAUDE_MODEL", "claude-opus-4-1-20250805")  # Default to latest Opus 4.1

app = FastAPI(title="WPMaster AI Brain")

# Initialize Telegram Application (Global)
application = Application.builder().token(TELEGRAM_TOKEN).build()

async def call_wp_tool(tool: str, params: dict):
    url = f"{WP_URL}/tools"
    auth = (WP_USERNAME, WP_APP_PASSWORD)
    payload = {"tool": tool, "params": params}
    logger.info(f"Calling WP Gateway Tool: {tool}")
    headers = {
        "ngrok-skip-browser-warning": "true",
        "User-Agent": "WPMasterAI-Brain"
    }
    try:
        response = requests.post(url, json=payload, auth=auth, headers=headers, timeout=30)
        logger.info(f"WP Response Code: {response.status_code}")
        
        if response.status_code == 401:
            return {"error": "401 Unauthorized (Check your Username and App Password)"}
        
        if response.status_code != 200:
            return {"error": f"HTTP {response.status_code}: {response.text[:100]}"}
            
        return response.json()
    except Exception as e:
        logger.error(f"WP Connection Error: {e}")
        return {"error": str(e)}

async def start_command(update: Update, context: ContextTypes.DEFAULT_TYPE):
    logger.info("Start command received")
    await update.message.reply_text("👋 WPMaster AI is connected and listening!")

# --- Agentic Tools ---
async def wp_tool_executor(tool_name: str, params: dict):
    """Bridge between OpenAI Tool Calls and WP Gateway."""
    logger.info(f"Executing Tool: {tool_name} with params: {params}")
    return await call_wp_tool(tool_name, params)

# Tool Definitions for OpenAI
TOOLS_SCHEMA = [
    {
        "type": "function",
        "function": {
            "name": "manage_posts",
            "description": "Create, edit, or list WordPress posts.",
            "parameters": {
                "type": "object",
                "properties": {
                    "action": {"type": "string", "enum": ["create", "edit", "list", "delete"]},
                    "title": {"type": "string"},
                    "content": {"type": "string"},
                    "post_id": {"type": "integer"}
                },
                "required": ["action"]
            }
        }
    },
    {
        "type": "function",
        "function": {
            "name": "manage_pages",
            "description": "Create or edit WordPress pages.",
            "parameters": {
                "type": "object",
                "properties": {
                    "action": {"type": "string", "enum": ["create", "edit", "list"]},
                    "title": {"type": "string"},
                    "content": {"type": "string"}
                },
                "required": ["action"]
            }
        }
    },
    {
        "type": "function",
        "function": {
            "name": "site_info",
            "description": "Get technical info about the WordPress site (version, plugins, status).",
            "parameters": {
                "type": "object",
                "properties": {
                    "detail": {"type": "string", "enum": ["basic", "full", "health"]}
                }
            }
        }
    }
]

async def run_agent_loop(user_msg: str, chat_history: list):
    """The core thinking loop of the WPMaster AI Agent (Supports OpenAI & Anthropic)."""
    openai_key = os.getenv("OPENAI_API_KEY")
    anthropic_key = os.getenv("ANTHROPIC_API_KEY")
    
    if not openai_key and not anthropic_key:
        return "❌ Missing AI API Key! Please add OPENAI_API_KEY or ANTHROPIC_API_KEY to Render."

    import asyncio
    loop = asyncio.get_event_loop()

    # Define the universal messages structure
    messages = [
        {"role": "system", "content": "You are WPMaster AI, a powerful WordPress administrator assistant. You have direct access to the user's WordPress site via tools."},
        *chat_history,
        {"role": "user", "content": user_msg}
    ]

    # --- ANTHROPIC CLAUDE PATH ---
    if anthropic_key:
        anthropic_key = anthropic_key.strip() # Remove any hidden spaces
        logger.info(f"Using Anthropic Claude Opus Provider (Key Length: {len(anthropic_key)})")
        url = "https://api.anthropic.com/v1/messages"
        headers = {
            "x-api-key": anthropic_key,
            "anthropic-version": "2023-06-01",
            "content-type": "application/json"
        }
        
        # Convert TOOLS_SCHEMA to Anthropic's strict format
        anthropic_tools = []
        for t in TOOLS_SCHEMA:
            anthropic_tools.append({
                "name": t["function"]["name"],
                "description": t["function"]["description"],
                "input_schema": t["function"]["parameters"]
            })

        system_str = "You are WPMaster AI, a powerful WordPress administrator assistant. You have direct access to the user's WordPress site via tools. Always be professional and helpful."
        clean_messages = [m for m in messages if m["role"] != "system"]

        def call_claude(msgs, tools=None, model_name=None):
            if model_name is None:
                model_name = CLAUDE_MODEL
            logger.info(f"Calling Claude with model: {model_name}")
            payload = {
                "model": model_name,
                "max_tokens": 4096,
                "system": system_str,
                "messages": msgs
            }
            if tools:
                payload["tools"] = tools
            
            resp = requests.post(url, json=payload, headers=headers, timeout=60)
            logger.info(f"Claude API Response Status: {resp.status_code}")
            if resp.status_code != 200:
                logger.error(f"Claude API Error Response: {resp.text}")
                return {"error": {"message": f"HTTP {resp.status_code}: {resp.text}"}}
            return resp.json()

        try:
            # --- MULTI-TURN THINKING LOOP ---
            current_msgs = clean_messages
            for _ in range(10): # Allow up to 10 steps to finish a task
                response = await loop.run_in_executor(None, call_claude, current_msgs, anthropic_tools)
                
                if "error" in response:
                    error_data = response["error"]
                    return f"❌ Claude Error: {error_data.get('message')}"

                # Handle Text & Tool Use
                assistant_content = response["content"]
                current_msgs.append({"role": "assistant", "content": assistant_content})

                if response.get("stop_reason") != "tool_use":
                    # AI is finished thinking, return final text
                    return assistant_content[0]["text"]

                # Process all tool calls in this turn
                for content in assistant_content:
                    if content["type"] == "tool_use":
                        tool_name = content["name"]
                        tool_args = content["input"]
                        tool_use_id = content["id"]
                        
                        logger.info(f"Agent wants to use: {tool_name}")
                        result = await wp_tool_executor(tool_name, tool_args)
                        
                        current_msgs.append({
                            "role": "user",
                            "content": [
                                {
                                    "type": "tool_result",
                                    "tool_use_id": tool_use_id,
                                    "content": json.dumps(result)
                                }
                            ]
                        })
                # Loop continues to next turn with tool results...

            return "⚠️ Agent exceeded maximum thinking steps (10). Please be more specific."

        except Exception as e:
            return f"❌ Claude Agent Error: {str(e)}"

    # --- OPENAI PATH ---
    url = "https://api.openai.com/v1/chat/completions"
    headers = {"Authorization": f"Bearer {openai_key}"}
    
    try:
        def call_openai(msgs, tools=None):
            payload = {"model": "gpt-4o", "messages": msgs}
            if tools:
                payload["tools"] = tools
                payload["tool_choice"] = "auto"
            return requests.post(url, json=payload, headers=headers, timeout=60).json()

        response = await loop.run_in_executor(None, call_openai, messages, TOOLS_SCHEMA)
        
        if "error" in response:
            return f"❌ OpenAI Error: {response['error']['message']}"
            
        if "choices" not in response:
            return f"❌ OpenAI Unexpected Response: {response}"

        message = response['choices'][0]['message']
        
        # Step 2: Handle Tool Calls
        if message.get("tool_calls"):
            for tool_call in message["tool_calls"]:
                tool_name = tool_call["function"]["name"]
                tool_args = json.loads(tool_call["function"]["arguments"])
                
                # Execute tool on WP
                result = await wp_tool_executor(tool_name, tool_args)
                
                # Add tool result to conversation
                messages.append(message)
                messages.append({
                    "role": "tool",
                    "tool_call_id": tool_call["id"],
                    "content": json.dumps(result)
                })

            # Step 3: Final response from AI
            final_response = await loop.run_in_executor(None, call_openai, messages)
            
            if "error" in final_response:
                return f"❌ OpenAI Final Error: {final_response['error']['message']}"
                
            if "choices" not in final_response:
                return f"❌ OpenAI Final Unexpected Response: {final_response}"
                
            return final_response['choices'][0]['message']['content']
        
        return message["content"]

    except Exception as e:
        logger.error(f"Agent Loop Error: {e}")
        return f"❌ Agent Error: {str(e)}"

async def handle_message(update: Update, context: ContextTypes.DEFAULT_TYPE):
    user_msg = update.message.text
    status_msg = await update.message.reply_text("🤔 WPMaster AI is thinking...")
    
    # Run the Agentic Loop
    # (Simplified history for now — can be expanded to a database later)
    ai_reply = await run_agent_loop(user_msg, [])
    
    # Remove Markdown parse_mode to prevent 'Can't parse entities' crashes
    await status_msg.edit_text(ai_reply)

async def button_callback(update: Update, context: ContextTypes.DEFAULT_TYPE):
    query = update.callback_query
    await query.answer()
    if query.data.startswith("publish_"):
        post_id = query.data.split("_")[1]
        await call_wp_tool("manage_posts", {"action": "edit", "post_id": int(post_id), "status": "publish"})
        await query.edit_message_text("✅ Published successfully!")

# Setup Handlers
application.add_handler(CommandHandler("start", start_command))
application.add_handler(MessageHandler(filters.TEXT & ~filters.COMMAND, handle_message))
application.add_handler(CallbackQueryHandler(button_callback))

@app.on_event("startup")
async def startup_event():
    await application.initialize()
    await application.start()
    logger.info("Telegram Application Initialized")

@app.on_event("shutdown")
async def shutdown_event():
    await application.stop()
    await application.shutdown()

# Explicitly allow GET and HEAD to satisfy Render's health checks
@app.api_route("/", methods=["GET", "HEAD"])
async def root():
    return {"message": "WPMaster AI Brain is Online"}

@app.post("/webhook/telegram")
async def telegram_webhook(request: Request):
    try:
        data = await request.json()
        logger.info("Incoming Webhook Data")
        update = Update.de_json(data, application.bot)
        await application.process_update(update)
        return Response(status_code=200)
    except Exception as e:
        logger.error(f"Webhook Error: {e}")
        return Response(status_code=500)

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=int(os.getenv("PORT", 8000)))
