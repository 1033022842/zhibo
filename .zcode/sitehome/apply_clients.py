# -*- coding: utf-8 -*-
"""站点首页可配：按 SOP 把「服务器侧改动」收敛成可重复执行的脚本

背景
----
后台「站点设置 → 站点首页」改了 `lp_platform_config.site_home_page` 后，公开入口
`/` 与 `/ai/` 需要落到站点根 `index.php` 再 302。nginx 侧改动原先是一次性手改的
（2026-10-26 sugus），按 SOP 要求收进仓库、可复现、可回滚。

做了什么（幂等）
----------------
1. 站点目录：确保 `.state/` 存在且 www-data 可写；上传仓库里的 `index.php`
2. `.user.ini`：open_basedir 必须含 /www/wwwroot/douyin/（旧值缺 /douyin → PHP 500）
3. nginx（公开 vhost）：`location = /` 内部转 `/ai/index.php`；`/ai/` index 顺序
   `index.php` 优先；新增 `location = /ai/index.php` 直跑站点目录的 index.php
4. nginx（回源 vhost，可选）：同样的 `location = /index.php`，只为本机排查
5. `nginx -t` 通过后 reload，并自检 `/`、`/ai/`、`/ai/Home.html`

用法
----
    set SITEHOME_SSH_HOST=172.81.98.55
    set SITEHOME_SSH_USER=root
    set SITEHOME_SSH_PASS=***
    python .zcode/sitehome/apply_clients.py --site sugus --dry-run
    python .zcode/sitehome/apply_clients.py --site sugus

前提：服务器上已经部署 `php/app/admin/controller/live/SiteHome.php`、
`php/app/api/controller/Live.php`、`php/route/live.php`（按 SOP 的 php 发布流程）。
"""
from __future__ import annotations

import argparse
import os
import posixpath
import sys
import time

import paramiko

REPO = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

# 每个站点的路径约定（新增客户只加一行）
SITES = {
    "sugus": {
        "site_dir": "/www/wwwroot/douyin/ai-girl-malaysia.com",
        "repo_site_dir": "ai-girl-malaysia.com",
        "public_conf": "/etc/nginx/sites-enabled/douyin",
        "origin_conf": "/etc/nginx/sites-enabled/douyin-8082",
        "origin_port": 8082,
        "public_host": "sugus.live",
    },
}

PHP_LOCATION = """    # 首页入口：后台「站点设置 → 站点首页」决定 / 与 /ai/ 落到哪个页面
    location = /ai/index.php {{
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME {site_dir}/index.php;
        fastcgi_param SCRIPT_NAME /ai/index.php;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }}

    # ai 静态站（登录页等）
    location ^~ /ai/ {{"""

ORIGIN_PHP = """    # 首页入口（回源口自测用，公开入口见 douyin vhost）
    location = /index.php {{
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME {site_dir}/index.php;
        fastcgi_param SCRIPT_NAME /index.php;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }}

    location / {{"""


def log(msg):
    print(msg, flush=True)


def connect(host, user, password):
    cli = paramiko.SSHClient()
    cli.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    cli.connect(host, 22, user, password, timeout=30, look_for_keys=False, allow_agent=False)
    return cli


def sh(cli, cmd, timeout=120):
    _i, o, e = cli.exec_command(cmd, timeout=timeout)
    return o.read().decode("utf-8", "replace"), e.read().decode("utf-8", "replace")


def ensure_state_dir(cli, cfg, dry):
    site = cfg["site_dir"]
    out, _ = sh(cli, f"mkdir -p {site}/.state && chown www-data:www-data {site}/.state && "
                     f"chmod 755 {site}/.state && ls -ld {site}/.state")
    log("  .state: " + out.strip())


def upload_index_php(cli, cfg, dry):
    local = os.path.join(REPO, cfg["repo_site_dir"], "index.php")
    if not os.path.isfile(local):
        raise SystemExit(f"仓库里没有 {local}")
    remote = posixpath.join(cfg["site_dir"], "index.php")
    if dry:
        log(f"  [dry] 会覆盖 {remote}")
        return
    sh(cli, f"test -f {remote} && cp -n {remote} {remote}.bak_sitehome || true")
    sftp = cli.open_sftp()
    sftp.put(local, remote)
    sftp.close()
    out, _ = sh(cli, f"php -l {remote}")
    log("  index.php: " + out.strip())


def fix_user_ini(cli, cfg, dry):
    site = cfg["site_dir"]
    ini = posixpath.join(site, ".user.ini")
    cur, _ = sh(cli, f"cat {ini} 2>/dev/null")
    if "/www/wwwroot/douyin/" in cur:
        log("  .user.ini: 已包含 /www/wwwroot/douyin/，跳过")
        return
    log(f"  .user.ini: 旧值 -> {cur.strip() or '(空)'}")
    if dry:
        log("  [dry] 会改写 .user.ini 并 reload php-fpm")
        return
    sh(cli, f"cp -n {ini} {ini}.bak_sitehome 2>/dev/null || true")
    sh(cli, f"printf 'open_basedir=/www/wwwroot/douyin/:/tmp/\\n' > {ini}")
    sh(cli, "systemctl reload php8.3-fpm")
    log("  .user.ini: 已改为 /www/wwwroot/douyin/:/tmp/ 并 reload php-fpm")


def patch_conf(cli, path, cfg, kind, dry):
    """kind: public | origin"""
    cur, _ = sh(cli, f"cat {path} 2>/dev/null")
    if not cur:
        log(f"  {path}: 不存在，跳过")
        return False

    notes = []
    if kind == "public":
        if "location = /ai/index.php" in cur:
            notes.append("已接线")
        new = cur
        if "rewrite ^ /ai/Home.html redirect;" in new:
            new = new.replace("location = / { rewrite ^ /ai/Home.html redirect; }",
                              "location = / { rewrite ^ /ai/index.php last; }")
            notes.append("根路径")
        if "        index Login.html index.html;" in new:
            new = new.replace("        index Login.html index.html;", "        index index.php index.html;")
            notes.append("ai index 顺序")
        if "location = /ai/index.php" not in new:
            anchor = "    # ai 静态站（登录页等）\n    location ^~ /ai/ {"
            if anchor not in new:
                log("  !! 找不到 /ai/ 的 location 锚点，需人工处理")
                return False
            new = new.replace(anchor, PHP_LOCATION.format(site_dir=cfg["site_dir"]), 1)
            notes.append("新增 /ai/index.php")
    else:
        if "location = /index.php" in cur:
            notes.append("已接线")
        new = cur
        if "    index index.html;" in new:
            new = new.replace("    index index.html;", "    index index.php index.html;", 1)
            notes.append("index 顺序")
        if "location = /index.php" not in new:
            anchor = "    location / {"
            if anchor not in new:
                log("  !! 找不到 location / 锚点，需人工处理")
                return False
            new = new.replace(anchor, ORIGIN_PHP.format(site_dir=cfg["site_dir"]), 1)
            notes.append("新增 /index.php")

    if new == cur:
        log(f"  {path}: 无需改动")
        return False
    if dry:
        log(f"  [dry] {path}: 会应用 {', '.join(notes)}")
        return True
    sh(cli, f"cp -n {path} /root/nginx_bak/sitehome_{time.strftime('%Y%m%d')}/$(basename {path}).old 2>/dev/null || true")
    sftp = cli.open_sftp()
    import io
    sftp.putfo(io.BytesIO(new.encode("utf-8")), path)
    sftp.close()
    log(f"  {path}: 已应用 {', '.join(notes)}")
    return True


def check_bak_files(cli):
    out, _ = sh(cli, "ls /etc/nginx/sites-enabled/ | grep -i bak || true")
    if out.strip():
        raise SystemExit(
            "sites-enabled 里还有备份文件，nginx 会 include 它们（2026-10-26 就是这么把 reload 搞挂的）：\n"
            + out.strip()
            + "\n先把它们挪到 /root/nginx_bak/ 再跑本脚本。"
        )


def verify(cli, cfg):
    site_dir = cfg["site_dir"]
    log("  自检：")
    out, _ = sh(cli, f"curl -sI -H 'Host: {cfg['public_host']}' http://127.0.0.1/ | grep -i 'HTTP/\\|^location'")
    log("    /      -> " + out.strip().replace("\n", " | "))
    out, _ = sh(cli, f"curl -sI -H 'Host: {cfg['public_host']}' http://127.0.0.1/ai/ | grep -i 'HTTP/\\|^location'")
    log("    /ai/   -> " + out.strip().replace("\n", " | "))
    out, _ = sh(cli, f"curl -s -o /dev/null -w '%{{http_code}} %{{size_download}}' -H 'Host: {cfg['public_host']}' http://127.0.0.1/ai/Home.html")
    log("    Home.html -> " + out.strip())
    out, _ = sh(cli, f"cat {site_dir}/.state/home.json 2>/dev/null || echo '(未设置，默认 Home.html)'")
    log("    状态文件: " + out.strip())


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--site", default="sugus", choices=sorted(SITES))
    ap.add_argument("--host", default=os.environ.get("SITEHOME_SSH_HOST", ""))
    ap.add_argument("--user", default=os.environ.get("SITEHOME_SSH_USER", "root"))
    ap.add_argument("--password", default=os.environ.get("SITEHOME_SSH_PASS", ""))
    ap.add_argument("--dry-run", action="store_true")
    args = ap.parse_args()

    if not args.host or not args.password:
        raise SystemExit("需要 --host/--password 或 SITEHOME_SSH_HOST/SITEHOME_SSH_PASS")

    cfg = SITES[args.site]
    log(f"== {args.site}: {cfg['site_dir']}  (dry-run={args.dry_run})")
    cli = connect(args.host, args.user, args.password)
    try:
        check_bak_files(cli)
        ensure_state_dir(cli, cfg, args.dry_run)
        upload_index_php(cli, cfg, args.dry_run)
        fix_user_ini(cli, cfg, args.dry_run)
        changed = False
        changed |= patch_conf(cli, cfg["public_conf"], cfg, "public", args.dry_run)
        changed |= patch_conf(cli, cfg["origin_conf"], cfg, "origin", args.dry_run)

        if args.dry_run:
            log("dry-run 结束")
            return
        if changed:
            out, err = sh(cli, "nginx -t 2>&1 | tail -2")
            log("  nginx -t: " + out.strip())
            if "successful" not in out:
                raise SystemExit("nginx 配置检查失败，已中止：" + err[:300])
            sh(cli, "systemctl reload nginx")
            log("  nginx reloaded")
        verify(cli, cfg)
    finally:
        cli.close()


if __name__ == "__main__":
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
    main()
