import sys
import paramiko

HOST = "38.181.44.164"
USER = "root"
PWD = "Mr3$Ye7]Dx7|"

CMDS = [
    "uname -a",
    "head -5 /etc/os-release",
    "echo '--- mem/disk ---'",
    "free -h",
    "df -h / | tail -2",
    "echo '--- installed ---'",
    "for c in nginx php mysql mysqld redis-server redis-cli ffmpeg docker git node npm; do printf '%-14s' $c; command -v $c || echo '(none)'; done",
    "php -v 2>/dev/null | head -1",
    "nginx -v 2>&1",
    "mysql --version 2>/dev/null",
    "docker --version 2>/dev/null",
    "echo '--- code dirs ---'",
    "ls -la /data/www/ 2>/dev/null",
    "ls -la /www/wwwroot/ 2>/dev/null",
    "echo '--- listen ports ---'",
    "ss -tlnp 2>/dev/null | head -50",
    "echo '--- srs ---'",
    "ls -d /usr/local/srs /opt/srs 2>/dev/null",
    "echo '--- bt panel ---'",
    "ls /www/server 2>/dev/null | head -30",
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
