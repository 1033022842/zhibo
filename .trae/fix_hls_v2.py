import sys
import paramiko

HOST = "38.181.44.164"
USER = "root"
PWD = "Mr3$Ye7]Dx7|"
BASE = "/www/wwwroot/douyin"

PHP_FIXES = [
    (
        f"{BASE}/php/app/room/service/RoomService.php",
        "'/hls/' . $streamAlias . '/index.m3u8'",
        "'/hls/' . $streamAlias . '/index.m3u8?v=2'",
    ),
    (
        f"{BASE}/php/app/ai/service/LivePortraitService.php",
        "'/hls/' . $streamAlias . '/index.m3u8'",
        "'/hls/' . $streamAlias . '/index.m3u8?v=2'",
    ),
]

NGINX_CONF = "/www/server/panel/vhost/nginx/38.181.44.164.conf"

# 要删除的旧 live 301 块
LIVE_301_BLOCK = """    # 兼容旧 live 播放地址 -> SRS 实际 room 输出路径
    location ~ ^/hls/live/(.+)$ {
        return 301 /hls/$1;
    }
"""

# 要插入到 location /hls/ 内部的 rewrite 规则
REWRITE_RULES = """        # 内部规范化旧 URL 变体（last 内部重写，不产生 301 缓存）
        rewrite ^/hls/live/(.+)/index/index\\.m3u8$ /hls/$1/index.m3u8 last;
        rewrite ^/hls/(.+)/index/index\\.m3u8$ /hls/$1/index.m3u8 last;
        rewrite ^/hls/live/(.+)$ /hls/$1 last;
"""

HLS_LOC_MARKER = "    location /hls/ {\n        disable_symlinks off;\n"


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

    # 1. PHP 加版本参数
    for path, old, new in PHP_FIXES:
        with sftp.open(path, "r") as f:
            content = f.read().decode("utf-8")
        if old not in content:
            print(f"[skip-php] {path.split('/')[-1]} 未找到 {old}")
            continue
        sh(cli, f"cp {path} {path}.bak.v2")
        content = content.replace(old, new)
        with sftp.open(path, "w") as f:
            f.write(content.encode("utf-8"))
        print(f"[fix-php] {path.split('/')[-1]}: 已加 ?v=2")

    # 2. Nginx：删除 live 301，加内部 rewrite
    with sftp.open(NGINX_CONF, "r") as f:
        nginx = f.read().decode("utf-8")

    changed = False
    if LIVE_301_BLOCK in nginx:
        sh(cli, f"cp {NGINX_CONF} {NGINX_CONF}.bak.v2")
        nginx = nginx.replace(LIVE_301_BLOCK, "")
        changed = True
        print("[fix-nginx] 已删除 live 301 块")
    else:
        print("[skip-nginx] 未找到 live 301 块")

    if HLS_LOC_MARKER in nginx and "rewrite ^/hls/live/" not in nginx:
        if not changed:
            sh(cli, f"cp {NGINX_CONF} {NGINX_CONF}.bak.v2")
        nginx = nginx.replace(HLS_LOC_MARKER, HLS_LOC_MARKER + REWRITE_RULES, 1)
        changed = True
        print("[fix-nginx] 已插入内部 rewrite 规则")
    else:
        print("[skip-nginx] rewrite 规则已存在或 marker 未找到")

    if changed:
        with sftp.open(NGINX_CONF, "w") as f:
            f.write(nginx.encode("utf-8"))

    sftp.close()

    # 3. 清缓存
    out, err = sh(cli, "rm -rf /www/wwwroot/douyin/php/runtime/cache/* /www/wwwroot/douyin/php/runtime/route.php 2>/dev/null; echo cleaned")
    print("[cache]", (out or err).strip())

    # 4. reload nginx
    out, err = sh(cli, "nginx -t 2>&1 && nginx -s reload 2>&1; echo done")
    print("[nginx]", (out or err).strip())

    # 5. 验证
    print("\n=== 验证接口 hls_url ===")
    out, err = sh(cli, "curl -sk -m 20 'https://hsl.lat/api/v1/feed/live?limit=3' | grep -o '\"hls_url\":\"[^\"]*\"' | head -3")
    print((out or err).strip())

    print("\n=== 验证各 URL 变体 (Host 38.181.44.164) ===")
    for url in [
        "/hls/room/1/index.m3u8?v=2",
        "/hls/room/1/index.m3u8",
        "/hls/live/room/1/index.m3u8",
        "/hls/live/room/1/index/index.m3u8",
        "/hls/room/1/index/index.m3u8",
    ]:
        out, err = sh(cli, f"curl -s -o /dev/null -w '%{{http_code}}' -m 10 -H 'Host: 38.181.44.164' 'http://127.0.0.1{url}'")
        print(f"{url} -> {(out or err).strip()}")

    cli.close()
    print("\nDONE")
    return 0


if __name__ == "__main__":
    sys.exit(main())
