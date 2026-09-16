import io

import paramiko

HOST = "172.81.98.55"
USER = "root"
PWD = "0wJfy`50tJQzG/0X"
LOG = r"d:\phpstudy_pro\WWW\douyin\.trae\_chat_diag2.log"

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
    ("home-26 全部记录明细", LOAD_ENV + "mysql -h\"$DBH\" -u\"$DBU\" \"$DBN\" -e \"SELECT id,role,media_kind,IF(media_url='','-',LEFT(media_url,30)) url,media_id,unlock_price,unlocked,LEFT(content,16) msg,created_at FROM lp_ai_chat_message WHERE device_id='web-mu140pfyfqu8dpil' AND role_key='home-26' ORDER BY id;\" 2>&1 | tail -12"),
    ("该接口真实返回", "curl -s -m 25 -k --resolve sugus.it.com:443:127.0.0.1 'https://sugus.it.com/api/live/chatHistory?content_id=0&device_id=web-mu140pfyfqu8dpil&limit=50&role_key=home-26' | head -c 1500; echo"),
    ("home-26 页面入口", "grep -o 'Chat.html?id=home-26\\|home-26' /www/wwwroot/douyin/ai-girl-malaysia.com/Home.html | head -3"),
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
            log(o[:3000])
        if e:
            log("[stderr] " + e[:500])
    except Exception as ex:
        log(f"[TIMEOUT/ERR] {type(ex).__name__}: {ex}")
    flush()

cli.close()
log("DIAG2 DONE")
flush()
print("done")
