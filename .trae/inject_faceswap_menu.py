# 一次性脚本：给 AI 女友端全站左侧菜单栏注入「Face Swap」菜单项
# 用法：python .trae/inject_faceswap_menu.py
import os
import re
import sys

try:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
except Exception:
    pass

ROOT = r"d:\phpstudy_pro\WWW\douyin\ai-girl-malaysia.com"

ICON = '<svg class="{cls}" viewBox="0 0 24 24" fill="none" stroke="#E75275" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8.5" r="4.5"/><circle cx="15" cy="15.5" r="4.5"/><path d="M7.6 7.9h.01M10.4 7.9h.01M8.2 10.6c.5.4 1.1.4 1.6 0"/></svg>'

COLLAPSED = (
    '<li class="relative w-full"><a href="./face_swap.html" title="Face Swap" class="relative h-[48px] w-[52px] '
    'hover:bg-zinc-700 rounded-[10px] border border-white border-opacity-10 justify-start px-3 items-center gap-2 '
    'flex mx-auto cursor-pointer">' + ICON.format(cls="w-6 h-6") + '</a></li>'
)
EXPANDED = (
    '<li class="relative w-full"><a href="./face_swap.html" class="relative h-[40px] w-full hover:bg-zinc-700 '
    'rounded-[10px] border border-white border-opacity-10 justify-start px-3 items-center gap-2 flex cursor-pointer">'
    + ICON.format(cls="w-4 h-4")
    + '<span class="text-start text-grey-medium text-xxs font-medium leading-4">Face Swap</span></a></li>'
)
DRAWER = (
    '<li class="relative w-full py-4 border-b border-white border-opacity-10"><a href="./face_swap.html" '
    'class="w-full flex justify-start items-center gap-2">'
    + ICON.format(cls="w-5 h-5")
    + '<span class="text-start text-grey-medium text-xs leading-5 font-semibold">Face Swap</span></a></li>'
)

LI_RE = re.compile(r"<li\b[^>]*>(?:(?!</li>).)*?</li>", re.DOTALL)


def indent_of(text, index, fallback="\t"):
    line_start = text.rfind("\n", 0, index) + 1
    prefix = text[line_start:index]
    stripped = prefix.lstrip()
    if stripped == "":
        return prefix
    return fallback


def inject(path):
    with open(path, "r", encoding="utf-8", errors="surrogateescape") as f:
        text = f.read()

    # 三个变体分别判重，支持重复执行（补漏）
    has = {
        "collapsed": 'title="Face Swap"' in text,
        "expanded": 'text-xxs font-medium leading-4">Face Swap<' in text,
        "drawer": 'text-xs leading-5 font-semibold">Face Swap<' in text,
    }
    if all(has.values()):
        return "skip(already)", {"collapsed": 0, "expanded": 0, "drawer": 0}

    inserts = []  # (pos, snippet)
    counts = {"collapsed": 0, "expanded": 0, "drawer": 0}

    for m in LI_RE.finditer(text):
        block = m.group(0)
        if "upload_media.html" not in block:
            continue

        if "w-[52px]" in block:
            variant, snippet = "collapsed", COLLAPSED
        elif "h-[40px]" in block:
            variant, snippet = "expanded", EXPANDED
        elif "w-5 h-5" in block:
            variant, snippet = "drawer", DRAWER
        else:
            continue

        if has[variant]:
            continue

        counts[variant] += 1
        indent = indent_of(text, m.start())
        inserts.append((m.end(), "\n" + indent + snippet))

    if not inserts:
        return "no-sidebar-match", counts

    for pos, snippet in reversed(inserts):
        text = text[:pos] + snippet + text[pos:]

    with open(path, "w", encoding="utf-8", errors="surrogateescape", newline="") as f:
        f.write(text)

    return "ok", counts


def main():
    files = sorted(
        os.path.join(ROOT, name)
        for name in os.listdir(ROOT)
        if name.lower().endswith(".html") and os.path.isfile(os.path.join(ROOT, name))
    )

    total = 0
    for path in files:
        status, counts = inject(path)
        name = os.path.basename(path)
        if status == "ok":
            total += 1
            print(f"{name}: injected collapsed={counts['collapsed']} expanded={counts['expanded']} drawer={counts['drawer']}")
        else:
            print(f"{name}: {status}")

    print(f"DONE, {total} files updated")


if __name__ == "__main__":
    main()
