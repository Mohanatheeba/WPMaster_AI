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

async def handle_message(update: Update, context: ContextTypes.DEFAULT_TYPE):
    user_msg = update.message.text
    logger.info(f"Message received: {user_msg}")
    
    status_msg = await update.message.reply_text("🤖 Working on it...")
    
    # 2. WP Gateway call
    wp_result = await call_wp_tool("manage_posts", {
        "action": "create",
        "title": "Draft: " + (user_msg[:20] + "..."),
        "content": f"<p>Request: {user_msg}</p>"
    })
    
    logger.info(f"WP Result received: {wp_result}")
    
    if not wp_result or "error" in wp_result:
        error_detail = wp_result.get("error", "Unknown Error") if wp_result else "No response from WP"
        await status_msg.edit_text(f"❌ WP Error: {error_detail}\n\nHint: Check your WP_USERNAME and WP_APP_PASSWORD in Render!")
        return

    if "post_id" not in wp_result:
        await status_msg.edit_text(f"❌ WP Response Missing ID: {wp_result}")
        return

    # 3. Send success message with public preview link
    public_url = wp_result.get("url", "").replace("http://wpmastertest.local", os.getenv("WP_URL").replace("/wp-json/clawwp/v1", ""))
    
    keyboard = [[InlineKeyboardButton("🚀 Publish", callback_data=f"publish_{post_id}")]]
    
    await status_msg.delete()
    await update.message.reply_text(
        f"✅ **Draft Created!** (ID: {post_id})\n🔗 **Preview:** {public_url}",
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
