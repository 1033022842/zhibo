import re
import sys
import paramiko

HOST = "38.181.44.164"
USER = "root"
PWD = "Mr3$Ye7]Dx7|"
BASE = "/www/wwwroot/douyin"

# 服务器上要替换的 PHP 行
PHP_FIXES = [
    (
        f"{BASE}/php/app/room/service/RoomService.php",
        "'/hls/' . $webrtcApp . '/' . $streamAlias . '/index.m3u8'",
        "'/hls/' . $streamAlias . '/index.m3u8'",
    ),
    (
        f"{BASE}/php/app/ai/service/LivePortraitService.php",
        "'/hls/live/' . $streamAlias . '/index.m3u8'",
        "'/hls/' . $streamAlias . '/index.m3u8'",
    ),
]

NGINX_CONF = "/www/server/panel/vhost/nginx/38.181.44.164.conf"
NGINX_OLD_BLOCK = """    # hls_legacy_redirect: old play_hls URLs -> SRS real file layout
    location ~ ^/hls/(?<hls_stream>(?!live/).+)\\.m3u8$ {
        return 301 /hls/live/$hls_stream/index.m3u8;
    }
"""


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

    # 1. 替换 PHP 文件中的 hls 地址
    for path, old, new in PHP_FIXES:
        with sftp.open(path, "r") as f:
            content = f.read().decode("utf-8")
        if old not in content:
            print(f"[skip] {path} 未找到: {old}")
            continue
        sh(cli, f"cp {path} {path}.bak.hls")
        new_content = content.replace(old, new)
        with sftp.open(path, "w") as f:
            f.write(new_content.encode("utf-8"))
        print(f"[fix-php] {path.split('/')[-1]}: {old} -> {new}")

    # 2. 删除 Nginx 旧重定向正则块
    with sftp.open(NGINX_CONF, "r") as f:
        nginx = f.read().decode("utf-8")
    if "hls_legacy_redirect" in nginx:
        sh(cli, f"cp {NGINX_CONF} {NGINX_CONF}.bak.hls")
        pattern = re.compile(
            r'[ \t]*# hls_legacy_redirect:.*?\n'
            r'[ \t]*location ~ \^/hls/.*?\n'
            r'[ \t]*return 301 /hls/live/.*?\n'
            r'[ \t]*\}\n',
            re.DOTALL,
        )
        new_nginx, cnt = pattern.subn("", nginx)
        with sftp.open(NGINX_CONF, "w") as f:
            f.write(new_nginx.encode("utf-8"))
        print(f"[fix-nginx] 删除 hls_legacy_redirect 正则块, 替换 {cnt} 处")
    else:
        print("[skip] nginx 未找到 hls_legacy_redirect 块")

    sftp.close()

    # 3. 清理 ThinkPHP 缓存
    out, err = sh(cli, "rm -rf /www/wwwroot/douyin/php/runtime/cache/* /www/wwwroot/douyin/php/runtime/route.php 2>/dev/null; echo cleaned")
    print("[cache]", (out or err).strip())

    # 4. 校验 nginx 并 reload
    out, err = sh(cli, "nginx -t 2>&1 && nginx -s reload 2>&1; echo reloaded")
    print("[nginx]", (out or err).strip())

    # 5. 验证
    print("\n=== 验证 Nginx hls 访问 ===")
    for url in ["/hls/room/1/index.m3u8", "/hls/live/room/1/index.m3u8"]:
        out, err = sh(cli, f"curl -s -o /dev/null -w '%{{http_code}}' -m 10 -H 'Host: 38.181.44.164' 'http://127.0.0.1{url}'")
        print(f"{url} -> {(out or err).strip()}")

    print("\n=== 验证 liveportrait config play_hls ===")
    out, err = sh(cli, "curl -sk -m 20 -H 'X-Api-Key: live-ai-api-key-2026' 'https://hsl.lat/api/v1/liveportrait/config?room_id=1' | grep -o '\"play_hls\":\"[^\"]*\"'")
    print((out or err).strip())

    cli.close()
    print("\nDONE")
    return 0


if __name__ == "__main__":
    sys.exit(main())
