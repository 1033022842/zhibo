"""组装 ai-girl-malaysia.com/shop.html

以 face_swap.html 的「站点外壳」（三种侧边栏 + 顶栏 + 移动端导航 + 登录态脚本）为基底，
把中间的主内容整块换成 candy.ai 商店页的复刻片段（.trae/shop_main.html），
确保侧边栏 / 顶栏与全站其它页面 100% 一致。

用法：python .trae/build_shop_page.py
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
FRAGMENT = os.path.join(ROOT, ".trae", "shop_main.html")
OUT = os.path.join(SITE, "shop.html")

SHOP_CSS = "application-c9a80e6102195b41b493002bf9a0ab06ceb43fb6bcbf45cf6c1a4fd0f3b29ad1.css"
MAIN_MARK = "\t\t<!-- Main Content -->"
NAV_MARK = "\t\t<!-- Mobile Bottom Navigation -->"


def main():
    with open(BASE, "r", encoding="utf-8", errors="surrogateescape") as f:
        base = f.read()
    with open(FRAGMENT, "r", encoding="utf-8", errors="surrogateescape") as f:
        fragment = f.read().rstrip("\n")

    i = base.index(MAIN_MARK)
    j = base.index(NAV_MARK)
    head = base[:i]
    tail = base[j:]

    # 1) 标题
    head = head.replace("<title>Face Swap - Sugus.ai</title>", "<title>Candy Shop - Sugus.ai</title>")
    head = head.replace(
        '<meta name="description" content="Upload your photo and swap the face onto a fixed template video">',
        '<meta name="description" content="Candy Shop - buy outfits and gifts for your AI characters">',
    )

    # 2) 追加原站商店页样式（含 bg-black-light / aspect-[3/4] / size-* 等本页专用类）
    anchor = '<link rel="stylesheet" href="./assets/fy.css" data-turbo-track="reload">'
    assert anchor in head, "未找到 fy.css 引入位置"
    head = head.replace(
        anchor,
        anchor
        + '\n\t\t<link rel="stylesheet" href="./shop_files/css2" media="all">'
        + '\n\t\t<link rel="stylesheet" href="./shop_files/' + SHOP_CSS + '" data-turbo-track="reload">',
    )

    # 2.1) 去掉站点原有的旧版 Tailwind 构建，避免两套同源 CSS 互相覆盖：
    #      商店页的 CSS 是 candy.ai 更新的构建，已包含侧边栏所需的全部类
    old_css = '\t\t<link rel="stylesheet" href="./charactersIndex_files/application-bbcb6fded782c5612ce9f0dcaa925c2d80f6d0340a2aa81d0ceb72c1c52c8d5e.css" data-turbo-track="reload">\n'
    assert old_css in head, "未找到旧版 application css 引入位置"
    head = head.replace(old_css, "")

    # 3) 商品列表加载态用的旋转动画（原站是内联在局部页里的）
    head = head.replace(
        "</head>",
        "<style>\n"
        "\t\t\t@keyframes loader-spin-counter-clockwise { from { transform: rotate(0deg); } to { transform: rotate(-360deg); } }\n"
        "\t\t\t.loader-spin-counter-clockwise { animation: loader-spin-counter-clockwise 1s linear infinite; }\n"
        "\t\t</style>\n\t</head>",
    )

    # 4) 本页脚本
    head = head.replace("./js/face_swap.js", "./js/shop.js")

    # 5) body 类名
    head = head.replace(
        '<body class="bg-main characters index stimulus-reflex-disconnected" id="app"',
        '<body class="bg-main items index" id="app"',
    )

    out = head + fragment + "\n\n" + tail

    # 6) 侧边栏选中态从 Face Swap 移到 Shop（外壳是从 face_swap.html 来的，默认高亮 Face Swap）
    for old, new in (
        # 收起态（100px）
        ('href="./face_swap.html" title="Face Swap" class="relative h-[48px] w-[52px] hover:bg-zinc-700 bg-[#303030] rounded-[10px]',
         'href="./face_swap.html" title="Face Swap" class="relative h-[48px] w-[52px] hover:bg-zinc-700 rounded-[10px]'),
        ('href="./shop.html" title="Shop" class="relative h-[48px] w-[52px] hover:bg-zinc-700 rounded-[10px]',
         'href="./shop.html" title="Shop" class="relative h-[48px] w-[52px] hover:bg-zinc-700 bg-[#303030] rounded-[10px]'),
        # 展开态（220px）
        ('href="./face_swap.html" class="relative h-[40px] w-full hover:bg-zinc-700 bg-[#303030] rounded-[10px]',
         'href="./face_swap.html" class="relative h-[40px] w-full hover:bg-zinc-700 rounded-[10px]'),
        ('href="./shop.html" class="relative h-[40px] w-full hover:bg-zinc-700 rounded-[10px]',
         'href="./shop.html" class="relative h-[40px] w-full hover:bg-zinc-700 bg-[#303030] rounded-[10px]'),
        # 展开态 / 移动端抽屉的文字颜色
        ('text-white text-xxs font-medium leading-4">Face Swap<', 'text-grey-medium text-xxs font-medium leading-4">Face Swap<'),
        ('text-grey-medium text-xxs font-medium leading-4">Shop<', 'text-white text-xxs font-medium leading-4">Shop<'),
        ('text-white text-xs leading-5 font-semibold">Face Swap<', 'text-grey-medium text-xs leading-5 font-semibold">Face Swap<'),
        ('text-grey-medium text-xs leading-5 font-semibold">Shop<', 'text-white text-xs leading-5 font-semibold">Shop<'),
    ):
        out = out.replace(old, new)

    if 'title="Shop" class="relative h-[48px] w-[52px] hover:bg-zinc-700 bg-[#303030]' not in out:
        raise SystemExit("侧边栏 Shop 高亮未生效，请检查替换规则")

    if "js/face_swap.js" in out:
        raise SystemExit("输出里仍残留 face_swap.js 引用，请检查")

    with open(OUT, "w", encoding="utf-8", errors="surrogateescape", newline="") as f:
        f.write(out)

    print("written:", OUT, len(out), "bytes")
    print("head lines:", head.count("\n"), "fragment lines:", fragment.count("\n"))


if __name__ == "__main__":
    main()
