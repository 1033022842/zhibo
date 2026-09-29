# -*- coding: utf-8 -*-
"""补齐手机端图标（标签/收藏/添加到主屏幕）

1) 已有本地 favicon 的页面：在 rel=icon 那行后面补一条 apple-touch-icon（iOS 收藏/桌面图标靠它）
2) 原先用 candy.ai 外链的 4 个页面（invite/settings/taocan/tokens）：
   - rel=icon 的 candy.ai 图 → 本地 ./sugus-favicon-v3.png
   - 12 条 candy.ai 的 apple-touch-icon → 只留一条本地 180x180
   - manifest 的 https://candy.ai/manifest.json → 本地 ./site.webmanifest

按字节处理，避免非 UTF-8 页面被破坏；可重复执行。
"""
import io
import os
import re

ROOT = r"d:\phpstudy_pro\WWW\douyin\ai-girl-malaysia.com"

ICON_LINE = b'<link rel="icon" type="image/png" href="./sugus-favicon-v3.png">'
AT_LINK = b'<link rel="apple-touch-icon" sizes="180x180" href="./apple-touch-icon-v3.png">'
AT_MARK = b"apple-touch-icon-v3.png"

CANDY_ICON_RE = re.compile(rb"https://candy\.ai/assets/favico-[0-9a-f]+\.png")
CANDY_APPLE = b"candy.ai/assets/pwa/icons/apple/"
CANDY_MANIFEST = b"https://candy.ai/manifest.json"
LOCAL_MANIFEST_OLD = b"./site.webmanifest"
LOCAL_MANIFEST_NEW = b"./site-manifest.json"
XICON_BAD = b'type="image/x-icon" href="./sugus-favicon-v3.png"'
XICON_OK = b'type="image/png" href="./sugus-favicon-v3.png"'
APP_TITLE_MARK = b"apple-mobile-web-app-title"
APP_TITLE_LINE = b'<meta name="apple-mobile-web-app-title" content="Sugus.ai">'
CANDY_APP_TITLE = b'<meta name="apple-mobile-web-app-title" content="Candy.ai">'


def indent_of(line: bytes) -> bytes:
    return line[: len(line) - len(line.lstrip())]


changed = []
for name in sorted(os.listdir(ROOT)):
    if not name.lower().endswith(".html"):
        continue
    path = os.path.join(ROOT, name)
    if not os.path.isfile(path):
        continue
    raw = io.open(path, "rb").read()
    out = raw
    notes = []

    # 1) 去掉 candy.ai 外链图标 / manifest
    if CANDY_ICON_RE.search(out):
        out = CANDY_ICON_RE.sub(b"./sugus-favicon-v3.png", out)
        notes.append("icon")
    if XICON_BAD in out:
        out = out.replace(XICON_BAD, XICON_OK)
        notes.append("icon-type")
    if CANDY_MANIFEST in out:
        out = out.replace(CANDY_MANIFEST, LOCAL_MANIFEST_NEW)
        notes.append("manifest")
    if LOCAL_MANIFEST_OLD in out:
        # nginx 不认 .webmanifest 的 MIME（会按 application/octet-stream 返回，Chrome 拒收），改用 .json
        out = out.replace(LOCAL_MANIFEST_OLD, LOCAL_MANIFEST_NEW)
        notes.append("manifest-name")
    if CANDY_APP_TITLE in out:
        # 添加到主屏幕后图标下面的名字
        out = out.replace(CANDY_APP_TITLE, APP_TITLE_LINE)
        notes.append("app-title")
    if CANDY_APPLE in out:
        lines = out.split(b"\n")
        kept = []
        inserted = False
        for line in lines:
            if CANDY_APPLE in line:
                if not inserted:
                    kept.append(indent_of(line) + AT_LINK)
                    inserted = True
                continue
            kept.append(line)
        out = b"\n".join(kept)
        notes.append("apple-touch(%d 条并 1 条)" % (len(lines) - len(kept) + 1))

    # 2) 有本地 favicon 的页面补 apple-touch-icon
    if ICON_LINE in out and AT_MARK not in out:
        lines = out.split(b"\n")
        for i, line in enumerate(lines):
            if ICON_LINE in line:
                lines.insert(i + 1, indent_of(line) + AT_LINK)
                break
        out = b"\n".join(lines)
        notes.append("+apple-touch")

    # 3) 主屏快捷方式名字（iOS 从这条 meta 取，缺省会用很长的页面标题）
    if AT_MARK in out and APP_TITLE_MARK not in out:
        lines = out.split(b"\n")
        for i, line in enumerate(lines):
            if AT_MARK in line:
                lines.insert(i + 1, indent_of(line) + APP_TITLE_LINE)
                break
        out = b"\n".join(lines)
        notes.append("+app-title")

    if out != raw:
        io.open(path, "wb").write(out)
        changed.append((name, ",".join(notes)))

print("改动页面 %d 个：" % len(changed))
for n, why in changed:
    print("  - %-28s %s" % (n, why))
