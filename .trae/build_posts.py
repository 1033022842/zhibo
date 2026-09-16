"""组装 ai-girl-malaysia.com/posts.html

以 face_swap.html 的「站点外壳」（三种侧边栏 + 顶栏 + 移动端导航 + 登录态脚本）为基底，
把中间的主内容整块换成 candy.ai「Posts 动态流」页（https://candy.ai/feed/posts）的复刻片段，
并把原站内联在局部页里的 feed 专用 CSS 原样带过来，确保 class 一字不改、结构像素级还原。

素材：
  .trae/archive/posts.html      原站存档页（feed 内联 CSS 1900~2173 行、<main> 2176~2394 行）
  .trae/_posts_templates.html   已抽好的 DOM 模板块（class 已合并成一行、动态值换成 {{占位符}}）

用法：python .trae/build_posts.py
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
ARCHIVE = os.path.join(ROOT, ".trae", "archive", "posts.html")
TEMPLATES = os.path.join(ROOT, ".trae", "_posts_templates.html")
OUT = os.path.join(SITE, "posts.html")

# candy.ai 最新版 Tailwind 构建：同时包含 ① 侧边栏需要的类（bg-main / text-grey-medium /
# webkit-transition-sidebar / bg-black-default）② 本页需要的类（bg-[#131313] / line-clamp-3 /
# animate-spin-slow / backdrop-blur），已用 PowerShell 逐项校验（见 .trae/build_posts.py 说明）
POSTS_CSS = "application-c9a80e6102195b41b493002bf9a0ab06ceb43fb6bcbf45cf6c1a4fd0f3b29ad1.css"
LIKE_ICON = "./posts_files/like-6bfb7f5a54d0259524bf6b37730926d02f493c54d1a3aa1db5fdd3a281f15d37.svg"

MAIN_MARK = "\t\t<!-- Main Content -->"
NAV_MARK = "\t\t<!-- Mobile Bottom Navigation -->"
INDENT = "\t\t"


def read(path):
    with open(path, "r", encoding="utf-8", errors="surrogateescape") as f:
        return f.read()


def blocks(text):
    """把 _posts_templates.html 解析成 {模板名: 片段}"""
    out = {}
    mark = "<!-- ==== TEMPLATE: "
    pos = 0
    while True:
        i = text.find(mark, pos)
        if i == -1:
            return out
        j = text.find("====", i + len(mark))
        name = text[i + len(mark):j].strip()
        head_end = text.find("-->", j) + 3
        end = text.find(mark, head_end)
        if end == -1:
            end = len(text)
        out[name] = text[head_end:end].strip("\n")
        pos = end


def indent(text, prefix):
    return "\n".join(prefix + line if line.strip() else line for line in text.split("\n"))


def feed_style(archive):
    """原样取出存档页里内联的 feed 专用 CSS（含 <style> 标签）"""
    start = archive.index("<style>\n  /* Desktop feed container")
    end = archive.index("</style>", start) + len("</style>")
    return archive[start:end]


def fragment(tpl):
    """用模板块拼出 posts 页的主内容（卡片由 js/posts.js 渲染，HTML 里只留骨架）"""
    loading = tpl["feed-desktop-loading"]
    container = tpl["feed-desktop-container"]
    like_hud = tpl["like-hud"]
    play_overlay = tpl["feed-play-overlay"]
    main = tpl["main"]

    # 加载骨架：原站用 Stimulus target 标记，这里换成普通 data 属性
    old = '<div class="hidden" aria-live="polite">'
    assert old in loading, "加载骨架根节点未找到"
    loading = loading.replace(old, '<div data-feed-loading class="hidden" aria-live="polite">')
    loading = indent(loading, "  ")

    # 点赞 HUD：初始无数据，post_id / likes 交给 js/posts.js 填
    like_hud = like_hud.replace("{{post_id}}", "").replace("{{likes}}", "0")
    like_hud = like_hud.replace("{{like_icon}}", LIKE_ICON)
    for old, new in (
        ('<aside class="feed-desktop-actions-sidebar"', '<aside id="feed-desktop-like-hud" class="feed-desktop-actions-sidebar"'),
        ('<button type="button" class="flex flex-col items-center', '<button type="button" id="feed-desktop-like-button" class="flex flex-col items-center'),
        ('<img alt="Like" class="w-full h-full"', '<img alt="Like" id="feed-desktop-like-icon" class="w-full h-full"'),
        ('<p class="text-white text-sm leading-5 font-medium font-poppins"', '<p id="feed-desktop-like-count" class="text-white text-sm leading-5 font-medium font-poppins"'),
    ):
        assert old in like_hud, "点赞 HUD 结构未找到：" + old
        like_hud = like_hud.replace(old, new)
    like_hud = indent(like_hud, "    ")

    # 暂停时共享的播放按钮（原站会被 append 进当前卡片，靠绝对定位居中）
    old = '<div style="display: none;" class="feed-play-overlay" aria-hidden="true">'
    assert old in play_overlay, "播放浮层结构未找到"
    play_overlay = play_overlay.replace(old, '<div id="feed-play-overlay" style="display: none;" class="feed-play-overlay" aria-hidden="true">')
    play_overlay = indent(play_overlay, "  ")

    # ① 先给 row 里的内容列加 id（此时加载骨架还没插入，不会误命中骨架里的同名 div）
    for old, new in (
        ('<div class="feed-desktop-container">', '<div class="feed-desktop-container" id="feed-desktop-container">'),
        ('<div class="feed-desktop-content-column">', '<div class="feed-desktop-content-column" id="feed-desktop-content">'),
        ("      {{feed_items}}\n", ""),  # 卡片由 js/posts.js 动态渲染
        ("{{top_spacer_height}}", "0"),
        ("{{bottom_spacer_height}}", "0"),
        ("{{like_hud}}", like_hud),
        ("{{play_overlay}}", play_overlay),
        ("{{loading_block}}", loading),  # ② 最后插入加载骨架
    ):
        assert old in container, "容器结构未找到：" + old
        container = container.replace(old, new)

    # ③ main：本站左侧栏收起态 100px（展开态才 220px），所以去掉原站的 lg:ml-[220px]
    #    注意：这里不加 pt-16 —— 原站 feed 容器就是 height:100vh 且顶栏 fixed 64px，
    #    卡片顶部 75px 本来就是空的（媒体被 is-visible 下移 75px），顶栏正好盖在死区上；
    #    若加 pt-16 会把 100vh 的容器整体推下 64px，卡片底部含角色名 / Chat Now 的那一行
    #    会被视口裁掉 39px，而容器自己吃掉滚轮事件导致这 39px 基本滚不出来。
    main = main.replace('<main class="ml-0 lg:ml-[220px] bg-[#131313] min-h-screen">',
                        '<main class="ml-0 lg:ml-[100px] bg-[#131313] min-h-screen">')
    main = main.replace("{{feed_desktop_container}}", container)

    # ④ 外壳里的主内容容器（Stimulus main 控制器的 contentContainer 目标，全站每页都有）
    return ('\t\t<!-- Main Content -->\n'
            '\t\t<div class="main-content-container" data-main-target="contentContainer">\n'
            + indent(main, INDENT + "\t") + '\n\t\t</div>')


def main():
    base = read(BASE)
    archive = read(ARCHIVE)
    tpl = blocks(read(TEMPLATES))

    i = base.index(MAIN_MARK)
    j = base.index(NAV_MARK)
    head = base[:i]
    tail = base[j:]

    # 1) 标题 / 描述
    head = head.replace("<title>Face Swap - Sugus.ai</title>", "<title>Posts - Sugus.ai</title>")
    head = head.replace(
        '<meta name="description" content="Upload your photo and swap the face onto a fixed template video">',
        '<meta name="description" content="Posts - watch the latest AI character posts and chat with them in Sugus.ai">',
    )

    # 2) 样式表：fy.css（沿用外壳）+ 站点验证可用的 candy.ai 字体 / Tailwind 构建
    anchor = '<link rel="stylesheet" href="./assets/fy.css" data-turbo-track="reload">'
    assert anchor in head, "未找到 fy.css 引入位置"
    head = head.replace(
        anchor,
        anchor
        + '\n\t\t<link rel="stylesheet" href="./shop_files/css2" media="all">'
        + '\n\t\t<link rel="stylesheet" href="./posts_files/' + POSTS_CSS + '" data-turbo-track="reload">',
    )

    # 3) 去掉站点原有的旧版 Tailwind 构建，避免两套同源 CSS 互相覆盖
    old_css = '\t\t<link rel="stylesheet" href="./charactersIndex_files/application-bbcb6fded782c5612ce9f0dcaa925c2d80f6d0340a2aa81d0ceb72c1c52c8d5e.css" data-turbo-track="reload">\n'
    assert old_css in head, "未找到旧版 application css 引入位置"
    head = head.replace(old_css, "")

    # 4) 原站内联的 feed 专用 CSS，原样搬进 <head>
    head = head.replace("</head>", feed_style(archive) + "\n\t\t\t</head>")

    # 5) 本页脚本 + body 类名
    head = head.replace('./js/face_swap.js', './js/posts.js')
    head = head.replace(
        '<body class="bg-main characters index stimulus-reflex-disconnected" id="app"',
        '<body class="bg-main feed index" id="app"',
    )

    out = head + fragment(tpl) + "\n\n" + tail

    # 6) 侧边栏高亮：外壳来自 face_swap.html（默认高亮 Face Swap），而全站导航里没有 Posts 入口，
    #    直接去掉高亮，不新增导航项（保持与其它页面 100% 一致）
    for old, new in (
        ('href="./face_swap.html" title="Face Swap" class="relative h-[48px] w-[52px] hover:bg-zinc-700 bg-[#303030] rounded-[10px]',
         'href="./face_swap.html" title="Face Swap" class="relative h-[48px] w-[52px] hover:bg-zinc-700 rounded-[10px]'),
        ('href="./face_swap.html" class="relative h-[40px] w-full hover:bg-zinc-700 bg-[#303030] rounded-[10px]',
         'href="./face_swap.html" class="relative h-[40px] w-full hover:bg-zinc-700 rounded-[10px]'),
        ('text-start text-white text-xxs font-medium leading-4">Face Swap<', 'text-start text-grey-medium text-xxs font-medium leading-4">Face Swap<'),
        ('text-start text-white text-xs leading-5 font-semibold">Face Swap<', 'text-start text-grey-medium text-xs leading-5 font-semibold">Face Swap<'),
    ):
        assert old in out, "侧边栏高亮结构未找到：" + old
        out = out.replace(old, new)

    if "js/face_swap.js" in out:
        raise SystemExit("输出里仍残留 face_swap.js 引用，请检查")
    # 只清掉旧的 Tailwind 构建（.css）；外壳的 Stimulus 应用脚本（charactersIndex_files/application-*.js）
    # 与 shop.html / face_swap.html 保持一致，必须保留（侧边栏、登录态、弹窗都靠它）
    if "charactersIndex_files/application-bbcb6fded782c5612ce9f0dcaa925c2d80f6d0340a2aa81d0ceb72c1c52c8d5e.css" in out:
        raise SystemExit("输出里仍残留旧版 application css 引用，请检查")

    with open(OUT, "w", encoding="utf-8", errors="surrogateescape", newline="") as f:
        f.write(out)

    print("written:", OUT, len(out.encode("utf-8")), "bytes,", out.count("\n") + 1, "lines")


if __name__ == "__main__":
    main()
