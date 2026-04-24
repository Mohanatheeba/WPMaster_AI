import os
import requests
import asyncio
import logging
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
    try:
        response = requests.post(url, json=payload, auth=auth, timeout=15)
        logger.info(f"WP Response: {response.status_code}")
        return response.json()
    except Exception as e:
        logger.error(f"WP Connection Error: {e}")
        return {"error": str(e)}

async def start_command(update: Update, context: ContextTypes.DEFAULT_TYPE):
    logger.info("Start command received")
    await update.message.reply_text("👋 WPMaster AI is connected and listening!")

async def handle_message(update: Update, context: ContextTypes.DEFAULT_TYPE):
    user_msg = update.message.text
    logger.info(f"Message received: {user_msg}")
    
    status_msg = await update.message.reply_text("🤖 Working on it...")
    
    # Simple logic
    wp_result = await call_wp_tool("manage_posts", {
        "action": "create",
        "title": "Draft: " + (user_msg[:20] + "..."),
        "content": f"<p>Request: {user_msg}</p>"
    })
    
    if "error" in wp_result:
        await status_msg.edit_text(f"❌ Error talking to WP: {wp_result['error']}")
        return

    post_id = wp_result["post_id"]
    keyboard = [[InlineKeyboardButton("🚀 Publish", callback_data=f"publish_{post_id}")]]
    
    await status_msg.delete()
    await update.message.reply_text(
        f"✅ **Draft Created!** (ID: {post_id})\nPreview: {wp_result.get('url')}",
        reply_markup=InlineKeyboardMarkup(keyboard),
        parse_mode="Markdown"
    )

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
    # Crucial: Initialize the application on FastAPI startup
    await application.initialize()
    await application.start()
    logger.info("Telegram Application Initialized")

@app.on_event("shutdown")
async def shutdown_event():
    await application.stop()
    await application.shutdown()

@app.get("/")
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
