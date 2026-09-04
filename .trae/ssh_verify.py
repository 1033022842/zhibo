import sys
import paramiko

HOST = "38.181.44.164"
USER = "root"
PWD = "Mr3$Ye7]Dx7|"

CMDS = [
    "echo '=== SRS streams ==='",
    "curl -s http://127.0.0.1:1986/api/v1/streams/",
    "echo ''",
    "echo '=== room state ==='",
    "mysql -uzhibo -p12345678 -N -e \"SELECT room_id, current_state, current_mode, updated_at FROM zhibo.lp_room_state_snapshot WHERE room_id=1;\"",
    "echo '=== hls dir ==='",
    "ls -la /www/wwwroot/douyin/hls/room/1/ 2>/dev/null | head -12",
    "echo '=== hls count ==='",
    "ls /www/wwwroot/douyin/hls/room/1/*.ts 2>/dev/null | wc -l",
    "echo '=== recent publish log ==='",
    "tail -n 15 /var/log/srs.log 2>/dev/null | grep -i 'publish\\|unpublish\\|accept' | tail -10",
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
