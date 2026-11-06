#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""UptimeRobot 四站外部探测点一键创建（幂等，可重复跑）。

用法：
    python .zcode/uptimerobot_setup.py <MAIN_API_KEY>

- Key 获取：uptimerobot.com 登录 → Settings → API Keys → Main API Key（免费版 50 监控 / 5 分钟间隔）
- 幂等：先 getMonitors 拉现有监控，按 friendly_name 匹配，已存在跳过
- API 限速 10 req/min，脚本自带 7s 间隔，11 个监控约 80 秒建完
- kiss 当前（2026-11-06）宕机中，监控建好后 kiss 两条立即告警属预期

监控设计（区分「机器炸」vs「链路抖」）：
- 每站 3 条：主站 HTTPS（带关键词）+ 管理后台 HTTPS + SSH 端口探测
- 判读见 docs/外部探测-UptimeRobot监控.md
"""
import sys
import time
import urllib.parse
import urllib.request

API = "https://api.uptimerobot.com/v2"

# (friendly_name, type, target, keyword)
# type: http=1, keyword=2, port=4
MONITORS = [
    ("sugus-主站",   2, "http://172.81.98.55/ai/Home.html",  "Sugus"),
    ("sugus-后台",   1, "https://sugus.it.com/admin/",        None),
    ("sugus-SSH",    4, "172.81.98.55:22",                    None),
    ("heygirl-主站", 2, "https://heygirl.live/ai/Home.html",  "heygirl"),
    ("heygirl-后台", 1, "https://heygirl.sbs/admin/",         None),
    ("heygirl-SSH",  4, "172.81.99.11:22",                    None),
    ("kelymi-主站",  2, "https://kelymi.autos/ai/Home.html",  "Kelymi"),
    ("kelymi-后台",  1, "https://kelymi.autos/admin/",        None),
    ("kelymi-SSH",   4, "172.81.99.42:22",                    None),
    ("kiss-主站",    2, "https://kisss.ai/ai/Home.html",      "Kiss"),
    ("kiss-SSH",     4, "103.217.252.52:22",                  None),
]


def call(api_key, method, **params):
    params["api_key"] = api_key
    data = urllib.parse.urlencode(params).encode()
    req = urllib.request.Request(f"{API}/{method}", data=data, method="POST")
    # 本机网络需走代理时，Python 会读 HTTP(S)_PROXY 环境变量；没有代理则直连
    with urllib.request.urlopen(req, timeout=30) as r:
        body = r.read().decode("utf-8", "replace")
    import json
    j = json.loads(body)
    if j.get("stat") != "ok":
        raise RuntimeError(f"{method} -> {j.get('error', j)}")
    return j


def main():
    if len(sys.argv) < 2:
        print(__doc__)
        sys.exit(1)
    key = sys.argv[1]

    existing = {}
    j = call(key, "getMonitors", limit=50)
    for m in j.get("monitors", []):
        existing[m["friendly_name"]] = m["id"]
    print(f"现有监控 {len(existing)} 个：{sorted(existing)}")

    for name, mtype, target, keyword in MONITORS:
        if name in existing:
            print(f"= 跳过（已存在）{name} #{existing[name]}")
            continue
        params = {
            "friendly_name": name,
            "url": target,
            "interval": 300,          # 免费版最小 5 分钟
            "type": mtype,
        }
        if mtype == 2 and keyword:
            params["keyword_type"] = 1  # 1=存在关键词算正常
            params["keyword_value"] = keyword
        if mtype == 4:
            host, port = target.rsplit(":", 1)
            params["url"] = host
            params["port"] = port
            params["sub_type"] = 1      # 1=自定义 TCP 端口
        j = call(key, "newMonitor", **params)
        print(f"+ 已创建 {name} #{j['monitor']['id']} ({target})")
        time.sleep(7)  # 限速 10 req/min

    print("\n完成。判读口径见 docs/外部探测-UptimeRobot监控.md")


if __name__ == "__main__":
    main()
