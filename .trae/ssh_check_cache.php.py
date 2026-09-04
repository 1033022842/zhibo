import sys
import paramiko

HOST = "38.181.44.164"
USER = "root"
PWD = "Mr3$Ye7]Dx7|"

CMDS = [
    "echo '=== config/cache.php redis ==='",
    "grep -niA12 \"'redis'\" /www/wwwroot/douyin/php/config/cache.php 2>/dev/null",
    "echo '=== config/redis.php ==='",
    "cat /www/wwwroot/douyin/php/config/redis.php 2>/dev/null",
    "echo '=== config 缓存文件 ==='",
    "find /www/wwwroot/douyin/php/runtime -name '*.php' 2>/dev/null | head -20",
    "echo '=== 直接测试 thinkphp redis 连接 ==='",
    "redis-cli -a redis4live2026 llen 'list:keyword:room:1' 2>&1 | tail -1",
]


def main():
    cli = paramiko.SSHClient()
    cli.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    cli.connect(HOST, username=USER, password=PWD, timeout=20)
    for c in CMDS:
        print("$", c)
        stdin, stdout, stderr = cli.exec_command(c, timeout=60)
        out = stdout.read().decode("utf-8", "replace")
        err = stderr.read().decode("utf-8", "replace")
        if out:
            print(out.rstrip())
        if err and "Warning" not in err:
            print("[stderr]", err.rstrip())
        print()
    cli.close()
    return 0


if __name__ == "__main__":
    sys.exit(main())
