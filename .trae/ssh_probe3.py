import sys
import paramiko

HOST = "38.181.44.164"
USER = "root"
PWD = "Mr3$Ye7]Dx7|"

CMDS = [
    "echo '=== key php files ==='",
    "ls -la /www/wwwroot/douyin/php/app/api/route/ai.php /www/wwwroot/douyin/php/app/api/controller/MachineAsset.php /www/wwwroot/douyin/php/app/api/controller/AiTask.php /www/wwwroot/douyin/php/app/ai/service/AiTaskService.php /www/wwwroot/douyin/php/app/ai/middleware/AiAuth.php /www/wwwroot/douyin/php/config/ai.php /www/wwwroot/douyin/php/config/jwt.php 2>&1",
    "echo '=== ai route ==='",
    "cat /www/wwwroot/douyin/php/app/api/route/ai.php 2>/dev/null",
    "echo '=== config ai.php ==='",
    "cat /www/wwwroot/douyin/php/config/ai.php 2>/dev/null",
    "echo '=== jwt.php ==='",
    "cat /www/wwwroot/douyin/php/config/jwt.php 2>/dev/null",
    "echo '=== AiTaskService stream-token? ==='",
    "grep -n 'getStreamToken\\|stream-token\\|streamToken' /www/wwwroot/douyin/php/app/ai/service/AiTaskService.php /www/wwwroot/douyin/php/app/api/controller/AiTask.php 2>/dev/null",
    "echo '=== MachineAsset role? ==='",
    "grep -n 'asset_role\\|role' /www/wwwroot/douyin/php/app/api/controller/MachineAsset.php 2>/dev/null",
    "echo '=== srs.conf ==='",
    "cat /usr/local/srs/conf/srs.conf 2>/dev/null",
    "echo '=== nginx vhost /api ==='",
    "grep -rn 'location /api\\|proxy_pass\\|server_name\\|9001' /www/server/panel/vhost/nginx/*.conf 2>/dev/null | head -50",
    "echo '=== nginx main ==='",
    "cat /www/server/panel/vhost/nginx/*.conf 2>/dev/null | head -120",
]


def main():
    cli = paramiko.SSHClient()
    cli.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    try:
        cli.connect(HOST, username=USER, password=PWD, timeout=20)
    except Exception as e:
        print("CONNECT_FAIL:", e)
        return 1

    for c in CMDS:
        print("$", c)
        stdin, stdout, stderr = cli.exec_command(c, timeout=30)
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
