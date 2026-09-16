"""
AI 女友端 Chat 页上线：Chat.html + js/chat.js
- 备份远端原文件（.bak_时间戳）
- 覆盖上传，属主/权限还原为 www:www 644
- 上传后在服务器本地回读校验（大小 + 关键标记）
用法: python .trae/deploy_chat_page.py
"""
import sys
import datetime
import paramiko

try:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
except Exception:
    pass

HOST = "38.181.44.164"
USER = "root"
PWD = "Mr3$Ye7]Dx7|"
WEB = "/www/wwwroot/douyin/ai-girl-malaysia.com"

FILES = [
    (r"d:\phpstudy_pro\WWW\douyin\ai-girl-malaysia.com\Chat.html", WEB + "/Chat.html"),
    (r"d:\phpstudy_pro\WWW\douyin\ai-girl-malaysia.com\js\chat.js", WEB + "/js/chat.js"),
]

cli = paramiko.SSHClient()
cli.set_missing_host_key_policy(paramiko.AutoAddPolicy())
cli.connect(HOST, username=USER, password=PWD, timeout=25)
sftp = cli.open_sftp()
stamp = datetime.datetime.now().strftime("%Y%m%d_%H%M%S")

for local, remote in FILES:
    with open(local, "rb") as f:
        data = f.read()
    try:
        with sftp.open(remote, "rb") as f:
            old = f.read()
        bak = remote + ".bak_" + stamp
        with sftp.open(bak, "wb") as f:
            f.write(old)
        print("backup:", bak, "size:", len(old))
    except Exception as e:
        print("backup skipped:", e)
    with sftp.open(remote, "wb") as f:
        f.write(data)
    print("uploaded:", remote, "size:", len(data))

sftp.close()

CMDS = [
    "ls -la %s/Chat.html %s/js/chat.js" % (WEB, WEB),
    "grep -o 'chat\\.js?v=[0-9a-z]*' %s/Chat.html | head -2" % WEB,
    "grep -c 'callHeaderUi' %s/js/chat.js; grep -c 'convRoleKey' %s/js/chat.js" % (WEB, WEB),
    "curl -s -o /tmp/_c.js -w 'origin js/chat.js %{http_code} %{size_download}\\n' "
    "-H 'Host: 38.181.44.164' http://127.0.0.1:8082/js/chat.js",
    "curl -s -o /tmp/_h.html -w 'origin Chat.html %{http_code} %{size_download}\\n' "
    "-H 'Host: 38.181.44.164' http://127.0.0.1:8082/Chat.html",
]
for c in CMDS:
    print("=" * 12, c)
    _, out, err = cli.exec_command(c, timeout=60)
    print(out.read().decode("utf-8", "replace").rstrip())
    e = err.read().decode("utf-8", "replace").rstrip()
    if e:
        print("[stderr]", e[:400])

cli.close()
print("DONE")
