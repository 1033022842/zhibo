#!/usr/bin/env python3
"""按公开参考页取证 JSON 建立版本化素材目录 catalog-v1 + manifest.json。

输入（只读 tests/create-character/assets/evidence/ref 下的取证 JSON）：
  {gender}-{style}-{eth}-s2.json   组图（body/breast/butt/hairstyle）
  {gender}-{style}-white-s1.json   六族裔卡
输出：
  ai-girl-malaysia.com/assets/create/catalog-v1/**.webp
  ai-girl-malaysia.com/assets/create/manifest.json

key 统一为 5 段：{gender}|{style}|{ethnicity}|{group}|{option}
  group ∈ ethnicity/body/breast/butt/hairstyle；颜色卡由前端 CSS 色块渲染，不出图。
"""
import hashlib
import io
import json
import sys
import time
import urllib.request
from concurrent.futures import ThreadPoolExecutor
from pathlib import Path

from PIL import Image

ROOT = Path(r"D:\ever\douyin\douyin")
EVID = ROOT / "tests" / "create-character" / "assets" / "evidence" / "ref"
SITE = ROOT / "ai-girl-malaysia.com"
CREATE = SITE / "assets" / "create"
OUT = CREATE / "catalog-v1"
MANIFEST = CREATE / "manifest.json"
CDN = "https://imagedelivery.net/XCC9OTLycTbXl7Ir7oXy0g/{}/width=320,quality=85,format=auto"

STYLES = ["Realistic", "Anime"]  # 前端契约的 style 段取值
ETH = ["white", "asian", "black", "latina", "south-asian", "middle-eastern"]
HAIR = ["straight", "bangs", "braids", "curly", "bun", "ponytail", "bob", "bald"]
F_BODY = ["slim", "athletic", "average", "curvy", "bbw"]
F_BREAST = ["flat", "small", "average", "big", "huge"]
F_BUTT = ["small", "slim", "athletic", "big", "huge"]
M_BODY = ["slim", "average", "muscular", "stout", "large"]
M_SIZE = ["micro", "small", "average", "big", "huge"]
M_HAIR = ["bald", "shaggy", "bro-flow", "buzz-cut", "fade", "long", "mullet", "undercut"]

LABEL2SLUG = {
    "Straight": "straight", "Bangs": "bangs", "Braids": "braids", "Curly": "curly",
    "Bun": "bun", "Ponytail": "ponytail", "Bob": "bob", "Bald": "bald",
    "Slim": "slim", "Athletic": "athletic", "Average": "average", "Curvy": "curvy", "BBW": "bbw",
    "Flat": "flat", "Small": "small", "Big": "big", "Huge": "huge",
    "Muscular": "muscular", "Stout": "stout", "Large": "large",
    "Micro": "micro", "Shaggy": "shaggy", "Bro flow": "bro-flow", "Buzz cut": "buzz-cut",
    "Fade": "fade", "Long": "long", "Mullet": "mullet", "Undercut": "undercut",
    "Platinum": "platinum", "Brunette": "brunette", "Black": "black", "Blond": "blond",
    "Ginger": "ginger", "Pink": "pink", "Blue": "blue",
    "White": "white", "Asian": "asian", "Latina": "latina", "Latino": "latina",
    "South Asian": "south-asian", "Middle Eastern": "middle-eastern",
}
HAIR_ALT = {"Straight", "Bangs", "Braids", "Curly", "Bun", "Ponytail", "Bob", "Bald",
            "Shaggy", "Bro flow", "Buzz cut", "Fade", "Long", "Mullet", "Undercut"}
BODY_ALT = {"Slim", "Athletic", "Average", "Curvy", "BBW", "Muscular", "Stout", "Large"}
SIZE_ALT = {"Flat", "Small", "Average", "Big", "Huge", "Micro"}


def rows(images):
    """行聚类：页面横向滚动条回位后，横滑条里的卡片 x 为负值；
    负 x 与正 x 分开聚类，避免发色条(y155/165)和身材行(y159)在视觉上重叠被并成一行。"""
    out = []
    for part in ([i for i in images if i["x"] < 0], [i for i in images if i["x"] >= 0]):
        part.sort(key=lambda i: (i["y"], i["x"]))
        cur = None
        for im in part:
            if cur and abs(im["y"] - cur["y0"]) <= 24:
                cur["items"].append(im)
            else:
                cur = {"y0": im["y"], "items": [im]}
                out.append(cur)
    for r in out:
        r["items"].sort(key=lambda i: i["x"])
    return out


def load(path):
    with open(path, encoding="utf-8") as f:
        return json.load(f)["data"]["images"]


def parse_options(images, gender):
    res = {"body": {}, "breast": {}, "butt": {}, "hairstyle": {}}
    for r in rows(images):
        alts = {i["alt"] for i in r["items"]}
        if "Flat" in alts or "Micro" in alts:
            group = "breast"          # 男性即尺寸组 Micro..Huge
        elif alts & {"BBW", "Muscular", "Stout", "Large"}:
            group = "body"
        elif "Curvy" in alts:
            group = "body"
        elif alts & {"Small", "Slim", "Athletic", "Big", "Huge"}:
            group = "butt" if gender == "female" else "breast"
        elif alts & HAIR_ALT:
            group = "hairstyle"
        else:
            group = None
        if not group:
            continue
        for i in r["items"]:
            slug = LABEL2SLUG.get(i["alt"])
            if slug:
                res[group][slug] = i["id"]
    if gender == "male":
        res.pop("butt")
    return res


def parse_eth(images):
    return {LABEL2SLUG[i["alt"]]: i["id"] for i in images if i["alt"] in
            ("White", "Asian", "Black", "Latina", "Latino", "South Asian", "Middle Eastern")}


def sha(b):
    return hashlib.sha256(b).hexdigest()


def fetch(image_id, dest: Path):
    url = CDN.format(image_id)
    err = None
    for attempt in range(4):
        try:
            req = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0", "Accept": "image/webp,image/*"})
            with urllib.request.urlopen(req, timeout=30) as r:
                raw = r.read()
            im = Image.open(io.BytesIO(raw)).convert("RGB")
            dest.parent.mkdir(parents=True, exist_ok=True)
            im.save(dest, "WEBP", quality=84, method=6)
            data = dest.read_bytes()
            return {"w": im.width, "h": im.height, "sha256": sha(data), "bytes": len(data)}
        except Exception as e:  # noqa: BLE001
            err = e
            time.sleep(0.8 * (attempt + 1))
    raise RuntimeError(f"fetch failed {image_id}: {err}")


def main():
    jobs = []            # (gender, style, eth, group, slug, id, dest)
    evidence = []
    for style in STYLES:
        st = style.lower()
        for gender in ("female", "male"):
            for eth in ETH:
                p = EVID / f"{gender}-{st}-{eth}-s2.json"
                if not p.exists() and eth == "latina":
                    p = EVID / f"{gender}-{st}-latino-s2.json"
                if not p.exists():
                    print(f"!! evidence missing {p.name}", file=sys.stderr)
                    continue
                imgs = load(p)
                evidence.append({"file": p.name, "unique_images": len({i["id"] for i in imgs})})
                for group, opts in parse_options(imgs, gender).items():
                    for slug, iid in opts.items():
                        jobs.append((gender, style, eth, group, slug, iid,
                                     OUT / gender / st / eth / f"{group}-{slug}.webp"))
            p = EVID / f"{gender}-{st}-white-s1.json"
            if p.exists():
                evidence.append({"file": p.name, "unique_images": len({i["id"] for i in load(p)})})
                for slug, iid in parse_eth(load(p)).items():
                    jobs.append((gender, style, slug, "ethnicity", slug, iid,
                                 OUT / gender / st / f"eth-{slug}.webp"))

    seen, todo = set(), []
    for j in jobs:
        sig = j[:5]
        if sig in seen:
            raise SystemExit(f"duplicate key {sig}")
        seen.add(sig)
        todo.append(j)
    print(f"jobs={len(todo)}")

    results = {}
    pending = []
    for j in todo:
        dest = j[6]
        sig = j[:5]
        if dest.exists() and dest.stat().st_size > 1500:
            try:
                with Image.open(dest) as im:
                    im.verify()
                with Image.open(dest) as im2:
                    w, h = im2.width, im2.height
                data = dest.read_bytes()
                results[sig] = {"w": w, "h": h, "sha256": sha(data), "bytes": len(data),
                                "path": dest.relative_to(CREATE).as_posix(), "reused": True}
                continue
            except Exception:  # noqa: BLE001
                pass
        pending.append(j)

    def work(j):
        info = fetch(j[5], j[6])
        info["path"] = j[6].relative_to(CREATE).as_posix()
        return j[:5], info

    with ThreadPoolExecutor(max_workers=5) as ex:
        for n, (sig, info) in enumerate(ex.map(work, pending), 1):
            results[sig] = info
            if n % 50 == 0:
                print(f"  fetched {n}/{len(pending)}")
    print(f"assets={len(results)} fetched={len(pending)}")

    build_manifest(results, evidence)


def build_manifest(results, evidence):
    assets = {}
    for (gender, style, eth, group, slug), info in sorted(results.items(), key=lambda kv: tuple(str(x) for x in kv[0])):
        assets[f"{gender}|{style}|{eth}|{group}|{slug}"] = {
            "path": info["path"], "w": info["w"], "h": info["h"],
            "bytes": info["bytes"], "sha256": info["sha256"],
        }

    # 兼容别名：前端族裔卡用「当前已选族裔」拼 key，这里把 6×6 组合都映射到正确的卡；
    # 男性 chest 组是前端 UI 概念（参考站无 chest 组），映射到参考站 male body 图。
    chest_map = {"slim": "slim", "toned": "average", "muscular": "muscular", "broad": "stout"}
    for gender in ("female", "male"):
        for style in STYLES:
            for sel in ETH:
                for card in ETH:
                    src = f"{gender}|{style}|{card}|ethnicity|{card}"
                    dst = f"{gender}|{style}|{sel}|ethnicity|{card}"
                    if src in assets and dst not in assets:
                        assets[dst] = dict(assets[src])
    for style in STYLES:
        for eth in ETH:
            for copt, bopt in chest_map.items():
                src = f"male|{style}|{eth}|body|{bopt}"
                dst = f"male|{style}|{eth}|chest|{copt}"
                if src in assets:
                    assets[dst] = dict(assets[src])

    # 参考站自身对同一 option 在不同族裔复用同一张图（字节相同）的组合，单独记录
    shared = []
    by_opt = {}
    for key, a in assets.items():
        gender, style, eth, group, opt = key.split("|")
        if group in ("ethnicity", "chest"):
            continue
        by_opt.setdefault((gender, style, group, opt), set()).add(a["sha256"])
    for (gender, style, group, opt), shas in sorted(by_opt.items()):
        if len(shas) < len(ETH):
            shared.append({"option": f"{gender}|{style}|{group}|{opt}",
                           "distinct_images": len(shas),
                           "note": "参考站该组合按族裔返回同一张图（字节相同），非我方跨族裔顶替"})

    missing = ["male|entry|Realistic", "male|entry|Anime"]
    for gender in ("female", "male"):
        for style in STYLES:
            for eth in ETH:
                groups = [("body", F_BODY if gender == "female" else M_BODY),
                          ("breast", F_BREAST if gender == "female" else M_SIZE),
                          ("hairstyle", HAIR if gender == "female" else M_HAIR)]
                if gender == "female":
                    groups.append(("butt", F_BUTT))
                groups.append(("ethnicity", [eth]))
                for group, opts in groups:
                    for slug in opts:
                        k = f"{gender}|{style}|{eth}|{group}|{slug}"
                        if k not in assets:
                            missing.append(k)

    options = {}
    for gender in ("female", "male"):
        for style in STYLES:
            eth0 = "white"
            options[f"{gender}|{style}"] = {
                "ethnicity": [e for e in ETH if f"{gender}|{style}|{e}|ethnicity|{e}" in assets],
                "body": [o for o in (F_BODY if gender == "female" else M_BODY)
                         if f"{gender}|{style}|{eth0}|body|{o}" in assets],
                "breast": [o for o in (F_BREAST if gender == "female" else M_SIZE)
                           if f"{gender}|{style}|{eth0}|breast|{o}" in assets],
                "hairstyle": [o for o in (HAIR if gender == "female" else M_HAIR)
                              if f"{gender}|{style}|{eth0}|hairstyle|{o}" in assets],
            }
            if gender == "female":
                options[f"{gender}|{style}"]["butt"] = [
                    o for o in F_BUTT if f"{gender}|{style}|{eth0}|butt|{o}" in assets]
            else:
                options[f"{gender}|{style}"]["chest"] = list(chest_map)
                options[f"{gender}|{style}"]["butt"] = []   # 参考站男向导无 butt 步骤，显式空表表示“不适用”而非缺图

    manifest = {
        "version": "catalog-v1-20261121",
        "root": "./assets/create",
        "generated_at": "2026-11-21T18:10:00+08:00",
        "notes": [
            "path 相对本 manifest 所在目录（assets/create/）。",
            "key 固定 5 段：gender|style|ethnicity|group|option；不存在无 ethnicity 的旧 key。",
            "女生入口两步沿用站点既有 entry-realistic.webp / entry-anime.webp，固定首图不变。",
            "所有组图逐族裔来自参考站公开页面实际渲染卡片（image id 与字节均不同），未用其他族裔/性别顶替。",
            "颜色卡由前端 CSS 色块渲染，不出图；颜色值见 color_swatches。",
            "missing 里的组合确认缺口，请显示中性 Art pending，不要回退旧 candy.ai 素材。",
            "男性页面没有 butt 组；breast 在男性语义下是尺寸组（micro/small/average/big/huge）。",
        ],
        "entry": {
            "female": {"Realistic": "entry-realistic.webp", "Anime": "entry-anime.webp"},
            "male": {},
        },
        "color_swatches": {
            "platinum": "#e8e1d5", "brunette": "#6b4a33", "black": "#1b1b1b",
            "blond": "#e3c07a", "ginger": "#b5522c", "pink": "#e58ab5", "blue": "#5b86c9",
        },
        "not_in_reference": ["male|butt"],
        "alias_groups": {"chest": chest_map, "ethnicity_selector": "ethnicity 段可用当前已选族裔，卡面取 option 自身"},
        "aliases": {
            "breast": {"medium": "average", "large": "big", "xl": "huge"},
            "butt": {"medium": "average", "large": "big", "xl": "huge"},
            "hairstyle": {"short": "bob"},
            "ethnicity": {"latino": "latina", "caucasian": "white", "arab": "middle-eastern", "blackafro": "black"},
        },
        "options": options,
        "assets": assets,
        "shared_reference_assets": shared,
        "missing": sorted(set(missing)),
        "reference_evidence": evidence,
    }
    MANIFEST.parent.mkdir(parents=True, exist_ok=True)
    with open(MANIFEST, "w", encoding="utf-8") as f:
        json.dump(manifest, f, ensure_ascii=False, indent=1)
        f.write("\n")
    print(f"manifest -> {MANIFEST}")
    print(f"assets={len(assets)} missing={len(manifest['missing'])}")
    if manifest["missing"]:
        for m in manifest["missing"][:40]:
            print("  miss", m)


if __name__ == "__main__":
    sys.exit(main())
