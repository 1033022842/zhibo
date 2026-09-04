import sys
import paramiko

HOST = "38.181.44.164"
USER = "root"
PWD = "Mr3$Ye7]Dx7|"

CMDS = [
    # 1. 迁移（stdin 重定向，避免 shell 解析反引号）
    "mysql -uzhibo -p12345678 --default-character-set=utf8mb4 zhibo < /www/wwwroot/douyin/php/sql/upgrade_liveportrait.sql && echo MIGRATE_OK",
    "mysql -uzhibo -p12345678 -N -e \"SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='zhibo' AND TABLE_NAME='lp_media_asset' AND COLUMN_NAME='asset_role'; SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='zhibo' AND TABLE_NAME='lp_room_binding' AND COLUMN_NAME='portrait_asset_id';\"",
    # 2. 对比新旧路由
    "echo '--- old route ---'",
    "curl -s -m 15 -H 'X-Api-Key: live-ai-api-key-2026' 'http://127.0.0.1/api/v1/ai/tasks/pull?count=1' | head -c 400",
    "echo ''",
    "echo '--- new route ---'",
    "curl -s -m 15 -H 'X-Api-Key: live-ai-api-key-2026' 'http://127.0.0.1/api/v1/liveportrait/config?room_id=1' | head -c 400",
    "echo ''",
    # 3. 上传后的 ai.php 是否含 liveportrait
    "echo '--- ai.php liveportrait? ---'",
    "grep -n 'liveportrait' /www/wwwroot/douyin/php/app/api/route/ai.php",
    # 4. nginx /api/ 完整段
    "echo '--- nginx /api/ block ---'",
    "sed -n '60,120p' /www/server/panel/vhost/nginx/38.181.44.164.conf",
    # 5. runtime 目录
    "echo '--- runtime ---'",
    "ls -la /www/wwwroot/douyin/php/runtime/ 2>/dev/null",
    "ls -la /www/wwwroot/douyin/php/runtime/cache/ 2>/dev/null | head -20",
]


def main():
    cli = paramiko.SSHClient()
    cli.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    cli.connect(HOST, username=USER, password=PWD, timeout=20)
    for c in CMDS:
        print("$", c)
        stdin, stdout, stderr = cli.exec_command(c, timeout=120)
        out = stdout.read().decode("utf-8", "replace")
        err = stderr.read().decode("utf-8", "replace")
        if out:
            print(out.rstrip())
        if err:
            print("[stderr]", err.rstrip())
        print()
    cli.close()
    return 0


if __name__ == "__main__":
    sys.exit(main())
