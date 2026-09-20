# -*- coding: utf-8 -*-
"""给改动过的静态资源加/更新缓存版本号，避免浏览器继续用旧缓存。

- ?v=20260920   -> ?v=20260921b   （js/shell-new.js）
- ?v=20260918b  -> ?v=20260921b   （assets/fy.css、js/main.js 等）
- js/purchasePoints.js（无版本号）-> 补 ?v=20260921b

按字节处理，避免非 UTF-8 页面被破坏；可重复执行。
"""
import io
import os

ROOT = r"d:\phpstudy_pro\WWW\douyin\ai-girl-malaysia.com"
NEW = b"?v=20260921b"

REPL = [
    (b"?v=20260920", NEW),
    (b"?v=20260918b", NEW),
]
PLAIN = b'<script src="./js/purchasePoints.js"></script>'
PLAIN_NEW = b'<script src="./js/purchasePoints.js' + NEW + b'"></script>'

changed = []
for name in sorted(os.listdir(ROOT)):
    if not name.lower().endswith(".html"):
        continue
    path = os.path.join(ROOT, name)
    if not os.path.isfile(path):
        continue
    raw = io.open(path, "rb").read()
    out = raw
    for old, new in REPL:
        if old in out:
            out = out.replace(old, new)
    if PLAIN in out:
        out = out.replace(PLAIN, PLAIN_NEW)
    if out != raw:
        io.open(path, "wb").write(out)
        changed.append(name)

print("更新版本号的页面 %d 个：" % len(changed))
for n in changed:
    print("  -", n)
