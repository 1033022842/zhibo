import sys

import paramiko

try:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
except Exception:
    pass

HOST = "172.81.98.55"
USER = "root"
PWD = "0wJfy`50tJQzG/0X"


def main():
    cli = paramiko.SSHClient()
    cli.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    cli.connect(HOST, username=USER, password=PWD, timeout=20)
    cmd = r"sed -E 's/(password|PASSWORD|pwd)[[:space:]]*=.*/\1=***/' /www/wwwroot/douyin/php/.env"
    _, out, err = cli.exec_command(cmd, timeout=30)
    print(out.read().decode("utf-8", "replace").rstrip()[:3000])
    e = err.read().decode("utf-8", "replace").rstrip()
    if e:
        print("[stderr]", e[:500])
    cli.close()


if __name__ == "__main__":
    main()
