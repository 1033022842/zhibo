# -*- coding: utf-8 -*-
"""从（压缩的）CSS 包里按选择器提取规则，用于照抄参考站的确切色值/尺寸。
用法：python .trae/_css_probe.py <css文件> <选择器1> <选择器2> ...
"""
import io
import re
import sys

path = sys.argv[1]
sels = sys.argv[2:]
css = io.open(path, encoding="utf-8", errors="replace").read()

for sel in sels:
    # 在压缩 CSS 中查找 ".selector{"
    pat = re.escape(sel) + r"\s*\{([^{}]*)\}"
    hits = re.findall(pat, css)
    if not hits:
        print("%-28s MISSING" % sel)
        continue
    for h in hits[:3]:
        print("%-28s {%s}" % (sel, h.strip()))
