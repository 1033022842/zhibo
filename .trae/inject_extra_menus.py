# 一次性脚本：给 AI 女友端全站左侧菜单栏注入「Shorts / Posts / Private Content」三项
# 锚点：含 shop.html 的那个 <li>（插在 Shop 后面）
# 同时支持把某一页的选中态切到它自己的菜单项上
#
# 用法：python .trae/inject_extra_menus.py
import os
import re
import sys

try:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
except Exception:
    pass

ROOT = r"d:\phpstudy_pro\WWW\douyin\ai-girl-malaysia.com"

ICON_STYLE = 'fill="none" stroke="#E75275" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"'

ICONS = {
    "shorts": '<svg class="{cls}" viewBox="0 0 24 24" ' + ICON_STYLE +
              '><rect x="3" y="4" width="18" height="16" rx="4"/><path d="M10.6 9.3v5.4l4.4-2.7z"/></svg>',
    "posts": '<svg class="{cls}" viewBox="0 0 24 24" ' + ICON_STYLE +
             '><rect x="3" y="4" width="18" height="16" rx="3"/><circle cx="8.8" cy="9.8" r="1.5"/>' +
             '<path d="M4.2 18.2l4.6-4.6 3.6 3.6 2.8-2.4 4.6 3.4"/></svg>',
    "private": '<svg class="{cls}" viewBox="0 0 24 24" ' + ICON_STYLE +
               '><rect x="4" y="10" width="16" height="10" rx="2.5"/><path d="M8 10V7.4a4 4 0 0 1 8 0V10"/></svg>',
}

# key, href, 显示名
ITEMS = [
    ("shorts", "./shorts.html", "Shorts"),
    ("posts", "./posts.html", "Posts"),
    ("private", "./private_content.html", "Private Content"),
]

# 需要把选中态切到自己菜单项的页面 -> 自己的 href
PAGE_ACTIVE = {
    "shorts.html": "./shorts.html",
    "posts.html": "./posts.html",
    "private_content.html": "./private_content.html",
}

LI_RE = re.compile(r"<li\b[^>]*>(?:(?!</li>).)*?</li>", re.DOTALL)


def indent_of(text, index, fallback="\t"):
    line_start = text.rfind("\n", 0, index) + 1
    prefix = text[line_start:index]
    if prefix.strip() == "":
        return prefix
    return fallback


def build_li(key, href, label, variant):
    icon = ICONS[key].format(cls={"collapsed": "w-6 h-6", "expanded": "w-4 h-4", "drawer": "w-5 h-5"}[variant])
    if variant == "collapsed":
        return ('<li class="relative w-full"><a href="%s" title="%s" class="relative h-[48px] w-[52px] '
                'hover:bg-zinc-700 rounded-[10px] border border-white border-opacity-10 justify-start px-3 '
                'items-center gap-2 flex mx-auto cursor-pointer">%s</a></li>' % (href, label, icon))
    if variant == "expanded":
        return ('<li class="relative w-full"><a href="%s" class="relative h-[40px] w-full hover:bg-zinc-700 '
                'rounded-[10px] border border-white border-opacity-10 justify-start px-3 items-center gap-2 '
                'flex cursor-pointer">%s<span class="text-start text-grey-medium text-xxs font-medium '
                'leading-4">%s</span></a></li>' % (href, icon, label))
    return ('<li class="relative w-full py-4 border-b border-white border-opacity-10"><a href="%s" '
            'class="w-full flex justify-start items-center gap-2">%s<span class="text-start text-grey-medium '
            'text-xs leading-5 font-semibold">%s</span></a></li>' % (href, icon, label))


def inject_text(text):
    """插入三个菜单项，返回 (新文本, 是否改动过)"""
    if all(('href="%s"' % href) in text for _, href, _ in ITEMS):
        return text, False

    inserts = []
    for m in LI_RE.finditer(text):
        block = m.group(0)
        if 'href="./shop.html"' not in block:
            continue

        if "w-[52px]" in block:
            variant = "collapsed"
        elif "h-[40px]" in block:
            variant = "expanded"
        elif "w-5 h-5" in block:
            variant = "drawer"
        else:
            continue

        snippets = []
        for key, href, label in ITEMS:
            if ('href="%s"' % href) in text:
                continue
            snippets.append(build_li(key, href, label, variant))
        if not snippets:
            continue

        indent = indent_of(text, m.start())
        inserts.append((m.end(), "".join("\n" + indent + s for s in snippets)))

    if not inserts:
        return text, False

    for pos, snippet in reversed(inserts):
        text = text[:pos] + snippet + text[pos:]

    return text, True


def set_active(text, active_href):
    """把侧边栏的选中态统一切到 active_href 对应的菜单项上"""

    def fix(m):
        block = m.group(0)
        if 'href="./' not in block and 'href="mailto:' not in block:
            return block

        # 先清掉所有选中态标记
        block = block.replace("hover:bg-zinc-700 bg-[#303030] rounded-[10px]",
                              "hover:bg-zinc-700 rounded-[10px]")
        block = block.replace("text-white text-xxs font-medium leading-4",
                              "text-grey-medium text-xxs font-medium leading-4")
        block = block.replace("text-white text-xs leading-5 font-semibold",
                              "text-grey-medium text-xs leading-5 font-semibold")

        target = 'href="%s"' % active_href
        if target in block:
            if "hover:bg-zinc-700 " in block and "bg-[#303030]" not in block:
                block = block.replace("hover:bg-zinc-700 ", "hover:bg-zinc-700 bg-[#303030] ", 1)
            block = block.replace("text-grey-medium text-xxs font-medium leading-4",
                                  "text-white text-xxs font-medium leading-4")
            block = block.replace("text-grey-medium text-xs leading-5 font-semibold",
                                  "text-white text-xs leading-5 font-semibold")
        return block

    return LI_RE.sub(fix, text)


def process(path, active_href=None):
    with open(path, "r", encoding="utf-8", errors="surrogateescape") as f:
        text = f.read()

    new_text, changed = inject_text(text)
    if active_href:
        active_text = set_active(new_text, active_href)
        if active_text != new_text:
            changed = True
            new_text = active_text

    if not changed:
        return "skip", 0

    with open(path, "w", encoding="utf-8", errors="surrogateescape", newline="") as f:
        f.write(new_text)
    return "ok", len(new_text)


def main():
    files = sorted(
        os.path.join(ROOT, n) for n in os.listdir(ROOT)
        if n.lower().endswith(".html") and os.path.isfile(os.path.join(ROOT, n))
    )
    done = skip = 0
    for path in files:
        name = os.path.basename(path)
        status, size = process(path, PAGE_ACTIVE.get(name))
        if status == "ok":
            done += 1
            print("%-40s updated (%d bytes)" % (name, size))
        else:
            skip += 1
            print("%-40s %s" % (name, status))
    print("DONE: %d updated, %d skipped" % (done, skip))


if __name__ == "__main__":
    main()
