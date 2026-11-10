#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""把已部署的 userInfo 限流 burst 从 15 调到 40（rate 保持 30r/m 不变）。

背景见 add_userinfo_ratelimit.py：30r/m + burst 15 是 2026-10-22 userInfo 死循环
事故的服务端兜底。前端单飞修好后，正常用户「多开几个标签页」的瞬时并发仍会撞到
burst 15 而拿到 429，表现为 token 显示 0 / 底栏 Studio 消失 / 抽屉头像空。

只放宽 burst（瞬时突发容量），rate 保持 30r/m：死循环 1 r/s 依然在 30 秒内被限住，
PHP-FPM 池仍然安全。

用法：
  python .zcode/tune_userinfo_burst.py --check
  python .zcode/tune_userinfo_burst.py --apply
"""
import argparse
import re
import sys

import paramiko

SITES = {
    "sugus":   ("172.81.98.55", "***REMOVED-SSH-PW***"),
    "heygirl": ("172.81.99.11", "***REMOVED-SSH-PW***"),
    "kelymi":  ("172.81.99.42", "***REMOVED-SSH-PW***"),
    "kisss":   ("103.217.252.52", "Aa1033022842"),
}

NEW_BURST = 40
BAK_DIR = "/root/nginx_bak"


def connect(host, pw, t=30):
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect(host, 22, "root", pw, timeout=t, banner_timeout=t, auth_timeout=t,
              look_for_keys=False, allow_agent=False)
    return c


def run(c, cmd, t=180):
    _, out, err = c.exec_command(cmd, timeout=t)
    return out.read().decode("utf-8", "replace").strip(), err.read().decode("utf-8", "replace").strip()


def check(name):
    host, pw = SITES[name]
    c = connect(host, pw)
    try:
        o, _ = run(c, "nginx -T 2>/dev/null | grep -n 'limit_req zone=userinfo'")
        print("  " + (o if o else "(未找到 userinfo 限流配置)"))
        return o
    finally:
        c.close()


def apply(name):
    host, pw = SITES[name]
    c = connect(host, pw)
    try:
        # 找出真正含该 location 的 vhost 文件
        o, _ = run(c, "grep -rl 'location = /api/live/userInfo' /etc/nginx/sites-enabled/ 2>/dev/null")
        files = [f.strip() for f in o.splitlines() if f.strip()]
        if not files:
            print("  ❌ 未找到含该 location 的 vhost")
            return False

        for vh in files:
            run(c, f"mkdir -p {BAK_DIR}")
            bak = f"{BAK_DIR}/{vh.split('/')[-1]}.bak.burst{NEW_BURST}"
            run(c, f"cp -a {vh} {bak}")

            # 只改 burst 数字，rate 与其余一字不动
            o, e = run(c, f"sed -i -E 's/(limit_req zone=userinfo burst=)[0-9]+/\\1{NEW_BURST}/' {vh}")
            if e:
                print(f"  !! sed: {e[:200]}")

        # nginx -t 通过才 reload，失败立即回滚
        o, e = run(c, "nginx -t 2>&1")
        ok = "successful" in (o + e).lower()
        print(f"  nginx -t: {'OK' if ok else 'FAIL'}")
        if not ok:
            print("  !! 配置有误，回滚")
            for vh in files:
                bak = f"{BAK_DIR}/{vh.split('/')[-1]}.bak.burst{NEW_BURST}"
                run(c, f"cp -a {bak} {vh}")
            print("  " + (o + e)[:400])
            return False

        o, e = run(c, "systemctl reload nginx 2>&1; echo RC=$?")
        print(f"  reload: {o[:120]}")

        o, _ = run(c, "nginx -T 2>/dev/null | grep 'limit_req zone=userinfo'")
        print(f"  生效: {o.strip()}")
        print(f"  备份: {bak}")
        return True
    finally:
        c.close()


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--check", action="store_true")
    ap.add_argument("--apply", action="store_true")
    ap.add_argument("--client")
    a = ap.parse_args()

    targets = [a.client] if a.client else list(SITES)
    if a.client and a.client not in SITES:
        sys.exit(f"未知站点: {a.client}")

    for n in targets:
        print(f"\n===== {n} ({SITES[n][0]}) =====", flush=True)
        try:
            if a.apply:
                apply(n)
            else:
                check(n)
        except Exception as ex:
            print(f"  ❌ {type(ex).__name__}: {ex}")


if __name__ == "__main__":
    main()
