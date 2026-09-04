import sys
import paramiko

HOST = "38.181.44.164"
USER = "root"
PWD = "Mr3$Ye7]Dx7|"

CMDS = [
    "echo '=== hls 顶层 ==='",
    "ls -la /www/wwwroot/douyin/hls/",
    "echo '=== hls/room ==='",
    "ls -la /www/wwwroot/douyin/hls/room/ 2>/dev/null",
    "echo '=== hls/room/1 ==='",
    "ls -la /www/wwwroot/douyin/hls/room/1/ 2>/dev/null",
    "echo '=== 所有 m3u8 ==='",
    "find /www/wwwroot/douyin/hls -name '*.m3u8' 2>/dev/null",
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
