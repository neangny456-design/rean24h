import json
import urllib.request
import urllib.parse
import time
import sys

# English24h Telegram Bot Server (Zero-Dependency)
# Replace 'YOUR_WEBAPP_URL' with your HTTPS url (e.g. ngrok link or your hosting domain)
# Example: "https://a1b2-34-56-78-90.ngrok-free.app/english24h/"
WEBAPP_URL = "https://4f22127f254e2393-36-37-239-48.serveousercontent.com/english24h/"
BOT_TOKEN = "8993389047:AAG8FpaYAZMHMF3hOV2BpLQKM_0venimdBI"

API_URL = f"https://api.telegram.org/bot{BOT_TOKEN}/"

def send_api_request(method, payload):
    url = API_URL + method
    headers = {"Content-Type": "application/json"}
    data = json.dumps(payload).encode("utf-8")
    
    req = urllib.request.Request(url, data=data, headers=headers, method="POST")
    try:
        with urllib.request.urlopen(req) as response:
            return json.loads(response.read().decode("utf-8"))
    except Exception as e:
        print(f"Error calling {method}: {e}", file=sys.stderr)
        return None

def send_welcome_message(chat_id, first_name):
    # If the URL is still the default placeholder, warn the developer in console but still send a button
    webapp_link = WEBAPP_URL
    if webapp_link == "YOUR_WEBAPP_URL":
        print("[WARNING] Please update the WEBAPP_URL variable in bot.py to your HTTPS ngrok/hosting link!")
        # Fallback placeholder that won't work in Telegram, but highlights what to do
        webapp_link = "https://example.com"
        
    text = (
        f"Hello {first_name}! Welcome to *English24h*! 🎓\n\n"
        "This bot helps you practice English Grammatical Tenses with interactive multiple-choice questions (QCM).\n\n"
        "Click the button below to launch the Mini App and start your tests!"
    )
    
    reply_markup = {
        "keyboard": [
            [
                {
                    "text": "🎓 Open English24h Dashboard",
                    "web_app": {
                        "url": webapp_link
                    }
                }
            ]
        ],
        "resize_keyboard": True,
        "one_time_keyboard": False
    }
    
    payload = {
        "chat_id": chat_id,
        "text": text,
        "parse_mode": "Markdown",
        "reply_markup": reply_markup
    }
    
    send_api_request("sendMessage", payload)

def main():
    print("=" * 60)
    print("           ENGLISH24H TELEGRAM BOT ROUTER            ")
    print("=" * 60)
    print("Bot is starting up...")
    
    if WEBAPP_URL == "YOUR_WEBAPP_URL":
        print("\n[IMPORTANT NOTICE]")
        print("Telegram Mini Apps require an HTTPS URL to load.")
        print("If running locally via XAMPP, use ngrok to tunnel your local server:")
        print("  1. Run ngrok: 'ngrok http 80'")
        print("  2. Copy the HTTPS forwarding URL (e.g., https://abc.ngrok-free.app)")
        print("  3. Update WEBAPP_URL inside this script to: 'https://abc.ngrok-free.app/english24h/'")
        print("  4. Restart this bot script.\n")
    else:
        print(f"Mini App configured to open: {WEBAPP_URL}")
        
    # Get bot info to verify token
    info = send_api_request("getMe", {})
    if not info or not info.get("ok"):
        print("[ERROR] Failed to connect to Telegram API. Verify your BOT_TOKEN and internet connection.")
        return
        
    bot_user = info["result"]
    print(f"Successfully logged in as: @{bot_user['username']} ({bot_user['first_name']})")
    print("Waiting for messages... (Press Ctrl+C to stop)")
    
    offset = 0
    while True:
        try:
            payload = {"offset": offset, "timeout": 30}
            updates = send_api_request("getUpdates", payload)
            
            if updates and updates.get("ok") and updates.get("result"):
                for update in updates["result"]:
                    # Update offset to acknowledge processed messages
                    offset = update["update_id"] + 1
                    
                    if "message" in update:
                        msg = update["message"]
                        chat_id = msg["chat"]["id"]
                        text = msg.get("text", "")
                        first_name = msg["from"].get("first_name", "Student")
                        
                        if text.startswith("/start"):
                            print(f"Received /start command from {first_name} (chat_id: {chat_id})")
                            send_welcome_message(chat_id, first_name)
                            
            time.sleep(0.5)
        except KeyboardInterrupt:
            print("\nShutting down bot. Goodbye!")
            break
        except Exception as e:
            print(f"Error in polling loop: {e}", file=sys.stderr)
            time.sleep(5)

if __name__ == "__main__":
    main()
