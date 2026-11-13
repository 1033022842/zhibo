#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
172.81.99.11 域名绑定 + HTTPS 一键脚本（HeyGirl 客户）

域名分工：
  主域 + www + 推广域名  -> 172.81.99.11   主站
  后台域名               -> 172.81.99.29   管理后台
  HLS 域名（可选）        -> 172.81.99.49   直播流（不给则 HLS 暂挂主域）

用法（本地 D:\\ever\\douyin\\douyin 下执行）：
  python .zcode/n99_bind_domain.py \
      --main heygirl.live \
      --promo "heygirl.cam,heygirl.fun,heygirl.one,heygirl.chat" \
      --admin heygirl.sbs \
      --email you@mail.com

  # 若之后要用独立 HLS 子域，加 --hls hls.heygirl.live（需先加解析）
  # 幂等，可重复执行
"""
import argparse
import io
import sys
import time

try:
    import paramiko
except ImportError:
    sys.exit("需要 paramiko: pip install paramiko")

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import deploy_creds as creds  # noqa: E402  (.zcode 下的凭据入口)

HOST = creds.get("heygirl")["host"]
USER = "root"
PASS = creds.get("heygirl")["password"]
IP_MAIN, IP_HLS, IP_ADMIN = "172.81.99.11", "172.81.99.49", "172.81.99.29"

SSH = None


def run(cmd, timeout=120):
    _, o, e = SSH.exec_command(cmd, timeout=timeout)
    out = o.read().decode(errors="replace")
    err = e.read().decode(errors="replace")
    return o.channel.recv_exit_status(), out, err


def put(text, remote):
    sf = SSH.open_sftp()
    sf.putfo(io.BytesIO(text.encode()), remote)
    sf.close()


def get(remote):
    sf = SSH.open_sftp()
    with sf.open(remote, "r") as f:
        return f.read().decode(errors="replace")


# ────────────────────────── 配置模板 ──────────────────────────

def _names(main, www, promo, default=True):
    n = [main]
    if www and www != main:
        n.append(www)
    n += promo
    if default:
        n.append("_")
    return " ".join(n)


ACME = "    location ^~ /.well-known/acme-challenge/ { root /var/www/html; }\n"


def main_block(names):
    """主站 server（.11），HTTP 完整版"""
    body = f"""server {{
    listen {IP_MAIN}:80 default_server;
    server_name {names};
{ACME}"""
    body += """    absolute_redirect off;
    root /www/wwwroot/douyin/vue/dist;
    index index.html;
    client_max_body_size 200m;
    access_log /var/log/nginx/zhibo.access.log;
    error_log  /var/log/nginx/zhibo.error.log;

    location ~ ^/hls/(?<hls_path>.+\\.m3u8)$ { alias /data/hls/$hls_path; add_header Access-Control-Allow-Origin *; add_header Cache-Control 'no-cache'; types { application/vnd.apple.mpegurl m3u8; } }
    location ~ ^/hls/(?<hls_path>.+\\.ts)$ { alias /data/hls/$hls_path; add_header Access-Control-Allow-Origin *; add_header Cache-Control 'public, max-age=86400'; types { video/mp2t ts; } }
    location = / { return 302 /ai/Home.html; }
    location / { try_files $uri $uri/ /index.html; add_header Cache-Control 'no-cache'; }
    location ~* \\.(js|css)$ { add_header Cache-Control 'public, max-age=3600'; }

    location ^~ /admin/assets/ { alias /www/wwwroot/douyin/admin/dist/assets/; expires 12h; }
    location /admin/ { alias /www/wwwroot/douyin/admin/dist/; index index.html; try_files $uri $uri/ @zhibo_admin_api; }
    location @zhibo_admin_api {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME /www/wwwroot/douyin/php/public/index.php;
        fastcgi_param SCRIPT_NAME /index.php;
        fastcgi_param PATH_INFO $uri;
    }
    location /api/ { root /www/wwwroot/douyin/php/public; try_files $uri /index.php$uri$is_args$args; }
    location ~ \\.php(/|$) {
        root /www/wwwroot/douyin/php/public;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME /www/wwwroot/douyin/php/public/index.php;
        fastcgi_param PATH_INFO $fastcgi_path_info;
        fastcgi_split_path_info ^(.+\\.php)(/.*)$;
    }
    location /api/v1/whep/ {
        rewrite ^/api/v1/whep/(.*)$ /$1/whep break;
        proxy_pass http://127.0.0.1:8889;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_read_timeout 86400;
    }
    location /ws/ {
        proxy_pass http://127.0.0.1:8788/;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_read_timeout 86400;
    }
    location ^~ /storage/ { alias /www/wwwroot/douyin/php/public/storage/; add_header Access-Control-Allow-Origin *; }
    location ^~ /ai/ { alias /www/wwwroot/douyin/ai-girl-malaysia.com/; index Login.html index.html; add_header Cache-Control 'no-cache'; }
    location = /aiweb { absolute_redirect off; return 301 /aiweb/; }
    location ^~ /aiweb/ { alias /www/wwwroot/douyin/ai_web/; index index.html; add_header Cache-Control 'no-cache'; }
    location ~* (\\.user\\.ini|\\.htaccess|\\.env$|\\.git) { deny all; }
}
"""
    return body


def main_block_https(names, cert):
    f, k = f"/etc/letsencrypt/live/{cert}/fullchain.pem", f"/etc/letsencrypt/live/{cert}/privkey.pem"
    return f"""server {{
    listen {IP_MAIN}:443 ssl http2;
    server_name {names};
    absolute_redirect off;
    root /www/wwwroot/douyin/vue/dist;
    index index.html;
    client_max_body_size 200m;
    access_log /var/log/nginx/zhibo.access.log;
    error_log  /var/log/nginx/zhibo.error.log;
    ssl_certificate {f};
    ssl_certificate_key {k};
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 1d;
    add_header Strict-Transport-Security "max-age=31536000" always;

    location ~ ^/hls/(?<hls_path>.+\\.m3u8)$ {{ alias /data/hls/$hls_path; add_header Access-Control-Allow-Origin *; add_header Cache-Control 'no-cache'; types {{ application/vnd.apple.mpegurl m3u8; }} }}
    location ~ ^/hls/(?<hls_path>.+\\.ts)$ {{ alias /data/hls/$hls_path; add_header Access-Control-Allow-Origin *; add_header Cache-Control 'public, max-age=86400'; types {{ video/mp2t ts; }} }}
    location = / {{ return 302 /ai/Home.html; }}
    location / {{ try_files $uri $uri/ /index.html; add_header Cache-Control 'no-cache'; }}
    location ~* \\.(js|css)$ {{ add_header Cache-Control 'public, max-age=3600'; }}
    location ^~ /admin/assets/ {{ alias /www/wwwroot/douyin/admin/dist/assets/; expires 12h; }}
    location /admin/ {{ alias /www/wwwroot/douyin/admin/dist/; index index.html; try_files $uri $uri/ @zhibo_admin_api; }}
    location @zhibo_admin_api {{
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME /www/wwwroot/douyin/php/public/index.php;
        fastcgi_param SCRIPT_NAME /index.php;
        fastcgi_param PATH_INFO $uri;
    }}
    location /api/ {{ root /www/wwwroot/douyin/php/public; try_files $uri /index.php$uri$is_args$args; }}
    location ~ \\.php(/|$) {{
        root /www/wwwroot/douyin/php/public;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME /www/wwwroot/douyin/php/public/index.php;
        fastcgi_param PATH_INFO $fastcgi_path_info;
        fastcgi_split_path_info ^(.+\\.php)(/.*)$;
    }}
    location /ws/ {{
        proxy_pass http://127.0.0.1:8788/;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_read_timeout 86400;
    }}
    location ^~ /storage/ {{ alias /www/wwwroot/douyin/php/public/storage/; add_header Access-Control-Allow-Origin *; }}
    location ^~ /ai/ {{ alias /www/wwwroot/douyin/ai-girl-malaysia.com/; index Login.html index.html; add_header Cache-Control 'no-cache'; }}
    location = /aiweb {{ absolute_redirect off; return 301 /aiweb/; }}
    location ^~ /aiweb/ {{ alias /www/wwwroot/douyin/ai_web/; index index.html; add_header Cache-Control 'no-cache'; }}
    location ~* (\\.user\\.ini|\\.htaccess|\\.env$|\\.git) {{ deny all; }}
}}
"""


def hls_block(server_name, ssl_lines="", https=False):
    """HLS server（.49）：只出 /hls/"""
    if https:
        return f"""server {{
    listen {IP_HLS}:443 ssl http2;
    server_name {server_name};
    absolute_redirect off;
    access_log /var/log/nginx/zhibo-hls.access.log;
    error_log  /var/log/nginx/zhibo-hls.error.log;
{ssl_lines}
    location ~ ^/hls/(?<hls_path>.+\\.m3u8)$ {{ alias /data/hls/$hls_path; add_header Access-Control-Allow-Origin * always; add_header Cache-Control 'no-cache' always; types {{ application/vnd.apple.mpegurl m3u8; }} }}
    location ~ ^/hls/(?<hls_path>.+\\.ts)$ {{ alias /data/hls/$hls_path; add_header Access-Control-Allow-Origin * always; add_header Cache-Control 'public, max-age=86400' always; types {{ video/mp2t ts; }} }}
    location / {{ return 404; }}
}}
"""
    return f"""server {{
    listen {IP_HLS}:80;
    server_name {server_name};
    absolute_redirect off;
    access_log /var/log/nginx/zhibo-hls.access.log;
    error_log  /var/log/nginx/zhibo-hls.error.log;
{ACME}    location ~ ^/hls/(?<hls_path>.+\\.m3u8)$ {{ alias /data/hls/$hls_path; add_header Access-Control-Allow-Origin * always; add_header Cache-Control 'no-cache' always; types {{ application/vnd.apple.mpegurl m3u8; }} }}
    location ~ ^/hls/(?<hls_path>.+\\.ts)$ {{ alias /data/hls/$hls_path; add_header Access-Control-Allow-Origin * always; add_header Cache-Control 'public, max-age=86400' always; types {{ video/mp2t ts; }} }}
    location / {{ return 404; }}
}}
"""


def admin_block(names, ssl_lines="", https=False):
    """后台 server（.29）"""
    if https:
        return f"""server {{
    listen {IP_ADMIN}:443 ssl http2;
    server_name {names};
    absolute_redirect off;
    root /www/wwwroot/douyin/php/public;
    index index.html index.php index.htm;
    client_max_body_size 200m;
    access_log /var/log/nginx/zhibo-admin.access.log;
    error_log  /var/log/nginx/zhibo-admin.error.log;
{ssl_lines}
    location = / {{ return 302 /admin/; }}
    location = /admin {{ return 301 /admin/; }}
    location / {{ return 404; }}
    location ^~ /storage/ {{ alias /www/wwwroot/douyin/php/public/storage/; expires 1h; }}
    location /api/ {{ try_files $uri /index.php$uri$is_args$args; }}
    location ^~ /admin/assets/ {{ alias /www/wwwroot/douyin/admin/dist/assets/; expires 12h; }}
    location /admin/ {{ alias /www/wwwroot/douyin/admin/dist/; index index.html; try_files $uri $uri/ @admin_api; }}
    location @admin_api {{
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME /www/wwwroot/douyin/php/public/index.php;
        fastcgi_param SCRIPT_NAME /index.php;
        fastcgi_param PATH_INFO $uri;
    }}
    location ~ \\.php(/|$) {{
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME /www/wwwroot/douyin/php/public/index.php;
        fastcgi_param PATH_INFO $fastcgi_path_info;
        fastcgi_split_path_info ^(.+\\.php)(/.*)$;
    }}
    location ~* (\\.env$|\\.git|composer\\.json|composer\\.lock) {{ return 404; }}
}}
"""
    return f"""server {{
    listen {IP_ADMIN}:80;
    server_name {names};
    absolute_redirect off;
    root /www/wwwroot/douyin/php/public;
    index index.html index.php index.htm;
    client_max_body_size 200m;
    access_log /var/log/nginx/zhibo-admin.access.log;
    error_log  /var/log/nginx/zhibo-admin.error.log;
{ACME}    location = / {{ return 302 /admin/; }}
    location = /admin {{ return 301 /admin/; }}
    location / {{ return 404; }}
    location ^~ /storage/ {{ alias /www/wwwroot/douyin/php/public/storage/; expires 1h; }}
    location /api/ {{ try_files $uri /index.php$uri$is_args$args; }}
    location ^~ /admin/assets/ {{ alias /www/wwwroot/douyin/admin/dist/assets/; expires 12h; }}
    location /admin/ {{ alias /www/wwwroot/douyin/admin/dist/; index index.html; try_files $uri $uri/ @admin_api; }}
    location @admin_api {{
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME /www/wwwroot/douyin/php/public/index.php;
        fastcgi_param SCRIPT_NAME /index.php;
        fastcgi_param PATH_INFO $uri;
    }}
    location ~ \\.php(/|$) {{
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME /www/wwwroot/douyin/php/public/index.php;
        fastcgi_param PATH_INFO $fastcgi_path_info;
        fastcgi_split_path_info ^(.+\\.php)(/.*)$;
    }}
    location ~* (\\.env$|\\.git|composer\\.json|composer\\.lock) {{ return 404; }}
}}
"""


LEGACY = """# ---------- 127.0.0.1:80  仅本机（内部调用，不做跳转） ----------
# 关键：SRS 发布钩子回调 http://127.0.0.1/api/v1/srs/*，worker 上报用
# http://127.0.0.1/api —— 必须保留，否则推流被钩子拒绝、HLS 全空。
server {
    listen 127.0.0.1:80;
    server_name _;
    location /api/ { root /www/wwwroot/douyin/php/public; try_files $uri /index.php$uri$is_args$args; }
    location ~ \\.php(/|$) {
        root /www/wwwroot/douyin/php/public;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME /www/wwwroot/douyin/php/public/index.php;
        fastcgi_param PATH_INFO $fastcgi_path_info;
        fastcgi_split_path_info ^(.+\\.php)(/.*)$;
    }
    location /ws/ {
        proxy_pass http://127.0.0.1:8788/;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_read_timeout 86400;
    }
    location ~ ^/hls/(?<hls_path>.+\\.(m3u8|ts))$ {
        alias /data/hls/$hls_path;
        types { application/vnd.apple.mpegurl m3u8; video/mp2t ts; }
    }
    location ^~ /storage/ { alias /www/wwwroot/douyin/php/public/storage/; }
    location / { return 404; }
}

# NOTE: 旧的 :8081（后台）/ :8082（AI 站）端口入口已于 2026-10-09 移除。
# 域名入口已完整覆盖（主站 /ai/、后台 admin 域名），无需端口访问，
# 移除后可防止用 IP:端口 绕过 HTTPS。如需临时恢复，见 /root/n99-bak/ 备份。
"""


def cert_ssl_lines(cert):
    return f"""    ssl_certificate /etc/letsencrypt/live/{cert}/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/{cert}/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 1d;
    add_header Strict-Transport-Security "max-age=31536000" always;
"""


def redirect_block(ip, names, target, default=False):
    """HTTP → HTTPS 跳转块（保留 ACME 校验路径）"""
    return f"""server {{
    listen {ip}:80{' default_server' if default else ''};
    server_name {names};
{ACME}    location / {{ return 301 https://{target}$request_uri; }}
}}
"""


def build_http(main_names, admin_names, hls_name):
    blocks = main_block(main_names) + "\n" + admin_block(admin_names)
    # .49 HLS 源站始终保留（IP 无法签证书，仅 HTTP，供 CDN 回源/直连）
    blocks += "\n" + hls_block("_")
    if hls_name != "_":
        blocks += "\n" + redirect_block(IP_HLS, hls_name, hls_name)
        blocks += "\n" + hls_block(hls_name, cert_ssl_lines(cert_main), https=True)
    return blocks + "\n" + LEGACY


def build_https(main_names, admin_names, hls_name, cert_main, cert_admin):
    first = main_names.split()[0]
    admin_first = admin_names.split()[0]
    blocks = redirect_block(IP_MAIN, main_names, first, default=True)
    blocks += "\n" + main_block_https(main_names, cert_main)
    blocks += "\n" + redirect_block(IP_ADMIN, admin_names, admin_first)
    blocks += "\n" + admin_block(admin_names, cert_ssl_lines(cert_admin), https=True)
    # .49 HLS 源站始终保留（IP 无法签证书，仅 HTTP，供 CDN 回源/直连）
    blocks += "\n" + hls_block("_")
    if hls_name != "_":
        blocks += "\n" + redirect_block(IP_HLS, hls_name, hls_name)
        blocks += "\n" + hls_block(hls_name, cert_ssl_lines(cert_main), https=True)
    return blocks + "\n" + LEGACY


# ────────────────────────── 应用层 ──────────────────────────

def env_set(text, section, key, value):
    lines = text.splitlines()
    sec = None
    for i, ln in enumerate(lines):
        if ln.strip() == f"[{section}]":
            sec = i
            break
    if sec is None:
        return "\n".join(lines + ["", f"[{section}]", f"{key} = {value}"]) + "\n"
    j = sec + 1
    while j < len(lines) and not lines[j].strip().startswith("["):
        if lines[j].split("=")[0].strip() == key:
            lines[j] = f"{key} = {value}"
            return "\n".join(lines) + "\n"
        j += 1
    lines.insert(sec + 1, f"{key} = {value}")
    return "\n".join(lines) + "\n"


def dns_ok(domain, ip):
    _, out, _ = run(f"dig +short A {domain} | tail -1")
    got = out.strip()
    ok = got == ip
    print(f"  {domain:24s} -> {got or '(无解析)':16s} 期望 {ip:16s} {'✔' if ok else '✘'}")
    return ok


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--main", required=True)
    ap.add_argument("--www", default="")
    ap.add_argument("--promo", default="", help="推广域名，逗号分隔")
    ap.add_argument("--admin", required=True)
    ap.add_argument("--hls", default="", help="HLS 独立域名（可选；不给则 HLS 暂挂主域）")
    ap.add_argument("--hls-cdn", default="", help="HLS 播放走 CDN 时的域名，如 https://hey-girl.b-cdn.net（不给则用主域）")
    ap.add_argument("--email", default="")
    ap.add_argument("--skip-dns-check", action="store_true")
    a = ap.parse_args()

    promo = [d.strip().lower() for d in a.promo.split(",") if d.strip()]
    main_d, admin_d = a.main.strip().lower(), a.admin.strip().lower()
    www_d = a.www.strip().lower()
    hls_d = a.hls.strip().lower()
    main_names = _names(main_d, www_d, promo)
    admin_names = f"{admin_d} _"
    hls_name = hls_d or "_"
    cert_main = main_d.replace(".", "-")
    cert_admin = admin_d.replace(".", "-")

    global SSH
    SSH = paramiko.SSHClient()
    SSH.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    SSH.connect(HOST, 22, USER, PASS, timeout=25)
    print(f"已连接 {HOST}\n")

    # 1. DNS
    print("[1/6] DNS 校验")
    if a.skip_dns_check:
        print("  跳过")
    else:
        ok = True
        for d in [main_d] + ([www_d] if www_d and www_d != main_d else []) + promo:
            ok &= dns_ok(d, IP_MAIN)
        ok &= dns_ok(admin_d, IP_ADMIN)
        if hls_d:
            ok &= dns_ok(hls_d, IP_HLS)
        if not ok:
            print("\n  解析未就绪，请先在域名商加 A 记录后重跑（或 --skip-dns-check 跳过）。")
            sys.exit(1)

    # 2. HTTP 配置
    print("\n[2/6] 部署 HTTP 配置")
    run("mkdir -p /var/www/html /root/n99-bak && cp -a /etc/nginx/sites-available/zhibo.conf /root/n99-bak/zhibo.conf.$(date +%s) 2>/dev/null; echo ok")
    put(build_http(main_names, admin_names, hls_name), "/etc/nginx/sites-available/zhibo.conf")
    _, o, e = run("nginx -t 2>&1")
    if "successful" not in o:
        print("  nginx -t 失败:\n" + o + e)
        sys.exit(1)
    run("systemctl restart nginx")
    print("  已生效")

    # 3. 证书
    print("\n[3/6] 签发证书")
    email_arg = f"--email {a.email}" if a.email else "--register-unsafely-without-email"
    main_domains = " ".join(f"-d {d}" for d in [main_d] + ([www_d] if www_d and www_d != main_d else []) + promo + ([hls_d] if hls_d else []))
    rc, o, e = run(f"certbot certonly --webroot -w /var/www/html --cert-name {cert_main} {main_domains} "
                   f"--non-interactive --agree-tos {email_arg} 2>&1 | tail -15", timeout=300)
    print("  主站证书:", o.strip().splitlines()[-1] if o.strip() else e.strip())
    if rc != 0:
        print(o)
        sys.exit(1)
    rc, o, e = run(f"certbot certonly --webroot -w /var/www/html --cert-name {cert_admin} -d {admin_d} "
                   f"--non-interactive --agree-tos {email_arg} 2>&1 | tail -15", timeout=300)
    print("  后台证书:", o.strip().splitlines()[-1] if o.strip() else e.strip())
    if rc != 0:
        print(o)
        sys.exit(1)

    # 4. HTTPS 配置
    print("\n[4/6] 部署 HTTPS 配置")
    put(build_https(main_names, admin_names, hls_name, cert_main, cert_admin), "/etc/nginx/sites-available/zhibo.conf")
    _, o, e = run("nginx -t 2>&1")
    if "successful" not in o:
        print("  nginx -t 失败:\n" + o + e)
        sys.exit(1)
    run("systemctl restart nginx")
    print("  已启用 HTTPS（80 跳 443）")

    # 5. 应用层
    print("\n[5/6] 应用层配置")
    env = get("/www/wwwroot/douyin/php/.env")
    hls_target = a.hls_cdn.strip().rstrip("/") or (f"https://{hls_d}" if hls_d else f"https://{main_d}")
    env = env_set(env, "AI", "HLS_CDN_DOMAIN", hls_target)
    env = env_set(env, "STARPAY", "NOTIFY_URL", f"https://{main_d}/api/v1/recharge/notify")
    env = env_set(env, "STARPAY", "RETURN_URL", f"https://{main_d}/ai/purchasePoints.html")
    env = env_set(env, "STARPAY", "RETURN_URL_VIP", f"https://{main_d}/ai/Subscriptions.html")
    env = env_set(env, "FLOWPAY", "NOTIFY_URL", f"https://{main_d}/api/v1/recharge/notifyFlowPay")
    env = env_set(env, "FLOWPAY", "RETURN_URL", f"https://{main_d}/ai/purchasePoints.html")
    env = env_set(env, "FLOWPAY", "RETURN_URL_VIP", f"https://{main_d}/ai/Subscriptions.html")
    sf = SSH.open_sftp()
    with sf.open("/www/wwwroot/douyin/php/.env", "w") as f:
        f.write(env)
    sf.close()
    print(f"  HLS_CDN_DOMAIN = {hls_target}（商户号/密钥仍需另换）")
    print(f"  支付 NOTIFY/RETURN -> {main_d}")

    cors = ",".join([main_d] + ([www_d] if www_d and www_d != main_d else []) + promo + [admin_d])
    run(f"sed -i \"s#cors_request_domain'[[:space:]]*=>[[:space:]]*'[^']*'#cors_request_domain' => '{cors}'#\" /www/wwwroot/douyin/php/config/buildadmin.php")
    _, o, _ = run("grep cors_request_domain /www/wwwroot/douyin/php/config/buildadmin.php")
    print("  CORS:", o.strip())
    run("systemctl restart php8.3-fpm")

    # 6. 验证
    print("\n[6/6] 验证")
    time.sleep(2)
    checks = [(f"https://{main_d}/", "主站"), (f"https://{main_d}/ai/Home.html", "AI站首页"),
              (f"https://{main_d}/home", "直播SPA"), (f"https://{main_d}/api/v1/feed/live?limit=1", "feed"),
              (f"https://{admin_d}/admin/", "后台")]
    if hls_d:
        checks.append((f"https://{hls_d}/hls/room/17/index.m3u8", "HLS"))
    for url, name in checks:
        _, o, _ = run(f"curl -s -o /dev/null -w '%{{http_code}}' --max-time 12 {url}")
        print(f"  {name:10s} {o.strip():5s} {url}")
    _, o, _ = run(f"curl -s --max-time 10 'https://{main_d}/api/v1/feed/live?limit=1' | python3 -c \"import sys,json;print(json.load(sys.stdin)['data']['list'][0]['play']['hls_url'])\" 2>/dev/null")
    print(f"\n  播放地址: {o.strip()}")
    print("  证书自动续期: systemctl status certbot.timer")
    SSH.close()


if __name__ == "__main__":
    main()
