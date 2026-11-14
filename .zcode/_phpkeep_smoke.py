#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""PHP 同步后的冒烟验证：主链路 HTTP 状态 + 关键 API 返回。

只读，不改任何东西。任一项失败即打印 FAIL，由调用方决定是否回滚。

用法: python .zcode/_phpkeep_smoke.py <site> [site...]
"""
import sys
import os

sys.path.insert(0, '.zcode')
import paramiko
import deploy_creds as creds

# (标签, URL 路径, 期望状态, 响应体必须包含的字符串或 None)
CHECKS = [
    ("首页",        "/ai/Home.html",            200, None),
    ("登录页",      "/ai/Login.html",           200, None),
    ("充值页",      "/ai/purchasePoints.html",  200, None),
    ("订阅页",      "/ai/Subscriptions.html",   200, None),
    ("AI 工作室",   "/ai/Image.html",           200, None),
    ("聊天页",      "/ai/Chat.html",            200, None),
    ("用户状态API", "/api/live/userInfo",       200, '"code"'),
    ("预设API",     "/api/aiShort/presets",     200, '"code"'),
    ("弹窗配置API", "/api/live/popupConfig",    200, '"code"'),
    ("站点配置API", "/api/live/siteConfig",    200, None),
]


def conn(name):
    if name == "sugus":
        jc = creds.site_dicts("heygirl")["heygirl"]
        jump = paramiko.SSHClient()
        jump.set_missing_host_key_policy(paramiko.AutoAddPolicy())
        jump.connect(jc["host"], jc["port"], "root", jc["password"], timeout=25,
                     look_for_keys=False, allow_agent=False)
        sg = creds.site_dicts("sugus")["sugus"]
        ch = jump.get_transport().open_channel(
            "direct-tcpip", (sg["host"], 22), ("127.0.0.1", 0))
        cli = paramiko.SSHClient()
        cli.set_missing_host_key_policy(paramiko.AutoAddPolicy())
        cli.connect(sg["host"], 22, "root", sg["password"], sock=ch, timeout=25,
                    look_for_keys=False, allow_agent=False)
        return cli, jump
    c = creds.conn(name)
    cli = paramiko.SSHClient()
    cli.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    cli.connect(c[0], c[1], c[2], c[3], timeout=25, look_for_keys=False, allow_agent=False)
    return cli, None


def run(name):
    cli, jump = conn(name)
    # 真实域名（server_name 匹配），只在本机 curl
    host = {"heygirl": "heygirl.live", "sugus": "sugus.ai",
            "kelymi": "kelymi.autos", "kiss": "kisss.ai"}[name]
    print(f"===== [{name}] 冒烟验证 =====")
    fails = []
    for label, path, want, must in CHECKS:
        cmd = (f"curl -s -m 20 -k -o /tmp/smoke.out -w '%{{http_code}}' "
               f"'https://{host}{path}'")
        if path.startswith("/api/"):
            cmd = (f"curl -s -m 20 -k -X POST -o /tmp/smoke.out -w '%{{http_code}}' "
                   f"'https://{host}{path}'")
        _, out, _ = cli.exec_command(cmd, timeout=40)
        code = out.read().decode().strip()
        body = ""
        if must:
            _, o2, _ = cli.exec_command("head -c 400 /tmp/smoke.out", timeout=20)
            body = o2.read().decode("utf-8", "replace")
        ok = (code == str(want)) and (must is None or must in body)
        print(f"  {'OK  ' if ok else 'FAIL'} {label:14} {code} {path}")
        if not ok:
            fails.append((label, code, body[:160]))
    cli.close()
    if jump:
        jump.close()
    return fails


def main():
    sites = sys.argv[1:] or ["heygirl"]
    allfail = {}
    for s in sites:
        f = run(s)
        if f:
            allfail[s] = f
        print()
    if allfail:
        print("!! 冒烟失败：")
        for s, items in allfail.items():
            for label, code, body in items:
                print(f"   [{s}] {label} -> {code} {body}")
        sys.exit(1)
    print("全部通过")


if __name__ == "__main__":
    main()
