"""把「左侧会话列表读服务端历史」的修复发布到 172.81.98.55

用法：python .trae/deploy_chat_convs_172.py [all|php|static|verify]
"""
import datetime
import os
import sys
import time

import paramiko

try:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
except Exception:
    pass

HOST = "172.81.98.55"
USER = "root"
PWD = "0wJfy`50tJQzG/0X"

LOCAL = r"d:\phpstudy_pro\WWW\douyin"
REMOTE = "/www/wwwroot/douyin"
PHP_DIR = REMOTE + "/php"
SITE = REMOTE + "/ai-girl-malaysia.com"
STAMP = datetime.datetime.now().strftime("%Y%m%d_%H%M%S")
LOG = os.path.join(LOCAL, ".trae", "_deploy_chat_convs.log")

PHP_FILES = [
    "php/app/api/controller/Live.php",
    "php/route/live.php",
]

STATIC_FILES = [
    "ai-girl-malaysia.com/Chat.html",
    "ai-girl-malaysia.com/js/chat.js",
]

RELOAD_STEP = r"""
set -e
mkdir -p %s/runtime
chown -R www-data:www-data %s/public %s/runtime
systemctl reload php8.3-fpm
echo "php-fpm reloaded"
""" % (PHP_DIR, PHP_DIR, PHP_DIR)

VERIFY_STEP = r"""
echo "--- PHP 语法 ---"
php -l %s/app/api/controller/Live.php
echo "--- 静态文件 ---"
grep -o 'chat.js?v=[0-9a-z]*' %s/Chat.html
grep -c 'setupLogout' %s/js/chat.js
echo "--- 数据库：聊天记录按 user_id 分布 ---"
cd %s
eval "$(php -r '$c = parse_ini_file(".env", true)["DATABASE"];
  echo "DBH=".escapeshellarg($c["HOSTNAME"])."\n";
  echo "DBU=".escapeshellarg($c["USERNAME"])."\n";
  echo "DBP=".escapeshellarg($c["PASSWORD"])."\n";
  echo "DBN=".escapeshellarg($c["DATABASE"])."\n";')"
export MYSQL_PWD="$DBP"
mysql -h"$DBH" -u"$DBU" "$DBN" -e "
  SELECT user_id, COUNT(*) AS rows_cnt, COUNT(DISTINCT device_id) AS devices
  FROM lp_ai_chat_message GROUP BY user_id ORDER BY rows_cnt DESC LIMIT 10;"
echo "--- 游客接口（都应返回空数据） ---"
echo -n "conversations: "
curl -s -m 20 'http://127.0.0.1:8082/api/live/chatConversations?device_id=web-mu140pfyfqu8dpil'
echo
echo -n "history: "
curl -s -m 20 'http://127.0.0.1:8082/api/live/chatHistory?content_id=0&device_id=web-mu140pfyfqu8dpil&limit=5&role_key=home-26'
echo
echo -n "send(guest): "
curl -s -m 20 -X POST 'http://127.0.0.1:8082/api/live/chat' \
  -H 'Content-Type: application/json' \
  -d '{"message":"guest-should-be-rejected","content_id":"0","device_id":"probe-guest-block","role_key":"home-26"}'
echo
echo "--- 拒绝后不应新增记录（probe-guest-block 应为 0） ---"
mysql -h"$DBH" -u"$DBU" "$DBN" -e "
  SELECT COUNT(*) AS guest_probe_rows FROM lp_ai_chat_message WHERE device_id='probe-guest-block';"
""" % (PHP_DIR, SITE, SITE, PHP_DIR)


def log_write(buf, text):
    buf.write(text.rstrip() + "\n")
    buf.flush()


def mkdir_remote(sftp, path):
    parts = path.strip("/").split("/")
    cur = ""
    for p in parts:
        cur += "/" + p
        try:
            sftp.stat(cur)
        except IOError:
            sftp.mkdir(cur)


def upload(sftp, buf, pairs):
    for rel, remote in pairs:
        local = os.path.join(LOCAL, rel.replace("/", os.sep))
        remote_dir = os.path.dirname(remote)
        try:
            sftp.stat(remote_dir)
        except IOError:
            mkdir_remote(sftp, remote_dir)
        try:
            with sftp.open(remote, "rb") as f:
                old = f.read()
            with sftp.open(remote + ".bak_convs_" + STAMP, "wb") as f:
                f.write(old)
        except IOError:
            pass
        with open(local, "rb") as f:
            data = f.read()
        with sftp.open(remote, "wb") as f:
            f.write(data)
        log_write(buf, f"uploaded {rel} -> {remote} ({len(data)} bytes)")


def run(cli, buf, title, cmd, timeout=300):
    log_write(buf, f"\n===== {title} =====")
    _, out, err = cli.exec_command(cmd, timeout=timeout)
    o = out.read().decode("utf-8", "replace").strip()
    e = err.read().decode("utf-8", "replace").strip()
    if o:
        log_write(buf, o[:8000])
    if e:
        log_write(buf, "[stderr] " + e[:3000])
    rc = out.channel.recv_exit_status()
    log_write(buf, f"[rc={rc}]")
    return rc, o


def main():
    action = sys.argv[1] if len(sys.argv) > 1 else "all"
    buf = open(LOG, "w", encoding="utf-8", buffering=1)
    try:
        do_deploy(buf, action)
    except Exception:
        import traceback
        log_write(buf, "!!!! FAILED !!!!\n" + traceback.format_exc())
        buf.close()
        raise
    buf.close()
    print("DEPLOY DONE ->", LOG)


def do_deploy(buf, action):
    cli = paramiko.SSHClient()
    cli.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    last = None
    for attempt in range(1, 6):
        try:
            cli.connect(
                HOST,
                username=USER,
                password=PWD,
                timeout=60,
                banner_timeout=120,
                auth_timeout=120,
                allow_agent=False,
                look_for_keys=False,
            )
            last = None
            break
        except Exception as exc:  # 线上偶发 SSH banner/Auth 超时，重试即可
            last = exc
            print("connect attempt %d failed: %s" % (attempt, exc))
            time.sleep(5)
    if last is not None:
        raise last
    sftp = cli.open_sftp()

    if action in ("all", "php"):
        upload(sftp, buf, [(rel, PHP_DIR + "/" + rel.split("/", 1)[1]) for rel in PHP_FILES])
    if action in ("all", "static"):
        upload(sftp, buf, [(rel, REMOTE + "/" + rel) for rel in STATIC_FILES])
    if action in ("all", "php"):
        run(cli, buf, "重载 php-fpm", RELOAD_STEP, timeout=120)
    if action in ("all", "verify", "php", "static"):
        run(cli, buf, "线上校验", VERIFY_STEP, timeout=180)

    sftp.close()
    cli.close()


if __name__ == "__main__":
    main()
