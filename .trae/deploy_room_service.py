import sys
import paramiko

HOST = "38.181.44.164"
USER = "root"
PWD = "Mr3$Ye7]Dx7|"

LOCAL = r"d:\phpstudy_pro\WWW\douyin\php\app\room\service\RoomService.php"
REMOTE = "/www/wwwroot/douyin/php/app/room/service/RoomService.php"


def main():
    cli = paramiko.SSHClient()
    cli.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    cli.connect(HOST, username=USER, password=PWD, timeout=20)

    sftp = cli.open_sftp()
    sftp.put(LOCAL, REMOTE)
    sftp.close()
    print("[upload] RoomService.php")

    stdin, stdout, stderr = cli.exec_command(
        "rm -rf /www/wwwroot/douyin/php/runtime/cache/* 2>/dev/null; echo CACHE_CLEARED"
    )
    print(stdout.read().decode().strip())

    stdin, stdout, stderr = cli.exec_command(
        "curl -s -m 15 'http://127.0.0.1/api/v1/rooms/1' -H 'Host: 38.181.44.164'"
    )
    out = stdout.read().decode("utf-8", "replace")
    # 提取 hls_url
    import re
    m = re.search(r'"hls_url"\s*:\s*"([^"]+)"', out)
    print("[verify hls_url]", m.group(1) if m else "NOT FOUND")

    cli.close()
    return 0


if __name__ == "__main__":
    sys.exit(main())
