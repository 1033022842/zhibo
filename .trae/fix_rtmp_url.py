import sys
import paramiko

HOST = "38.181.44.164"
USER = "root"
PWD = "Mr3$Ye7]Dx7|"
ENV_PATH = "/www/wwwroot/douyin/php/.env"

OLD = "RTMP_PUSH_URL = rtmp://38.181.44.164:1935/live/"
NEW = "RTMP_PUSH_URL = rtmp://38.181.44.164:1935/"


def sh(cli, cmd, timeout=60):
    stdin, stdout, stderr = cli.exec_command(cmd, timeout=timeout)
    out = stdout.read().decode("utf-8", "replace")
    err = stderr.read().decode("utf-8", "replace")
    return out, err


def main():
    cli = paramiko.SSHClient()
    cli.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    cli.connect(HOST, username=USER, password=PWD, timeout=20)
    sftp = cli.open_sftp()

    # 1. 读取 .env
    with sftp.open(ENV_PATH, "r") as f:
        env = f.read().decode("utf-8")

    if OLD not in env:
        print("[skip] 未找到旧配置:", OLD)
    else:
        sh(cli, f"cp {ENV_PATH} {ENV_PATH}.bak.rtmp")
        new_env = env.replace(OLD, NEW)
        with sftp.open(ENV_PATH, "w") as f:
            f.write(new_env.encode("utf-8"))
        print("[fix] RTMP_PUSH_URL 已更新:", OLD, "->", NEW)

    sftp.close()

    # 2. 清理 ThinkPHP 缓存
    out, err = sh(cli, "rm -rf /www/wwwroot/douyin/php/runtime/cache/* /www/wwwroot/douyin/php/runtime/route.php /www/wwwroot/douyin/php/runtime/config.php 2>/dev/null; echo cleaned")
    print("[cache]", (out or err).strip())

    # 3. 验证 .env
    print("\n=== verify .env ===")
    out, err = sh(cli, "grep -n 'RTMP_PUSH_URL' /www/wwwroot/douyin/php/.env")
    print((out or err).strip())

    # 4. 验证接口返回的 push_url
    print("\n=== verify 接口 (https://hsl.lat) ===")
    out, err = sh(cli, "curl -sk -m 20 -H 'X-Api-Key: live-ai-api-key-2026' 'https://hsl.lat/api/v1/liveportrait/config?room_id=1'")
    print((out[:1200] or err).strip())

    cli.close()
    print("\nDONE")
    return 0


if __name__ == "__main__":
    sys.exit(main())
