# -*- coding: utf-8 -*-
"""把 js/shell-new.js（新版外壳渲染）挂到各页面上。

一、已有 ./js/main.js 或 ./js/site-shell.js 的页面：在它后面插一行脚本引用。
二、有旧侧边栏但没引上面两个脚本的页面（Chat / Gallery / create）：在 </head> 前补
    assets/fy.css（新版外壳样式所在）+ 脚本引用。
已经是新版外壳的页面（Subscriptions / tokens / taocan）跳过；已处理过的跳过。
按字节处理，避免非 UTF-8 页面被破坏。
"""
import io
import os
import re

ROOT = r"d:\phpstudy_pro\WWW\douyin\ai-girl-malaysia.com"
TAG = b'<script src="./js/shell-new.js?v=20260920"></script>'
CSS_LINK = b'<link rel="stylesheet" href="./assets/fy.css?v=20260920">\n\t\t'

MAIN_RE = re.compile(rb'<script src="\./js/main\.js[^"]*"></script>')
SHELL_RE = re.compile(rb'<script src="\./js/site-shell\.js[^"]*"></script>')

added, fallback, skipped, done = [], [], [], []

for name in sorted(os.listdir(ROOT)):
    if not name.lower().endswith(".html"):
        continue
    path = os.path.join(ROOT, name)
    if not os.path.isfile(path):
        continue
    raw = io.open(path, "rb").read()

    if b"new-shell-closed-sidebar" in raw:
        skipped.append(name + "  (已是新版外壳)")
        continue
    if b"js/shell-new.js" in raw:
        done.append(name + "  (已挂过)")
        continue

    m = MAIN_RE.search(raw) or SHELL_RE.search(raw)
    if m:
        out = raw[:m.end()] + b"\n\t\t" + TAG + raw[m.end():]
        io.open(path, "wb").write(out)
        added.append(name)
        continue

    # 兜底：有旧侧边栏但没引主脚本的页面
    if b'data-main-target="mainSidebarClosedContainer"' in raw and b"</head>" in raw:
        head = raw.index(b"</head>")
        out = raw[:head] + CSS_LINK + TAG + b"\n\t" + raw[head:]
        io.open(path, "wb").write(out)
        fallback.append(name)
        continue

    skipped.append(name + "  (无侧边栏/无主脚本)")

print("主脚本后插入 %d 个：" % len(added))
for n in added:
    print("  +", n)
print("兜底插入（补 fy.css）%d 个：" % len(fallback))
for n in fallback:
    print("  *", n)
print("跳过 %d 个：" % (len(skipped) + len(done)))
for n in skipped + done:
    print("  -", n)
