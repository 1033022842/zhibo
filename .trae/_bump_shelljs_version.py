# -*- coding: utf-8 -*-
"""js/shell-new.js 内容有改动，把页面里的引用版本号统一提升到新值（避免浏览器继续用旧缓存）。

不管页面当前是什么版本号都统一改成 NEW_VER；按字节替换，避免非 UTF-8 页面被破坏；可重复执行。
"""
import io
import os
import re

ROOT = r"d:\phpstudy_pro\WWW\douyin\ai-girl-malaysia.com"
PAT = re.compile(rb"js/shell-new\.js\?v=[0-9a-zA-Z]+")
NEW_VER = b"js/shell-new.js?v=20260930i"

changed = []
for name in sorted(os.listdir(ROOT)):
    if not name.lower().endswith(".html"):
        continue
    path = os.path.join(ROOT, name)
    if not os.path.isfile(path):
        continue
    raw = io.open(path, "rb").read()
    out = PAT.sub(NEW_VER, raw)
    if out != raw:
        io.open(path, "wb").write(out)
        changed.append(name)

print("更新 shell-new.js 版本号的页面 %d 个：" % len(changed))
for n in changed:
    print("  -", n)
