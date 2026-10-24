#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""从 APNIC delegated-extended 生成 CN v4+v6 的 nginx geo 名单（含自测）。

背景：旧方案 chnroutes.mmdb 只有 IPv4 数据，访客走 IPv6（手机蜂窝网）
时 geoip2 查不到 → 判不出 CN → 大陆封锁被绕过。改用纯 geo 名单（v4+v6）。

输入: /tmp/apnic.delegated   (ftp.apnic.net/stats/apnic/delegated-apnic-latest)
输出: /etc/nginx/cn_geo.conf (每行 "<CIDR> 1;")
"""
import ipaddress, sys

SRC = "/tmp/apnic.delegated"
DST = "/etc/nginx/cn_geo.conf"

nets = []
with open(SRC, encoding="utf-8", errors="replace") as f:
    for line in f:
        if not line.startswith("apnic|CN|ipv"):
            continue
        parts = line.strip().split("|")
        # apnic|CN|ipv4|start|count|date|status  /  apnic|CN|ipv6|start|prefixlen|...
        _, cc, typ, start, value, _date, status = parts[:7]
        if typ not in ("ipv4", "ipv6") or status not in ("allocated", "assigned"):
            continue
        first = ipaddress.ip_address(start)
        if typ == "ipv4":
            n = int(value)
            last = ipaddress.ip_address(int(first) + n - 1)
            nets.extend(ipaddress.summarize_address_range(first, last))
        else:
            nets.append(ipaddress.ip_network(f"{start}/{value}"))

nets = sorted(set(nets), key=lambda n: (n.version, int(n.network_address)))
with open(DST, "w") as f:
    for net in nets:
        f.write(f"{net} 1;\n")

# ---- 自测：典型 CN/非 CN 地址 ----
loaded = [ipaddress.ip_network(l.rsplit(" ", 1)[0]) for l in open(DST)]
def is_cn(ip):
    ip = ipaddress.ip_address(ip)
    return any(ip in n for n in loaded)
cn_cases = ["114.114.114.114", "1.2.4.0", "223.5.5.5", "2400:3200::1", "240e::9", "2408:84e8::1"]
non_cases = ["8.8.8.8", "1.1.1.1", "172.81.99.11", "2606:4700:4700::1111", "2a01:4f8::1"]
bad = [ip for ip in cn_cases if not is_cn(ip)] + [ip for ip in non_cases if is_cn(ip)]
v4 = sum(1 for n in loaded if n.version == 4)
v6 = sum(1 for n in loaded if n.version == 6)
print(f"CN v4 段: {v4}  v6 段: {v6}")
if bad:
    sys.exit(f"自测失败: {bad}")
print("自测通过: CN/非CN 样本分类全部正确")
