import sys

import paramiko

try:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
except Exception:
    pass

HOST = "172.81.98.55"
USER = "root"
PWD = "0wJfy`50tJQzG/0X"

CMDS = [
    ("which mysql", "which mysql || echo NO_MYSQL"),
    ("env db lines", "grep -E '^database\\.' /www/wwwroot/douyin/php/.env | sed 's/password.*/password=***/'"),
    ("table exists", "ls -l /www/wwwroot/douyin/php/sql/upgrade_chat_role_key.sql"),
]


def main():
    cli = paramiko.SSHClient()
    cli.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    cli.connect(HOST, username=USER, password=PWD, timeout=20)
    for title, cmd in CMDS:
        print("=" * 8, title)
        _, out, err = cli.exec_command(cmd, timeout=30)
        print(out.read().decode("utf-8", "replace").rstrip()[:1500])
        e = err.read().decode("utf-8", "replace").rstrip()
        if e:
            print("[stderr]", e[:400])
        print()
    cli.close()


if __name__ == "__main__":
    main()
