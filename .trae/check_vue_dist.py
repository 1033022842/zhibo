import sys
import paramiko

HOST = "38.181.44.164"
USER = "root"
PWD = "Mr3$Ye7]Dx7|"

CMDS = [
    "ls -la /www/wwwroot/douyin/vue/ | grep -E 'dist|backup'",
    "echo '--- dist 内容 ---'",
    "ls -la /www/wwwroot/douyin/vue/dist/ 2>/dev/null | head -20",
    "echo '--- 检查是否有反斜杠文件名 ---'",
    "find /www/wwwroot/douyin/vue/dist -name '*\\\\*' 2>/dev/null | head -10",
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
