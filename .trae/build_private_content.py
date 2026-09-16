# -*- coding: utf-8 -*-
"""组装 ai-girl-malaysia.com/private_content.html

以 face_swap.html 的「站点外壳」（三种侧边栏 + 顶栏 + 移动端导航 + 登录态脚本）为基底，
把中间的主内容整块换成 candy.ai /private-content 页的复刻片段（下方 FRAGMENT），
确保侧边栏 / 顶栏与全站其它页面 100% 一致。

页面内容依据：.trae/archive/private-content.html（存自 https://candy.ai/private-content）
    · class 逐字保留，动态部分（搜索框展开、标签切换、卡片列表）由 js/private_content.js 处理
    · 卡片 DOM 见 .trae/_private_templates.html（本脚本 FRAGMENT 只放静态部分）

用法：python .trae/build_private_content.py
"""
import os
import sys

try:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
except Exception:
    pass

ROOT = r"d:\phpstudy_pro\WWW\douyin"
SITE = os.path.join(ROOT, "ai-girl-malaysia.com")
BASE = os.path.join(SITE, "face_swap.html")
OUT = os.path.join(SITE, "private_content.html")

MAIN_MARK = "\t\t<!-- Main Content -->"
NAV_MARK = "\t\t<!-- Mobile Bottom Navigation -->"

# 原站 private-content 页引用的构建（与 shop 页同一份 hash），站点里有两个副本
CSS_FILE = "application-c9a80e6102195b41b493002bf9a0ab06ceb43fb6bcbf45cf6c1a4fd0f3b29ad1.css"
CSS_PRIVATE = "./private-content_files/" + CSS_FILE
CSS_SHOP = "./shop_files/" + CSS_FILE
# 校验用：① 站点外壳侧边栏需要的类 ② 本页需要的类（Tailwind 转义后的选择器）
CSS_REQUIRED = (
    ".bg-main{",
    "text-grey-medium",
    "webkit-transition-sidebar",
    "bg-black-default",
    r"aspect-\[9\/16\]",
    r"blur-\[7px\]",
    "line-clamp-2",
    "rounded-2xl",
    r"ring-white\/40",
)

# ---------------------------------------------------------------------------
# 主内容片段：candy.ai /private-content 复刻（class 逐字来自存档页）
# 与原站的两处结构调整（见文末报告）：
#   1) <main> 用 pt-16 顶出固定顶栏（64px）、lg:ml-[100px] 顶出收起态侧边栏（100px），
#      且不再套用 shop 页那种 pt-16 / lg:pl-[100px] 外壳包装层，避免双重偏移；
#   2) 卡片列表、搜索框展开、标签切换交给 js/private_content.js 渲染。
# ---------------------------------------------------------------------------
FRAGMENT = """\t\t<!-- Main Content -->
\t\t<div class="main-content-container" data-main-target="contentContainer">
\t\t\t<main class="ml-0 lg:ml-[100px] pt-16 h-full pb-[calc(70px+env(safe-area-inset-bottom)+1rem)] lg:pb-8">
\t\t\t\t<div id="private-content-buy-all" class="empty:hidden"></div>

\t\t\t\t<div class="px-4 py-4 md:py-6">
\t\t\t\t\t<div class="mx-auto max-w-7xl">
\t\t\t\t\t\t<!-- Character search + filter tabs -->
\t\t\t\t\t\t<div class="flex items-center gap-2 mb-5 lg:mb-7">
\t\t\t\t\t\t\t<div id="private-content-search" class="flex items-center shrink-0 h-9 rounded-full border transition-colors border-white/15 bg-white/5">
\t\t\t\t\t\t\t\t<button type="button" id="private-content-search-toggle" class="w-9 h-9 flex items-center justify-center cursor-pointer text-white/80 hover:text-white focus:outline-none" aria-label="Search">
\t\t\t\t\t\t\t\t\t<svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true">
\t\t\t\t\t\t\t\t\t\t<circle cx="11" cy="11" r="7"></circle>
\t\t\t\t\t\t\t\t\t\t<line x1="16.5" y1="16.5" x2="21" y2="21"></line>
\t\t\t\t\t\t\t\t\t</svg>
\t\t\t\t\t\t\t\t</button>

\t\t\t\t\t\t\t\t<input type="text" id="private-content-search-input" value="" placeholder="Search" autocomplete="off" enterkeyhint="search" class="appearance-none bg-transparent border-0 outline-none focus:outline-none focus:ring-0 min-w-0 p-0 text-white text-sm font-medium placeholder-white/40 transition-all duration-300 w-0">

\t\t\t\t\t\t\t\t<button type="button" id="private-content-search-clear" class="w-7 h-9 items-center justify-center cursor-pointer text-white/50 hover:text-white shrink-0 hidden" aria-label="Clear search">
\t\t\t\t\t\t\t\t\t<svg viewBox="0 0 24 24" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true">
\t\t\t\t\t\t\t\t\t\t<line x1="6" y1="6" x2="18" y2="18"></line>
\t\t\t\t\t\t\t\t\t\t<line x1="18" y1="6" x2="6" y2="18"></line>
\t\t\t\t\t\t\t\t\t</svg>
\t\t\t\t\t\t\t\t</button>
\t\t\t\t\t\t\t</div>

\t\t\t\t\t\t\t<div id="private-content-lobby-tabs" class="flex items-center gap-2 overflow-x-auto no-scrollbar min-w-0 min-h-9"><a data-tab="all" class="shrink-0 h-9 px-4 inline-flex items-center rounded-full text-xs md:text-sm font-semibold transition-colors border bg-white text-black border-white" href="./private_content.html?tab=all">All</a>
\t\t\t\t\t\t\t\t<a data-tab="most_liked" class="shrink-0 h-9 px-4 inline-flex items-center rounded-full text-xs md:text-sm font-semibold transition-colors border bg-white/5 text-white/80 border-white/15 hover:bg-white/10" href="./private_content.html?tab=most_liked">Most liked</a>
\t\t\t\t\t\t\t</div>
\t\t\t\t\t\t</div>

\t\t\t\t\t\t<div id="private-content-lobby-content"></div>

\t\t\t\t\t\t<template></template>
\t\t\t\t\t</div>
\t\t\t\t</div>

\t\t\t\t<div id="content_packs_modal"></div>
\t\t\t\t<template id="content-pack-modal-loader"></template>
\t\t\t\t<div id="insufficiant-tokens-modal"></div>
\t\t\t</main>
\t\t</div>"""


def read(path):
    with open(path, "r", encoding="utf-8", errors="surrogateescape") as f:
        return f.read()


def check_css(href):
    """返回 (是否覆盖全部所需类, 命中的类列表)"""
    path = os.path.join(SITE, href.lstrip("./").replace("/", os.sep))
    if not os.path.isfile(path):
        return False, []
    css = read(path)
    hit = [k for k in CSS_REQUIRED if k in css]
    return len(hit) == len(CSS_REQUIRED), hit


def pick_css():
    """① 侧边栏类 ② 本页类 都齐全 → 用 private-content_files 那份；否则退回 shop 页那份"""
    for href in (CSS_PRIVATE, CSS_SHOP):
        ok, hit = check_css(href)
        print("CSS %-72s %s (%d/%d)" % (href, "OK" if ok else "缺少类", len(hit), len(CSS_REQUIRED)))
        if ok:
            return href
    raise SystemExit("两份 application CSS 都不满足要求：\n  " + "\n  ".join(CSS_REQUIRED))


def main():
    base = read(BASE)
    css_href = pick_css()

    i = base.index(MAIN_MARK)
    j = base.index(NAV_MARK)
    head = base[:i]
    tail = base[j:]

    # 1) 标题 / 描述
    head = head.replace("<title>Face Swap - Sugus.ai</title>", "<title>Private Content - Sugus.ai</title>")
    head = head.replace(
        '<meta name="description" content="Upload your photo and swap the face onto a fixed template video">',
        '<meta name="description" content="Private content - unlock exclusive videos and photo sets from your AI characters">',
    )

    # 2) 追加原站 private content 页样式（含 aspect-[9/16] / blur-[7px] / truncate-2-lines 等本页专用类）
    anchor = '<link rel="stylesheet" href="./assets/fy.css" data-turbo-track="reload">'
    assert anchor in head, "未找到 fy.css 引入位置"
    head = head.replace(
        anchor,
        anchor
        + '\n\t\t<link rel="stylesheet" href="./shop_files/css2" media="all">'
        + '\n\t\t<link rel="stylesheet" href="' + css_href + '" data-turbo-track="reload">',
    )

    # 3) 去掉站点原有的旧版 Tailwind 构建，避免两套同源 CSS 互相覆盖
    old_css = '\t\t<link rel="stylesheet" href="./charactersIndex_files/application-bbcb6fded782c5612ce9f0dcaa925c2d80f6d0340a2aa81d0ceb72c1c52c8d5e.css" data-turbo-track="reload">\n'
    assert old_css in head, "未找到旧版 application css 引入位置"
    head = head.replace(old_css, "")

    # 4) 本页脚本
    head = head.replace("./js/face_swap.js", "./js/private_content.js")

    # 5) body 类名（原站是 bg-main-v2 content_packs lobby，本站外壳统一用 bg-main）
    head = head.replace(
        '<body class="bg-main characters index stimulus-reflex-disconnected" id="app"',
        '<body class="bg-main content_packs lobby" id="app"',
    )

    out = head + FRAGMENT + "\n\n" + tail

    for must in (
        'data-main-target="mainSidebarClosedContainer"',
        "./js/private_content.js",
        "./private_content.html?tab=all",
        'id="private-content-lobby-content"',
    ):
        if must not in out:
            raise SystemExit("输出缺少：%s" % must)
    # 旧版 application CSS 必须换掉；外壳自带的 application-*.js（Turbo/Alpine）与 shop.html 一样保留
    for banned in ("js/face_swap.js", "charactersIndex_files/application-bbcb6fded782c5612ce9f0dcaa925c2d80f6d0340a2aa81d0ceb72c1c52c8d5e.css"):
        if banned in out:
            raise SystemExit("输出里仍残留 %s 引用，请检查" % banned)

    with open(OUT, "w", encoding="utf-8", errors="surrogateescape", newline="") as f:
        f.write(out)

    print("CSS 选用:", css_href)
    print("written:", OUT, len(out.encode("utf-8")), "bytes")
    print("head lines:", head.count("\n"), "fragment lines:", FRAGMENT.count("\n"), "tail lines:", tail.count("\n"))


if __name__ == "__main__":
    main()
