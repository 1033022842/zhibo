import sys
import paramiko

HOST = "38.181.44.164"
USER = "root"
PWD = "Mr3$Ye7]Dx7|"

CMDS = [
    "echo '=== redis requirepass ==='",
    "grep -n 'requirepass' /www/server/redis/redis.conf 2>/dev/null",
    "echo '=== redis-cli auth test ==='",
    "redis-cli -a '123456' ping 2>&1 | head -2",
    "echo '=== ws-webman redis config ==='",
    "cat /www/wwwroot/douyin/apps/ws-webman/.env 2>/dev/null | grep -iA3 redis",
    "grep -rn 'password\\|host\\|port' /www/wwwroot/douyin/apps/ws-webman/config/redis.php 2>/dev/null | head -20",
    "echo '=== thinkphp .env redis ==='",
    "grep -iA4 '\\[REDIS\\]' /www/wwwroot/douyin/php/.env",
]


def main():
    cli = paramiko.SSHClient()
    cli.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    cli.connect(HOST, username=USER, password=PWD, timeout=20)
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
