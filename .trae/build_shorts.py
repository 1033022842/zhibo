"""组装 ai-girl-malaysia.com/shorts.html

以 face_swap.html 的「站点外壳」（三种侧边栏 + 顶栏 + 移动端导航 + 登录态脚本）为基底，
把中间的主内容整块换成 candy.ai/candy-shorts 的 1:1 复刻片段（class 与存档页一字不改，
卡片由 js/shorts.js 从 /api/live/shorts 渲染），确保侧边栏 / 顶栏与全站其它页面 100% 一致。

用法：python .trae/build_shorts.py
"""
import os
import re
import sys

try:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
except Exception:
    pass

ROOT = r"d:\phpstudy_pro\WWW\douyin"
SITE = os.path.join(ROOT, "ai-girl-malaysia.com")
BASE = os.path.join(SITE, "face_swap.html")
OUT = os.path.join(SITE, "shorts.html")

# CSS：.trae/archive/candy-shorts.html 引用的那份，与 shop_files 里那份 SHA256 完全一致
SHORTS_CSS = "application-c9a80e6102195b41b493002bf9a0ab06ceb43fb6bcbf45cf6c1a4fd0f3b29ad1.css"
SHORTS_CSS_DIR = "./candy-shorts_files/"
OLD_CSS = '\t\t<link rel="stylesheet" href="./charactersIndex_files/application-bbcb6fded782c5612ce9f0dcaa925c2d80f6d0340a2aa81d0ceb72c1c52c8d5e.css" data-turbo-track="reload">\n'

MAIN_MARK = "\t\t<!-- Main Content -->"
NAV_MARK = "\t\t<!-- Mobile Bottom Navigation -->"

ICON_BACK = "./candy-shorts_files/chevron_left-de2998a1c014f985d6671bd37486b618f48fd968ae90aa918c13405ce9b8fcf5.svg"
ICON_NEXT = "./candy-shorts_files/chevron_right-e61ca94ade214ead19832349d6a795f47b10c92906d388fee1313fa9ed2ddafe.svg"
ICON_PREV = "./candy-shorts_files/chevron_left-b8a7af3652c12d167876297ef2fa037cf5165553e306544588901c77d05ed096.svg"

# ---------------------------------------------------------------- 复刻片段用的 class（照存档页）
TAB_CLS = (
    "group relative shrink-0 whitespace-nowrap pb-2.5 pt-1 text-sm md:text-md font-medium "
    "text-white/50 cursor-pointer transition-colors duration-200 hover:text-white aria-selected:text-white"
)
TAB_UNDERLINE = (
    '<span class="absolute inset-x-0 bottom-0 h-[2.5px] rounded-full bg-gradient-pink-cta '
    'opacity-0 transition-opacity duration-200 group-aria-selected:opacity-100" aria-hidden="true"></span>'
)
PILL_CLS = (
    "inline-flex shrink-0 snap-start items-center gap-1.5 whitespace-nowrap rounded-full border "
    "border-white/10 bg-white/[0.04] px-3.5 py-1.5 text-sm text-white/70 transition-all duration-200 "
    "cursor-pointer hover:bg-white/10 hover:text-white aria-selected:border-transparent "
    "aria-selected:bg-white aria-selected:font-semibold aria-selected:text-black"
)
PILL_BADGE = (
    '<span class="rounded bg-[#ff1f30] px-1.5 py-0.5 text-[10px] font-bold uppercase leading-none '
    'text-white">New</span>'
)

# 角色类型 tab（第一个默认选中）
SCOPE_TABS = [
    ("All", "true"),
    ("Candy Creators", "false"),
    ("Candy characters", "false"),
    ("Other characters", "false"),
]

# 13 个分类 pill：(文案, 图标, 徽章文案, 是否选中)
TAG_PILLS = [
    ("All", "", "", "true"),
    ("New", "", "", "false"),
    ("Spicy", "🔥", "New", "false"),
    ("Stepsister", "", "", "false"),
    ("Milf", "", "", "false"),
    ("Cuckold", "", "", "false"),
    ("Nympho", "", "", "false"),
    ("Stepmom", "", "", "false"),
    ("Wife", "", "", "false"),
    ("Taboo", "", "", "false"),
    ("Fantasy", "", "", "false"),
    ("Teacher", "", "", "false"),
    ("Maid", "", "", "false"),
]

I_SCOPE = "\t" * 10        # 角色类型 tab 按钮缩进
I_SCOPE2 = "\t" * 11       # 角色类型 tab 内容缩进
I_PILL = "\t" * 9          # 分类 pill 缩进
I_PILL2 = "\t" * 10        # 分类 pill 内容缩进


def scope_tabs_html():
    out = []
    for label, selected in SCOPE_TABS:
        out.append(
            I_SCOPE + '<button type="button" role="tab" aria-selected="' + selected + '" class="' + TAB_CLS + '">'
        )
        out.append(I_SCOPE2 + label)
        out.append("")
        out.append(I_SCOPE2 + TAB_UNDERLINE)
        out.append(I_SCOPE + "</button>")
    return "\n".join(out)


def tag_pills_html():
    out = []
    for label, icon, badge, selected in TAG_PILLS:
        out.append(
            I_PILL + '<button type="button" role="tab" aria-selected="' + selected + '" class="' + PILL_CLS + '">'
        )
        if icon:
            out.append(I_PILL2 + '<span aria-hidden="true">' + icon + "</span>")
            out.append("")
        out.append(I_PILL2 + "<span>" + label + "</span>")
        if badge:
            out.append("")
            out.append(I_PILL2 + PILL_BADGE)
        out.append(I_PILL + "</button>")
        out.append("")
    return "\n".join(out).rstrip("\n")


def arrow_buttons(right_label, left_label):
    """货架左右箭头：与原站（templates: row-arrow-buttons / row-arrow-button--disabled）一致"""
    ind = "\t\t\t\t\t\t\t\t"
    right_cls = (
        "absolute top-1/2 z-20 hidden size-11 -translate-y-1/2 items-center justify-center rounded-full "
        "border border-shorts-row-arrow-border bg-shorts-row-arrow text-white shadow-shorts-row-arrow "
        "transition-opacity duration-200 hover:bg-shorts-row-arrow-hover lg:flex right-2 opacity-100"
    )
    left_cls = (
        "pointer-events-none absolute top-1/2 z-20 hidden size-11 -translate-y-1/2 items-center justify-center "
        "rounded-full border border-shorts-row-arrow-border bg-shorts-row-arrow text-white opacity-0 "
        "shadow-shorts-row-arrow transition-opacity duration-200 hover:bg-shorts-row-arrow-hover lg:flex left-2"
    )
    return (
        ind + '<button type="button" class="' + right_cls + '" aria-label="' + right_label + '" aria-hidden="false">\n'
        + ind + '\t<img class="size-[18px] brightness-200" alt="" aria-hidden="true" src="' + ICON_NEXT + '">\n'
        + ind + "</button>\n"
        "\n"
        + ind + '<button type="button" class="' + left_cls + '" aria-label="' + left_label + '" aria-hidden="true" disabled="">\n'
        + ind + '\t<img class="size-[18px] brightness-200" alt="" aria-hidden="true" src="' + ICON_PREV + '">\n'
        + ind + "</button>"
    )


FRAGMENT = """\t\t<!-- Main Content -->
\t\t<div class="main-content-container" data-main-target="contentContainer">
\t\t\t<main class="h-full pb-[calc(70px+env(safe-area-inset-bottom)+1rem)] lg:pb-0 pt-16 lg:pl-[100px]" tabindex="-1">
\t\t\t\t<div class="px-[10px] pb-4 md:px-4 md:pb-6 lg:px-[105px] pt-[calc(env(safe-area-inset-top)+1rem)] md:pt-[calc(env(safe-area-inset-top)+1.5rem)] lg:pt-6">
\t\t\t\t\t<div class="mx-auto flex max-w-[1296px] flex-col lg:max-w-7xl">
\t\t\t\t\t\t<h1 class="sr-only">Candy Shorts</h1>

\t\t\t\t\t\t<!-- Continue watching（order-2）：卡片由 js/shorts.js 从 /api/live/shorts 渲染 -->
\t\t\t\t\t\t<section class="hidden order-2 mb-5 lg:mb-7" aria-labelledby="candy-shorts-continue-watching-title">
\t\t\t\t\t\t\t<h2 id="candy-shorts-continue-watching-title" class="text-[18px] leading-[27px] font-semibold text-white lg:text-xl lg:leading-6"><span class="text-pink-dark">Continue</span> <span>watching</span></h2>

\t\t\t\t\t\t\t<div class="relative mt-3 lg:mt-4">
\t\t\t\t\t\t\t\t<div class="cs-row-scroller hide-scrollbar -mx-[10px] flex snap-x snap-mandatory gap-[10px] overflow-x-auto px-[10px] pb-1 scroll-pl-[10px] md:-mx-4 md:px-4 md:scroll-pl-4 lg:mx-0 lg:gap-5 lg:px-0 lg:scroll-pl-0" data-fade="none"></div>

__ARROWS_CONTINUE__
\t\t\t\t\t\t\t</div>
\t\t\t\t\t\t</section>

\t\t\t\t\t\t<!-- 筛选栏（order-1）：sticky，含返回首页 / 角色类型 tab / 分类 pill；仅切换选中态，不做真实筛选 -->
\t\t\t\t\t\t<div class="sticky z-20 bg-[#0d0a0f]/85 pb-3 pt-1 backdrop-blur-md order-1 mb-5 lg:mb-7 -mx-[10px] top-[env(safe-area-inset-top)] px-[10px] md:-mx-4 md:px-4 lg:-mx-6 lg:px-6 lg:top-16">
\t\t\t\t\t\t\t<div class="mx-auto flex max-w-7xl flex-col gap-3">
\t\t\t\t\t\t\t\t<div class="flex items-stretch border-b border-white/5 -mx-[10px] px-[10px] md:-mx-4 md:px-4 lg:-mx-6 lg:px-6">
\t\t\t\t\t\t\t\t\t<a class="mr-3 flex shrink-0 items-center self-center" aria-label="Back to home" href="./Home.html">
\t\t\t\t\t\t\t\t\t\t<span class="flex size-8 items-center justify-center rounded-full bg-white/[0.08] lg:size-11">
\t\t\t\t\t\t\t\t\t\t\t<img class="size-3.5 lg:size-4" alt="" aria-hidden="true" src="__ICON_BACK__">
\t\t\t\t\t\t\t\t\t\t</span>
\t\t\t\t\t\t\t\t\t</a>
\t\t\t\t\t\t\t\t\t<div role="tablist" aria-label="Character type" class="cs-filter-scroller hide-scrollbar flex min-w-0 flex-1 items-center gap-6 overflow-x-auto" data-fade="none">
__SCOPE_TABS__
\t\t\t\t\t\t\t\t\t</div>
\t\t\t\t\t\t\t\t</div>
\t\t\t\t\t\t\t\t<div role="tablist" aria-label="Categories" class="cs-filter-scroller hide-scrollbar flex snap-x items-center gap-2 overflow-x-auto -mx-[10px] px-[10px] scroll-pl-[10px] md:-mx-4 md:px-4 md:scroll-pl-4 lg:-mx-6 lg:px-6 lg:scroll-pl-6" data-fade="none">
__TAG_PILLS__
\t\t\t\t\t\t\t\t</div>
\t\t\t\t\t\t\t</div>
\t\t\t\t\t\t</div>

\t\t\t\t\t\t<!-- Top 10 shows in the US this week（order-4） -->
\t\t\t\t\t\t<section class="hidden order-4 mb-5 lg:mb-7" aria-labelledby="candy-shorts-top-series-title">
\t\t\t\t\t\t\t<h2 id="candy-shorts-top-series-title" class="text-[18px] leading-[27px] font-semibold text-white lg:text-xl lg:leading-6"><span class="text-pink-dark">Top 10</span> <span>shows in the US this week</span></h2>

\t\t\t\t\t\t\t<div class="relative mt-3 lg:mt-4">
\t\t\t\t\t\t\t\t<div class="cs-row-scroller hide-scrollbar flex snap-x snap-mandatory items-start overflow-x-auto overflow-y-hidden pb-1 -mx-[10px] px-[10px] scroll-pl-[10px] md:-mx-4 md:px-4 md:scroll-pl-4 lg:mx-0 lg:px-0 lg:scroll-pl-0 cs-top-shelf" data-fade="right"></div>

__ARROWS_TOP__
\t\t\t\t\t\t\t</div>
\t\t\t\t\t\t</section>

\t\t\t\t\t\t<!-- Explore All Shorts（order-5 标题 + order-6 网格） -->
\t\t\t\t\t\t<div class="hidden order-5 mb-3 lg:mb-4">
\t\t\t\t\t\t\t<h2 class="text-[18px] leading-[27px] font-semibold text-white lg:text-xl lg:leading-6"><span class="text-pink-dark">Explore</span> <span>All Shorts</span></h2>
\t\t\t\t\t\t</div>

\t\t\t\t\t\t<div class="hidden order-6 block scroll-mt-40 md:scroll-mt-44 transition-opacity duration-150 aria-busy:pointer-events-none aria-busy:opacity-60" id="candy-shorts-library-grid">
\t\t\t\t\t\t\t<div id="candy-shorts-library-cards-all-all" class="grid grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-2.5 md:gap-6"></div>
\t\t\t\t\t\t</div>

\t\t\t\t\t</div>
\t\t\t\t</div>
\t\t\t</main>
\t\t</div>"""


def main():
    with open(BASE, "r", encoding="utf-8", errors="surrogateescape") as f:
        base = f.read()

    i = base.index(MAIN_MARK)
    j = base.index(NAV_MARK)
    head = base[:i]
    tail = base[j:]

    # 1) 外壳必须带侧边栏（自检要求）
    assert 'data-main-target="mainSidebarClosedContainer"' in head, "外壳缺少侧边栏容器"

    # 2) 标题 / 描述
    head = head.replace("<title>Face Swap - Sugus.ai</title>", "<title>Candy Shorts - Sugus.ai</title>")
    head = head.replace(
        '<meta name="description" content="Upload your photo and swap the face onto a fixed template video">',
        '<meta name="description" content="Candy Shorts - watch short series with your AI characters">',
    )

    # 3) CSS：保留 assets/fy.css，补 Poppins(css2) 与 Shorts 页的 application css，
    #    并删掉站点旧的 Tailwind 构建（避免两套同源 CSS 互相覆盖）
    anchor = '<link rel="stylesheet" href="./assets/fy.css" data-turbo-track="reload">'
    assert anchor in head, "未找到 fy.css 引入位置"
    head = head.replace(
        anchor,
        anchor
        + '\n\t\t<link rel="stylesheet" href="./shop_files/css2" media="all">'
        + '\n\t\t<link rel="stylesheet" href="' + SHORTS_CSS_DIR + SHORTS_CSS + '" data-turbo-track="reload">',
    )
    assert OLD_CSS in head, "未找到旧版 application css 引入位置"
    head = head.replace(OLD_CSS, "")

    # 4) 本页脚本
    head = head.replace("./js/face_swap.js", "./js/shorts.js")

    # 5) body 类名
    head = head.replace(
        '<body class="bg-main characters index stimulus-reflex-disconnected" id="app"',
        '<body class="bg-main library index" id="app"',
    )

    # 6) 主内容片段
    fragment = FRAGMENT
    fragment = fragment.replace("__ICON_BACK__", ICON_BACK)
    fragment = fragment.replace("__SCOPE_TABS__", scope_tabs_html())
    fragment = fragment.replace("__TAG_PILLS__", tag_pills_html())
    fragment = fragment.replace("__ARROWS_CONTINUE__", arrow_buttons("Show more Continue Watching series", "Show previous Continue Watching series"))
    fragment = fragment.replace("__ARROWS_TOP__", arrow_buttons("Show more top series", "Show previous top series"))

    out = head + fragment + "\n\n" + tail

    # 7) 收尾自检
    if "js/face_swap.js" in out:
        raise SystemExit("输出里仍残留 face_swap.js 引用，请检查")
    # 只校验旧版 application css 已移除（外壳自带的 charactersIndex_files/application-*.js 框架脚本与
    # shop.html 一致，按原样保留）
    if re.search(r"charactersIndex_files/application-[0-9a-f]+\.css", out):
        raise SystemExit("输出里仍残留旧版 application css 引用，请检查")
    if 'data-main-target="mainSidebarClosedContainer"' not in out:
        raise SystemExit("输出缺少侧边栏容器")

    with open(OUT, "w", encoding="utf-8", errors="surrogateescape", newline="") as f:
        f.write(out)

    print("written:", OUT)
    print("bytes:", len(out.encode("utf-8", "surrogateescape")), "lines:", out.count("\n") + 1)
    print("head lines:", head.count("\n"), "fragment lines:", fragment.count("\n"), "tail lines:", tail.count("\n"))
    print("css:", SHORTS_CSS_DIR + SHORTS_CSS)


if __name__ == "__main__":
    main()
