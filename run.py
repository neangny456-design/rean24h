import subprocess
import re
import urllib.request
import json
import time
import os
import sys

BOT_TOKEN = "8993389047:AAG8FpaYAZMHMF3hOV2BpLQKM_0venimdBI"

def set_telegram_menu_button(url):
    api_url = f"https://api.telegram.org/bot{BOT_TOKEN}/setChatMenuButton"
    payload = {
        "menu_button": {
            "type": "web_app",
            "text": "English24h",
            "web_app": {
                "url": url
            }
        }
    }
    
    req = urllib.request.Request(
        api_url, 
        data=json.dumps(payload).encode('utf-8'),
        headers={'Content-Type': 'application/json'},
        method='POST'
    )
    try:
        with urllib.request.urlopen(req) as res:
            result = json.loads(res.read().decode('utf-8'))
            return result.get('ok', False)
    except Exception as e:
        print(f"[Manager] Failed to update Chat Menu Button API: {e}", file=sys.stderr)
        return False

def update_bot_py(url):
    bot_py_path = os.path.join(os.path.dirname(__file__), 'bot.py')
    if not os.path.exists(bot_py_path):
        return
    with open(bot_py_path, 'r', encoding='utf-8') as f:
        content = f.read()
    
    # Replace WEBAPP_URL line
    updated_content = re.sub(
        r'WEBAPP_URL\s*=\s*".*?"',
        f'WEBAPP_URL = "{url}"',
        content
    )
    
    with open(bot_py_path, 'w', encoding='utf-8') as f:
        f.write(updated_content)
    print(f"[Manager] Updated bot.py URL definition to: {url}")

def main():
    print("=" * 60)
    print("      ENGLISH24H BOT & SELF-HEALING TUNNEL MANAGER      ")
    print("=" * 60)
    
    bot_process = None
    
    while True:
        print("[Manager] Starting SSH tunnel to serveo.net...")
        
        # Start SSH tunnel process
        tunnel_process = subprocess.Popen(
            ['ssh', '-o', 'StrictHostKeyChecking=no', '-R', '80:localhost:80', 'serveo.net'],
            stdout=subprocess.PIPE,
            stderr=subprocess.STDOUT,
            text=True,
            bufsize=1
        )
        
        url_detected = False
        
        # Read SSH output line by line in real time
        for line in tunnel_process.stdout:
            print(f"[Tunnel] {line.strip()}")
            
            # Check for death/expiration keywords to trigger auto-reboot
            if any(kw in line.lower() for kw in ["expired", "closed", "failed", "broken", "denied"]):
                print("[Manager] Detected tunnel death or expiration. Restarting...")
                tunnel_process.terminate()
                break
                
            # Find the forwarding url
            match = re.search(r'https://[a-zA-Z0-9.-]+serveousercontent\.com', line)
            if match:
                url = match.group(0) + '/english24h/'
                print(f"\n[Manager] Live HTTPS URL established: {url}")
                
                # 1. Register Chat Menu Button
                ok = set_telegram_menu_button(url)
                if ok:
                    print("[Manager] Telegram Chat Menu Button configured successfully.")
                else:
                    print("[Manager] Warning: Chat Menu Button could not be updated.")
                
                # 2. Update local URL configuration in bot.py
                update_bot_py(url)
                
                # 3. Kill previous bot if running
                if bot_process:
                    print("[Manager] Terminating old bot process...")
                    bot_process.terminate()
                    bot_process.wait()
                
                # 4. Start new bot script
                print("[Manager] Starting bot.py server...")
                bot_process = subprocess.Popen([sys.executable, '-u', 'bot.py'])
                url_detected = True
        
        # If the tunnel exits (e.g. server closes connection)
        tunnel_process.wait()
        print("\n[Manager] Warning: SSH tunnel disconnected.")
        
        # Terminate bot process to prevent invalid link responses
        if bot_process:
            print("[Manager] Stopping bot script...")
            bot_process.terminate()
            bot_process.wait()
            bot_process = None
            
        print("[Manager] Retrying tunnel connection in 5 seconds...\n")
        time.sleep(5)

if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        print("\n[Manager] Shutting down. Goodbye!")
