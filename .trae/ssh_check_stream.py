import sys
import paramiko

HOST = "38.181.44.164"
USER = "root"
PWD = "Mr3$Ye7]Dx7|"

CMDS = [
    "echo '=== SRS streams 原始 ==='",
    "curl -s -m 10 'http://127.0.0.1:1985/api/v1/streams'",
    "echo ''",
    "echo '=== 房间当前状态 ==='",
    "mysql -uzhibo -p12345678 -N -e \"SELECT room_id, current_state, current_mode FROM zhibo.lp_live_room WHERE room_id=1;\" 2>/dev/null",
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
