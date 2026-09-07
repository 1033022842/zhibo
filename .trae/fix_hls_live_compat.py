import sys
import paramiko

HOST = "38.181.44.164"
USER = "root"
PWD = "Mr3$Ye7]Dx7|"
NGINX_CONF = "/www/server/panel/vhost/nginx/38.181.44.164.conf"

# 兼容规则：旧的 /hls/live/... 播放地址 301 到 SRS 实际输出 /hls/...
LIVE_REDIRECT = """    # 兼容旧 live 播放地址 -> SRS 实际 room 输出路径
    location ~ ^/hls/live/(.+)$ {
        return 301 /hls/$1;
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

    with sftp.open(NGINX_CONF, "r") as f:
        nginx = f.read().decode("utf-8")

    marker = "    location /hls/ {"
    if "location ~ ^/hls/live/" not in nginx:
        sh(cli, f"cp {NGINX_CONF} {NGINX_CONF}.bak.livecompat")
        nginx = nginx.replace(marker, LIVE_REDIRECT + "\n" + marker, 1)
        with sftp.open(NGINX_CONF, "w") as f:
            f.write(nginx.encode("utf-8"))
        print("[fix-nginx] 已插入 live 兼容重定向规则")
    else:
        print("[skip] live 兼容规则已存在")

    sftp.close()

    # 校验并 reload
    out, err = sh(cli, "nginx -t 2>&1 && nginx -s reload 2>&1; echo done")
    print("[nginx]", (out or err).strip())

    # 验证
    print("\n=== 验证 ===")
    for url in [
        "/hls/live/room/1/index.m3u8",
        "/hls/room/1/index.m3u8",
        "/hls/live/room/1/218995.ts",
    ]:
        out, err = sh(cli, f"curl -s -o /dev/null -w '%{{http_code}} %{{redirect_url}}' -m 10 -H 'Host: 38.181.44.164' 'http://127.0.0.1{url}'")
        print(f"{url} -> {(out or err).strip()}")

    cli.close()
    print("\nDONE")
    return 0


if __name__ == "__main__":
    sys.exit(main())
