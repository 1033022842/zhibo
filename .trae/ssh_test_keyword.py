import sys
import paramiko

HOST = "38.181.44.164"
USER = "root"
PWD = "Mr3$Ye7]Dx7|"

CMDS = [
    # 模拟送礼：往 Redis 写一个关键词指令（和 GiftService 写入格式一致）
    "redis-cli rpush 'list:keyword:room:1' '{\"room_id\":1,\"command_type\":\"keyword\",\"params\":{\"keyword\":\"玫瑰\"}}'",
    "echo '--- 拉取动作指令 ---'",
    "curl -s -m 15 -H 'X-Api-Key: live-ai-api-key-2026' 'http://127.0.0.1/api/v1/liveportrait/instructions?room_id=1'",
    "echo ''",
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
        if err:
            print("[stderr]", err.rstrip())
        print()
    cli.close()
    return 0


if __name__ == "__main__":
    sys.exit(main())
