import os
import requests
import json
import asyncio
from fastapi import FastAPI, Request, Response
from telegram import Bot, Update, InlineKeyboardButton, InlineKeyboardMarkup
from telegram.ext import Application, CommandHandler, MessageHandler, filters, ContextTypes, CallbackQueryHandler
from dotenv import load_dotenv

# Load environment variables
load_dotenv()

# Config
TELEGRAM_TOKEN = os.getenv("TELEGRAM_TOKEN")
WP_URL = os.getenv("WP_URL")
WP_USERNAME = os.getenv("WP_USERNAME")
WP_APP_PASSWORD = os.getenv("WP_APP_PASSWORD")

app = FastAPI(title="WPMaster AI Brain")

# Initialize Telegram Application
application = Application.builder().token(TELEGRAM_TOKEN).build()

# In-memory store for pending approvals
pending_approvals = {}

async def call_wp_tool(tool: str, params: dict):
    url = f"{WP_URL}/tools"
    auth = (WP_USERNAME, WP_APP_PASSWORD)
    payload = {"tool": tool, "params": params}
    try:
        response = requests.post(url, json=payload, auth=auth, timeout=10)
        return response.json()
    except Exception as e:
        return {"error": str(e)}

async def model_router(prompt: str):
    # Simplified AI logic for POC
    return {
        "title": "Staged Content: " + (prompt[:30] + "..." if len(prompt) > 30 else prompt),
        "content": f"<p>This content was generated for your request: <strong>{prompt}</strong></p><p>It is currently saved as a draft for your review.</p>",
    }

async def start_command(update: Update, context: ContextTypes.DEFAULT_TYPE):
    await update.message.reply_text("👋 Hello! I am your WPMaster AI. Send me a request (e.g., 'Create a post about travel') and I will stage it for you.")

async def handle_message(update: Update, context: ContextTypes.DEFAULT_TYPE):
    user_msg = update.message.text
    chat_id = update.effective_chat.id
    
    status_msg = await update.message.reply_text("🤖 Processing request...")
    
    # 1. AI Logic
    ai_content = await model_router(user_msg)
    
    # 2. WP Gateway call
    wp_result = await call_wp_tool("manage_posts", {
        "action": "create",
        "title": ai_content["title"],
        "content": ai_content["content"]
    })
    
    if "error" in wp_result:
        await status_msg.edit_text(f"❌ WP Error: {wp_result['error']}\n\nMake sure your LocalWP Live Link is active!")
        return

    post_id = wp_result["post_id"]
    preview_url = wp_result.get("url")

    # 3. Request Approval
    keyboard = [[
        InlineKeyboardButton("✅ Publish", callback_data=f"publish_{post_id}"),
        InlineKeyboardButton("🗑️ Discard", callback_data=f"discard_{post_id}")
    ]]
    
    await status_msg.delete()
    await update.message.reply_text(
        f"📝 **Draft Created!**\n\n"
        f"Title: {ai_content['title']}\n"
        f"Preview: {preview_url}\n\n"
        f"Shall I publish this?",
        reply_markup=InlineKeyboardMarkup(keyboard),
        parse_mode="Markdown"
    )

async def button_callback(update: Update, context: ContextTypes.DEFAULT_TYPE):
    query = update.callback_query
    await query.answer()
    data = query.data
    
    if data.startswith("publish_"):
        post_id = data.split("_")[1]
        result = await call_wp_tool("manage_posts", {"action": "edit", "post_id": int(post_id), "status": "publish"})
        await query.edit_message_text(text="🚀 **Live!** The post is now published." if "success" in result else f"❌ Error: {result.get('error')}")
    elif data.startswith("discard_"):
        await query.edit_message_text(text="🗑️ Draft discarded.")

# Register Telegram handlers
application.add_handler(CommandHandler("start", start_command))
application.add_handler(MessageHandler(filters.TEXT & ~filters.COMMAND, handle_message))
application.add_handler(CallbackQueryHandler(button_callback))

@app.get("/")
async def root():
    return {"message": "WPMaster AI Brain is Online"}

@app.post("/webhook/telegram")
async def telegram_webhook(request: Request):
    """Processes updates from Telegram."""
    try:
        data = await request.json()
        update = Update.de_json(data, application.bot)
        
        # We need to run the application's processing in the current event loop
        async with application:
            await application.process_update(update)
            
        return Response(status_code=200)
    except Exception as e:
        print(f"Error processing webhook: {e}")
        return Response(status_code=500)

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=int(os.getenv("PORT", 8000)))
