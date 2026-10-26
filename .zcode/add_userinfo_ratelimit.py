#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""给四站 nginx 的 /api/live/userInfo 加限流（2026-10-22 userInfo 死循环事故兜底）。

背景：前端 shell-new.js 的 fetch 钩子会让 userInfo 自我触发成死循环（已修）。
即便代码修好，历史遗留的旧标签页仍会继续刷；且将来若再出现类似客户端 bug，
没有服务端兜底就会再次打垮 PHP-FPM 池。

策略：
  - nginx http 块加 `limit_req_zone $binary_remote_addr zone=userinfo:10m rate=30r/m`
  - vhost 内加 `location = /api/live/userInfo { limit_req zone=userinfo burst=15 nodelay; ... }`
  - 超限返回 429（前端已有 catch，静默失败不影响功能）
  - 正常用户远低于 30 r/m（页面加载 1 次 + 操作后若干次），不会误伤
  - 实测：300ms 间隔连发 40 次 → 22 通过 / 18 被 429

用法：
  python .zcode/add_userinfo_ratelimit.py --check
  python .zcode/add_userinfo_ratelimit.py --apply
  python .zcode/add_userinfo_ratelimit.py --rollback
"""
import argparse
import sys

import paramiko

SITES = {
    "sugus":   ("172.81.98.55", "0wJfy`50tJQzG/0X"),
    "heygirl": ("172.81.99.11", "mw-DGckAL,61fV00"),
    "kisss":   ("103.217.252.52", "Aa1033022842"),
    "kelymi":  ("172.81.99.42", "ffG2dAUGc{V572{j"),
}
ORDER = ["sugus", "heygirl", "kisss", "kelymi"]

ZONE = """# userInfo 限流：单 IP 30 r/m（0.5 次/秒）+ burst 15。
# ⚠️ 参数必须按真实故障速率校准：第一版设 5r/s，对 1 次/秒的循环完全无效
#    （浏览器后台标签限流后正好 1 次/秒）。30r/m 实测能拦住 300ms 间隔的循环。
# 2026-10-22 事故：前端 fetch 钩子自触发导致每标签页约 3.3 r/s 的 userInfo 死循环，
# 数个标签页即吃满 PHP-FPM 40 个 worker，customVideo/submit 排队到无响应。
# 前端已修（shell-new.js 排除 userInfo + 单飞），这里是服务端兜底：
# 即便将来再出现客户端 bug 或恶意刷，也不会打垮整个 FPM 池。
limit_req_zone $binary_remote_addr zone=userinfo:10m rate=30r/m;

"""

LOCATION = """    # userInfo 精确匹配 + 限流（见文件顶部 limit_req_zone 注释）
    location = /api/live/userInfo {
        limit_req zone=userinfo burst=15 nodelay;
        limit_req_status 429;
%s
    }

"""

# 各站 vhost 内的 /api/ location 有两种写法，缩进不同
VHOSTS = {
    "sugus":   ["/etc/nginx/sites-enabled/douyin"],
    "heygirl": ["/etc/nginx/sites-enabled/zhibo.conf"],
    "kisss":   ["/etc/nginx/sites-enabled/kiss.conf"],
    "kelymi":  ["/etc/nginx/sites-enabled/zhibo.conf"],
}
NGINX_CONF = "/etc/nginx/nginx.conf"
BODY_512 = "        root /www/wwwroot/douyin/php/public;\n        try_files $uri /index.php$uri$is_args$args;"


def connect(host, pw, t=30):
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect(host, 22, "root", pw, timeout=t, banner_timeout=t, auth_timeout=t,
              look_for_keys=False, allow_agent=False)
    return c


def run(c, cmd, t=120):
    stdin, out, err = c.exec_command(cmd, timeout=t)
    o = out.read().decode("utf-8", "replace")
    e = err.read().decode("utf-8", "replace")
    return out.channel.recv_exit_status(), o.strip(), e.strip()


def check_site(name):
    host, pw = SITES[name]
    c = connect(host, pw)
    try:
        info = {"site": name}
        # zone 可以在 nginx.conf 或任意被 http 块 include 的文件里（sugus 就加在 sites-enabled/douyin 顶部），
        # 所以以「实际生效的配置」为准
        rc, o, _ = run(c, "nginx -T 2>/dev/null | grep -c 'limit_req_zone.*userinfo' | head -1")
        info["zone"] = int((o or "0").split()[0] or 0) > 0
        rc, o, _ = run(c, "nginx -T 2>/dev/null | grep -c 'location = /api/live/userInfo' | head -1")
        info["loc"] = int((o or "0").split()[0] or 0) > 0
        info["http"] = "-"
        return info
    finally:
        c.close()


def apply_site(name):
    host, pw = SITES[name]
    c = connect(host, pw)
    try:
        sftp = c.open_sftp()
        # 1) nginx.conf 加 zone
        raw = sftp.open(NGINX_CONF, "rb").read()
        crlf = b"\r\n" in raw
        conf = raw.replace(b"\r\n", b"\n").decode("utf-8", "replace")
        if "limit_req_zone" in conf and "zone=userinfo" in conf:
            print("  [%s] zone 已存在" % name)
        else:
            # 插到 http { 之后
            idx = conf.find("http {")
            if idx < 0:
                print("  [%s] ❌ 找不到 http { 块" % name)
                return False
            conf = conf[:idx] + "http {\n\n" + ZONE + conf[idx + len("http {"):]
            out = conf.replace("\n", "\r\n").encode() if crlf else conf.encode()
            bk = "/root/nginx_bak/nginx.conf.bak.userinfo"
            run(c, "mkdir -p /root/nginx_bak")
            with sftp.open(bk, "wb") as f:
                f.write(raw)
            with sftp.open(NGINX_CONF, "wb") as f:
                f.write(out)
            print("  [%s] ✓ nginx.conf 加 limit_req_zone" % name)

        # 2) vhost 加精确 location
        for vh in VHOSTS[name]:
            raw = sftp.open(vh, "rb").read()
            crlf = b"\r\n" in raw
            v = raw.replace(b"\r\n", b"\n").decode("utf-8", "replace")
            if "location = /api/live/userInfo" in v:
                print("  [%s] %s location 已存在" % (name, vh.split("/")[-1]))
                continue
            anchor = "    location /api/ {"
            if anchor not in v:
                print("  [%s] ❌ %s 找不到 location /api/ 锚点" % (name, vh))
                return False
            v2 = v.replace(anchor, LOCATION % BODY_512 + anchor, 1)
            out = v2.replace("\n", "\r\n").encode() if crlf else v2.encode()
            # ⚠️ 备份绝不能放在 sites-enabled/ 下：nginx 会把 *.bak 也当配置加载，
            #    之前就因此触发 "duplicate default server" 导致 nginx -t 失败
            bk = "/root/nginx_bak/%s.bak.userinfo" % vh.split("/")[-1]
            run(c, "mkdir -p /root/nginx_bak")
            with sftp.open(bk, "wb") as f:
                f.write(raw)
            with sftp.open(vh, "wb") as f:
                f.write(out)
            print("  [%s] ✓ %s 加 userInfo 限流 location" % (name, vh.split("/")[-1]))
        sftp.close()

        rc, o, e = run(c, "nginx -t 2>&1 | tail -2", 90)
        print("   nginx -t: %s" % o.replace("\n", " | "))
        if "successful" not in o and "syntax is ok" not in o:
            print("  [%s] ❌ 配置校验失败，不 reload" % name)
            return False

        rc, o, _ = run(c, "nginx -s reload && sleep 2 && echo RELOADED", 60)
        print("   %s" % o)
        rc, o, _ = run(c, "nginx -T 2>/dev/null | grep -c 'limit_req_zone.*userinfo'")
        print("   zone 生效数: %s" % o)
        return True
    finally:
        c.close()


def rollback_site(name):
    host, pw = SITES[name]
    c = connect(host, pw)
    try:
        parts = ["/root/nginx_bak/nginx.conf.bak.userinfo:" + NGINX_CONF]
        parts += ["/root/nginx_bak/%s.bak.userinfo:%s" % (v.split("/")[-1], v) for v in VHOSTS[name]]
        cmds = []
        for pr in parts:
            src, dst = pr.split(":")
            cmds.append("[ -f \"%s\" ] && cp -a \"%s\" \"%s\" && echo restored %s" % (src, src, dst, dst))
        rc, o, _ = run(c, "; ".join(cmds))
        print("  [%s] %s" % (name, o or "无备份"))
        rc, o, _ = run(c, "nginx -t 2>&1 | tail -1 && nginx -s reload && echo reloaded", 90)
        print("   %s" % o.replace("\n", " | "))
    finally:
        c.close()


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--check", action="store_true")
    ap.add_argument("--apply", action="store_true")
    ap.add_argument("--rollback", action="store_true")
    ap.add_argument("--site", choices=list(SITES))
    args = ap.parse_args()
    targets = [args.site] if args.site else ORDER

    if args.apply:
        ok = True
        for s in targets:
            print("=" * 60)
            print("[%s] %s" % (s, SITES[s][0]))
            try:
                ok = apply_site(s) and ok
            except Exception as ex:
                print("  ❌ %s" % ex)
                ok = False
        return 0 if ok else 1

    if args.rollback:
        for s in targets:
            rollback_site(s)
        return 0

    print("%-8s %-8s %-8s %s" % ("SITE", "zone", "location", "userInfo(HTTP)"))
    for s in targets:
        i = check_site(s)
        print("%-8s %-8s %-8s %s" % (s,
                                     "✓" if i.get("zone") else "✗",
                                     "✓" if i.get("loc") else "✗",
                                     i.get("http", "?")))
    return 0


if __name__ == "__main__":
    sys.exit(main())
