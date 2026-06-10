#!/usr/bin/env python3
import requests
import os
from datetime import datetime

TOKEN = "8895851530:AAH3iHOY5_8bAfnWz4YTz33u9KuOkvtZKYQ"
CHAT_ID = "5951403620"

def send_message(text):
    url = f"https://api.telegram.org/bot{TOKEN}/sendMessage"
    data = {"chat_id": CHAT_ID, "text": text, "parse_mode": "HTML"}
    requests.post(url, data=data)

def get_server_info():
    now = datetime.now().strftime("%d %B %Y - %H:%M WIB")
    cpu = os.popen("top -bn1 | grep 'Cpu(s)' | awk '{print $2}'").read().strip()
    ram = os.popen("free -h | grep Mem | awk '{print $3\"/\"$2}'").read().strip()
    disk = os.popen("df -h / | tail -1 | awk '{print $5}'").read().strip()
    temp = os.popen("cat /sys/class/thermal/thermal_zone0/temp 2>/dev/null | awk '{print $1/1000}'").read().strip()
    uptime = os.popen("uptime -p").read().strip()
    visitors = os.popen("cat /var/log/nginx/access.log 2>/dev/null | wc -l").read().strip()

    return f"""
🖥️ <b>SERVER STATUS</b>
📅 {now}

📊 CPU: {cpu}%
🌡️ Suhu: {temp}°C
🧠 RAM: {ram}
💾 Disk: {disk}
⏱️ Uptime: {uptime}

👥 Request: {visitors}

🔗 https://lmsmoodletest.xyz
"""

if __name__ == "__main__":
    send_message(get_server_info())
    print("Laporan terkirim!")
