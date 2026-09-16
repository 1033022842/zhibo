"""把聊天记录修复（js/chat.js + Chat.html 版本号）发布到 172"""
import datetime
import os
import sys

import paramiko

try:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
except Exception:
    pass

HOST = "172.81.98.55"
USER = "root"
PWD = "0wJfy`50tJQzG/0X"
LOCAL = r"d:\phpstudy_pro\WWW\douyin"
SITE = "/www/wwwroot/douyin/ai-girl-malaysia.com"
STAMP = datetime.datetime.now().strftime("%Y%m%d_%H%M%S")

FILES = [
    "ai-girl-malaysia.com/js/chat.js",
    "ai-girl-malaysia.com/Chat.html",
]

cli = paramiko.SSHClient()
cli.set_missing_host_key_policy(paramiko.AutoAddPolicy())
cli.connect(HOST, username=USER, password=PWD, timeout=25)
sftp = cli.open_sftp()

for rel in FILES:
    local = os.path.join(LOCAL, rel.replace("/", os.sep))
    remote = SITE + "/" + rel.split("/", 1)[1]
    try:
        with sftp.open(remote, "rb") as f:
            old = f.read()
        with sftp.open(remote + ".bak_chatfix_" + STAMP, "wb") as f:
            f.write(old)
        print("backup:", remote, len(old))
    except IOError:
        print("no backup (new file):", remote)

    with open(local, "rb") as f:
        data = f.read()
    with sftp.open(remote, "wb") as f:
        f.write(data)
    print("uploaded:", remote, len(data))

sftp.close()

_, out, err = cli.exec_command(
    "grep -c 'chat-default' " + SITE + "/js/chat.js; "
    "grep -o 'chat.js?v=[0-9a-z]*' " + SITE + "/Chat.html; "
    "curl -s -m 15 -o /dev/null -w 'page=%{http_code}\\n' http://127.0.0.1:8082/Chat.html; "
    "curl -s -m 15 -o /dev/null -w 'js=%{http_code}\\n' http://127.0.0.1:8082/js/chat.js",
    timeout=60,
)
print(out.read().decode("utf-8", "replace").strip())
print("[stderr]", err.read().decode("utf-8", "replace").strip()[:500])

cli.close()
print("CHAT FIX DEPLOYED")
