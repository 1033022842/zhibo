# -*- coding: utf-8 -*-
"""全站 favicon 换成新图标：把页面里的 ./sugus-favicon.png 换为 ./sugus-favicon-v2.png

用新文件名而不是覆盖旧文件：nginx 对图片给了 7 天强缓存，浏览器对 favicon 缓存更顽固，
换 URL 才能让用户立刻看到新图标。按字节替换，避免非 UTF-8 页面被破坏；可重复执行。
"""
import io
import os

ROOT = r"d:\phpstudy_pro\WWW\douyin\ai-girl-malaysia.com"
OLD = b"./sugus-favicon.png"
NEW = b"./sugus-favicon-v2.png"

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

print("替换 favicon 的页面 %d 个：" % len(changed))
for n in changed:
    print("  -", n)
