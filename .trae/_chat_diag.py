import io

import paramiko

HOST = "172.81.98.55"
USER = "root"
PWD = "0wJfy`50tJQzG/0X"
LOG = r"d:\phpstudy_pro\WWW\douyin\.trae\_chat_diag.log"

LOAD_ENV = r"""
cd /www/wwwroot/douyin/php
eval "$(php -r '$c = parse_ini_file(".env", true)["DATABASE"];
  echo "DBH=".escapeshellarg($c["HOSTNAME"])."\n";
  echo "DBU=".escapeshellarg($c["USERNAME"])."\n";
  echo "DBP=".escapeshellarg($c["PASSWORD"])."\n";
  echo "DBN=".escapeshellarg($c["DATABASE"])."\n";')"
export MYSQL_PWD="$DBP"
"""

CMDS = [
    ("最近 12 条落库记录", LOAD_ENV + "mysql -h\"$DBH\" -u\"$DBU\" \"$DBN\" -e \"SELECT id,user_id,device_id,content_id,role_key,role,LEFT(content,18) msg,created_at FROM lp_ai_chat_message ORDER BY id DESC LIMIT 12;\" 2>&1 | tail -15"),
    ("今天的 device/role 汇总", LOAD_ENV + "mysql -h\"$DBH\" -u\"$DBU\" \"$DBN\" -e \"SELECT device_id,user_id,content_id,role_key,COUNT(*) c,MAX(created_at) last_at FROM lp_ai_chat_message WHERE created_at>='2026-09-16' GROUP BY device_id,user_id,content_id,role_key ORDER BY last_at DESC LIMIT 12;\" 2>&1 | tail -14"),
    ("聊天相关请求（最近）", "grep -o 'GET /api/live/chat[A-Za-z]*[^ ]*' /www/wwwroot/douyin/php/public/debug.log | tail -25; echo '--- POST chat 次数 ---'; grep -c 'POST /api/live/chat ' /www/wwwroot/douyin/php/public/debug.log"),
    ("前端是否用会话列表接口", "grep -rc 'chatConversations' /www/wwwroot/douyin/ai-girl-malaysia.com/js/ /www/wwwroot/douyin/ai-girl-malaysia.com/*.html 2>/dev/null | grep -v ':0' | head"),
    ("当前 chat.js 版本", "ls -la /www/wwwroot/douyin/ai-girl-malaysia.com/js/chat.js; grep -o 'chat.js?v=[0-9a-z]*' /www/wwwroot/douyin/ai-girl-malaysia.com/Chat.html"),
]

buf = io.StringIO()


def log(s):
    buf.write(s + "\n")


def flush():
    with open(LOG, "w", encoding="utf-8") as f:
        f.write(buf.getvalue())


cli = paramiko.SSHClient()
cli.set_missing_host_key_policy(paramiko.AutoAddPolicy())
cli.connect(HOST, username=USER, password=PWD, timeout=25)

for name, cmd in CMDS:
    log(f"===== {name} =====")
    flush()
    try:
        _, out, err = cli.exec_command(cmd, timeout=90)
        o = out.read().decode("utf-8", "replace").strip()
        e = err.read().decode("utf-8", "replace").strip()
        if o:
            log(o[:4000])
        if e:
            log("[stderr] " + e[:600])
    except Exception as ex:
        log(f"[TIMEOUT/ERR] {type(ex).__name__}: {ex}")
    flush()

cli.close()
log("DIAG DONE")
flush()
print("done")
