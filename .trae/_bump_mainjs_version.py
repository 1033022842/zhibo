# -*- coding: utf-8 -*-
"""js/main.js 改了（新增 Google 登录），把页面里的引用版本号从 20260921b 升到 20260928a。

只改 js/main.js 这一个资源，其它资源版本号不动；按字节替换，避免非 UTF-8 页面被破坏；可重复执行。
"""
import io
import os

ROOT = r"d:\phpstudy_pro\WWW\douyin\ai-girl-malaysia.com"
OLD = b"js/main.js?v=20260921b"
NEW = b"js/main.js?v=20260928a"

changed = []
for name in sorted(os.listdir(ROOT)):
    if not name.lower().endswith(".html"):
        continue
    path = os.path.join(ROOT, name)
    if not os.path.isfile(path):
        continue
    raw = io.open(path, "rb").read()
    if OLD not in raw:
        continue
    io.open(path, "wb").write(raw.replace(OLD, NEW))
    changed.append(name)

print("更新 main.js 版本号的页面 %d 个：" % len(changed))
for n in changed:
    print("  -", n)
