import sys
import paramiko

HOST = "38.181.44.164"
USER = "root"
PWD = "Mr3$Ye7]Dx7|"

CMDS = [
    "echo '=== douyin root ==='",
    "ls -la /www/wwwroot/douyin/",
    "echo '=== liveportrait code ==='",
    "ls -la /www/wwwroot/douyin/php/app/ai/service/LivePortraitService.php 2>/dev/null || echo 'MISSING LivePortraitService'",
    "ls -la /www/wwwroot/douyin/php/app/api/controller/LivePortrait.php 2>/dev/null || echo 'MISSING LivePortrait controller'",
    "ls -la /www/wwwroot/douyin/php/sql/upgrade_liveportrait.sql 2>/dev/null || echo 'MISSING upgrade_liveportrait.sql'",
    "ls -la /www/wwwroot/douyin/services/liveportrait-worker/ 2>/dev/null || echo 'MISSING liveportrait-worker'",
    "echo '=== php .env ==='",
    "cat /www/wwwroot/douyin/php/.env 2>/dev/null",
    "echo '=== git ==='",
    "cd /www/wwwroot/douyin && git log --oneline -5 2>/dev/null; echo '--- status ---'; git status -s 2>/dev/null | head -30",
    "echo '=== srs conf ==='",
    "ls -la /usr/local/srs/conf/ 2>/dev/null",
    "grep -rn 'on_publish\\|on_unpublish\\|http_hooks\\|1935' /usr/local/srs/conf/*.conf 2>/dev/null | head -40",
    "echo '=== srs process cmd ==='",
    "ps -ef | grep -i srs | grep -v grep",
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
