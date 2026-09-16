import sys

import paramiko

try:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
except Exception:
    pass

HOST = "172.81.98.55"
USER = "root"
PWD = "0wJfy`50tJQzG/0X"

PHP_DIR = "/www/wwwroot/douyin/php"

# .env 是 INI 分段格式([DATABASE] HOSTNAME/USERNAME/PASSWORD/...)，用 php 解析最稳
LOAD_ENV = r"""
set -e
cd %s
eval "$(php -r '$c = parse_ini_file(".env", true)["DATABASE"];
  echo "DBH=".escapeshellarg($c["HOSTNAME"])."\n";
  echo "DBU=".escapeshellarg($c["USERNAME"])."\n";
  echo "DBP=".escapeshellarg($c["PASSWORD"])."\n";
  echo "DBN=".escapeshellarg($c["DATABASE"])."\n";
  echo "DBPORT=".escapeshellarg($c["HOSTPORT"] ?? "3306")."\n";')"
export MYSQL_PWD="$DBP"
echo "库: $DBN @ $DBH:$DBPORT  用户: $DBU"
""" % PHP_DIR

APPLY_SQL = LOAD_ENV + r"""
mysql -h"$DBH" -P"$DBPORT" -u"$DBU" "$DBN" < sql/upgrade_chat_role_key.sql 2>&1 | grep -v "Using a password" || true
echo "--- 表结构 ---"
mysql -h"$DBH" -P"$DBPORT" -u"$DBU" "$DBN" -e "SHOW COLUMNS FROM lp_ai_chat_message LIKE 'role_key'; SHOW INDEX FROM lp_ai_chat_message WHERE Key_name='idx_role_key_user';" 2>&1 | grep -v "Using a password"
"""

STEPS = [
    ("应用迁移", APPLY_SQL),
    ("接口验证：按 role_key 存历史",
     "curl -s -m 60 -X POST 'http://127.0.0.1:8082/api/live/chat' -H 'Content-Type: application/json' "
     "-d '{\"message\":\"remember me please\",\"content_id\":0,\"role_key\":\"home-verify\",\"device_id\":\"rk-test-001\","
     "\"lang\":\"en\",\"persona_name\":\"Riley\",\"persona_desc\":\"Playful\"}'"),
    ("接口验证：读 role_key 历史",
     "curl -s -m 30 'http://127.0.0.1:8082/api/live/chatHistory?content_id=0&role_key=home-verify&device_id=rk-test-001&limit=10' -H 'Accept: application/json'"),
    ("接口验证：非法 role_key 仍报错",
     "curl -s -m 20 'http://127.0.0.1:8082/api/live/chatHistory?content_id=0&device_id=rk-test-001&limit=5' -H 'Accept: application/json'"),
]


def main():
    cli = paramiko.SSHClient()
    cli.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    cli.connect(HOST, username=USER, password=PWD, timeout=20)
    for title, cmd in STEPS:
        print("=" * 8, title)
        _, out, err = cli.exec_command(cmd, timeout=120)
        print(out.read().decode("utf-8", "replace").rstrip()[:4000])
        e = err.read().decode("utf-8", "replace").rstrip()
        if e:
            print("[stderr]", e[:800])
        print()
        sys.stdout.flush()
    cli.close()
    print("DONE")


if __name__ == "__main__":
    main()
