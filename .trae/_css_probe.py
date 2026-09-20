# -*- coding: utf-8 -*-
"""从（压缩的）CSS 包里按选择器提取规则（不截断）。
用法：python .trae/_css_probe.py <css文件> <选择器1> <选择器2> ... [--all]
     --all：连媒体查询里的同名规则也一起打印
"""
import io
import re
import sys

args = [a for a in sys.argv[1:] if a != "--all"]
show_all = "--all" in sys.argv
path, sels = args[0], args[1:]
css = io.open(path, encoding="utf-8", errors="replace").read()

for sel in sels:
    pat = re.escape(sel) + r"[^,{]*\{([^{}]*)\}"
    hits = re.findall(pat, css)
    if not hits:
        print("%s  ==>  MISSING" % sel)
        continue
    for h in (hits if show_all else hits[:1]):
        print("%s  ==>  {%s}" % (sel, h.strip()))
