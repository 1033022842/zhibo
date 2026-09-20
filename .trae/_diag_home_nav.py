# -*- coding: utf-8 -*-
"""诊断 Home.html 顶栏的 div 嵌套：定位导航栏 div 与 #user-setting 的真实归属。
只读，不改文件。
"""
import io
import os
import re

HOME = r"d:\phpstudy_pro\WWW\douyin\ai-girl-malaysia.com\Home.html"
html = io.open(HOME, encoding="utf-8").read()
lines = html.split("\n")

starts = [m.start() for m in re.finditer(r"<div\b", html)]
ends = [m.start() for m in re.finditer(r"</div>", html)]
tokens = sorted([(p, "open") for p in starts] + [(p, "close") for p in ends])

depth = 0
stack = []
for pos, kind in tokens:
    ln = html.count("\n", 0, pos) + 1
    if kind == "open":
        depth += 1
        snippet = re.sub(r"\s+", " ", html[pos:pos + 110])
        stack.append((ln, snippet))
    else:
        depth -= 1
        opened = stack.pop() if stack else (None, "?")
        if 380 <= ln <= 460:
            print("close line %-5d depth=%-2d  ->  matched open line %s :: %s" % (ln, depth, opened[0], opened[1][:90]))
