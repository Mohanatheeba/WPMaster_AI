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

load_dotenv()

# Config
TELEGRAM_TOKEN = os.getenv("TELEGRAM_TOKEN")
WP_URL = os.getenv("WP_URL")
WP_USERNAME = os.getenv("WP_USERNAME")
WP_APP_PASSWORD = os.getenv("WP_APP_PASSWORD")

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
        logger.info("Using Anthropic Claude Provider")
        url = "https://api.anthropic.com/v1/messages"
        headers = {
            "x-api-key": anthropic_key,
            "anthropic-version": "2023-06-01",
            "content-type": "application/json"
        }
        
        # Convert TOOLS_SCHEMA to Anthropic format (they use 'input_schema' instead of 'parameters')
        anthropic_tools = []
        for t in TOOLS_SCHEMA:
            anthropic_tools.append({
                "name": t["function"]["name"],
                "description": t["function"]["description"],
                "input_schema": t["function"]["parameters"]
            })

        system_prompt = "You are WPMaster AI, a powerful WordPress administrator assistant. You have direct access to the user's WordPress site via tools."
        
        def call_claude(msgs, tools=None):
            payload = {
                "model": "claude-3-5-sonnet-20240620",
                "max_tokens": 4096,
                "system": system_prompt,
                "messages": msgs
            }
            if tools:
                payload["tools"] = tools
            return requests.post(url, json=payload, headers=headers, timeout=60).json()

        try:
            response = await loop.run_in_executor(None, call_claude, messages[1:]) # Skip system role for Claude
            
            if "error" in response:
                return f"❌ Claude Error: {response['error']['message']}"

            # Handle Tool Use (Anthropic)
            if response.get("stop_reason") == "tool_use":
                for content in response["content"]:
                    if content["type"] == "tool_use":
                        tool_name = content["name"]
                        tool_args = content["input"]
                        tool_use_id = content["id"]
                        
                        result = await wp_tool_executor(tool_name, tool_args)
                        
                        # Follow up
                        follow_up_msgs = [
                            *messages[1:],
                            {"role": "assistant", "content": response["content"]},
                            {
                                "role": "user",
                                "content": [
                                    {
                                        "type": "tool_result",
                                        "tool_use_id": tool_use_id,
                                        "content": json.dumps(result)
                                    }
                                ]
                            }
                        ]
                        final_resp = await loop.run_in_executor(None, call_claude, follow_up_msgs)
                        return final_resp["content"][0]["text"]
            
            return response["content"][0]["text"]
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
                import json
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
    
    await status_msg.edit_text(ai_reply, parse_mode="Markdown")

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
