"""从存档的 shop.html 里抽取 candy.ai 商店的 10 个商品数据，生成 SQL 种子。

用法：python .trae/extract_shop_items.py
输出：.trae/_shop_items.json + .trae/_shop_seed.sql
"""
import html
import json
import os
import re
import sys

try:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
except Exception:
    pass

ROOT = r"d:\phpstudy_pro\WWW\douyin"
SRC = os.path.join(ROOT, "ai-girl-malaysia.com", "shop.html")

with open(SRC, "r", encoding="utf-8", errors="surrogateescape") as f:
    text = f.read()

# 只取 <main> 里的商品卡区域，避免误匹配弹窗里的内容
main_start = text.index("<main ")
main_end = text.index("</main>")
main = text[main_start:main_end]

ARTICLE_RE = re.compile(
    r'<article class="flex flex-col" data-candy-shop-item-id="(?P<id>\d+)">.*?</article>',
    re.DOTALL,
)

items = []
for m in ARTICLE_RE.finditer(main):
    block = m.group(0)
    item_id = int(m.group("id"))

    title = ""
    tm = re.search(r'<h3[^>]*>\s*<span[^>]*>(.*?)</span>', block, re.DOTALL)
    if tm:
        title = html.unescape(tm.group(1)).strip()

    price = 0
    pm = re.search(r'<img class="size-4 shrink-0"[^>]*>\s*<span>(\d+)</span>', block, re.DOTALL)
    if pm:
        price = int(pm.group(1))

    rating = 0.0
    rm = re.search(r'aria-label="Rated ([\d.]+) out of 5"', block)
    if rm:
        rating = float(rm.group(1))

    rating_pct = 0
    wm = re.search(r'text-\[#FFB300\]" style="width:\s*(\d+)%"', block)
    if wm:
        rating_pct = int(wm.group(1))

    reviews = 0
    vm = re.search(r'aria-label="\((\d+) reviews\)"', block)
    if vm:
        reviews = int(vm.group(1))

    poster = ""
    om = re.search(r'poster="([^"]+)"', block)
    if om:
        poster = om.group(1)

    video = ""
    sm = re.search(r'<source src="([^"]+)"', block)
    if sm:
        video = sm.group(1)

    items.append({
        "candy_id": item_id,
        "title": title,
        "price": price,
        "rating": rating,
        "rating_pct": rating_pct,
        "reviews": reviews,
        "poster": poster,
        "video": video,
    })

# 商品 1 的详情描述（弹窗里唯一有真实文案的）
desc = ""
dm = re.search(r'<p class="mt-2 font-poppins text-xs leading-5 text-grey-default lg:mt-3">(.*?)</p>', text, re.DOTALL)
if dm:
    desc = html.unescape(dm.group(1)).strip()

out_json = os.path.join(ROOT, ".trae", "_shop_items.json")
with open(out_json, "w", encoding="utf-8") as f:
    json.dump({"items": items, "desc_item_1": desc}, f, ensure_ascii=False, indent=2)

print("items:", len(items))
for it in items:
    print("  %2d  %-34s %4d  %.1f (%d)  %s" % (
        it["candy_id"], it["title"], it["price"], it["rating"], it["reviews"], it["video"].rsplit("/", 1)[-1]))

print("desc(item1):", desc[:80])
print("->", out_json)
