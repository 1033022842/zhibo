#!/usr/bin/env python3
# -*- coding: utf-8 -*-
r"""chat_gift_staging.py —— 把「AI 电脑导出的角色素材」整理成可直接发布的 staging 目录

输入（默认）：
  角色目录   D:\ever\douyin\_roles2\<角色名>\*.png + <礼物中文名>.mp4
  礼物图标   C:\Users\a1033\Pictures\礼物图片\<礼物中文名>.jpg

输出：
  .zcode/_staging/chat_gifts/icons/<gift_key>.jpg
  .zcode/_staging/chat_gifts/<role_key>/persona.png
  .zcode/_staging/chat_gifts/<role_key>/persona_800.jpg
  .zcode/_staging/chat_gifts/<role_key>/<gift_key>.mp4
  .zcode/_staging/manifest.json      # 本次实际产出的清单（供上传步骤消费）

角色名 → role_key 需要一份映射：默认从服务器 lp_chat_gift_role 拉取缓存到
.zcode/_staging/role_map.json；也可用 --role-map 指定本地 JSON（{"Bonnie": "home-1", ...}）。

用法：
  python .zcode/chat_gift_staging.py --roles-dir D:/ever/douyin/_roles2 --icons-dir "C:/Users/a1033/Pictures/礼物图片"
  python .zcode/chat_gift_staging.py --only Aiko,Bonnie        # 只处理指定角色
"""
import argparse
import json
import os
import shutil
import subprocess
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
REPO = os.path.dirname(HERE)
STAGING = os.path.join(HERE, "_staging")

# 礼物中文名 → gift_key。新增礼物在这里加一行即可（顺序 = 面板排序）
GIFT_MAP = {
    "玫瑰": "rose",
    "单支玫瑰": "rose_single",
    "戒指": "ring",
    "熊": "bear",
    "内衣": "lingerie",
    "手铐": "cuffs",
    "丝袜": "stockings",
    "手机": "phone",
    "项链": "necklace",
    "香水": "perfume",
    "高跟鞋": "heels",
}


def sh(cmd):
    r = subprocess.run(cmd, shell=True, capture_output=True)
    return r.returncode, r.stdout.decode("utf-8", "replace"), r.stderr.decode("utf-8", "replace")


def load_role_map(path):
    with open(path, encoding="utf-8") as f:
        return json.load(f)


def make_persona(src_png, out_dir):
    """persona.png 原图保留；persona_800.jpg 按 600x800 长边裁切（与线上历史一致）"""
    dst_png = os.path.join(out_dir, "persona.png")
    shutil.copy2(src_png, dst_png)
    dst_jpg = os.path.join(out_dir, "persona_800.jpg")
    rc, _, err = sh(
        f'ffmpeg -y -v error -i "{dst_png}" '
        f'-vf "scale=600:800:force_original_aspect_ratio=increase,crop=600:800" '
        f'-q:v 3 "{dst_jpg}"'
    )
    if rc != 0 or not os.path.isfile(dst_jpg):
        raise RuntimeError(f"persona_800 生成失败: {err[:300]}")
    return dst_jpg


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--roles-dir", default=r"D:\ever\douyin\_roles2")
    ap.add_argument("--icons-dir", default=r"C:\Users\a1033\Pictures\礼物图片")
    ap.add_argument("--role-map", default=os.path.join(STAGING, "role_map.json"))
    ap.add_argument("--only", default="")
    args = ap.parse_args()

    if not os.path.isfile(args.role_map):
        sys.exit(f"缺少角色映射文件：{args.role_map}\n"
                 f"先执行：python .zcode/chat_gift_staging.py --dump-role-map")
    role_map = load_role_map(args.role_map)
    only = {s.strip() for s in args.only.split(",") if s.strip()}

    site_dir = os.path.join(STAGING, "chat_gifts")
    icons_dir = os.path.join(site_dir, "icons")
    os.makedirs(icons_dir, exist_ok=True)

    manifest = {"roles": {}, "icons": [], "missing": []}

    # 图标
    for cn, key in GIFT_MAP.items():
        src = os.path.join(args.icons_dir, f"{cn}.jpg")
        dst = os.path.join(icons_dir, f"{key}.jpg")
        if os.path.isfile(src):
            shutil.copy2(src, dst)
            manifest["icons"].append(key)
        elif not os.path.isfile(dst):
            manifest["missing"].append(f"icon:{cn}")

    # 角色
    for name in sorted(os.listdir(args.roles_dir)):
        src_dir = os.path.join(args.roles_dir, name)
        if not os.path.isdir(src_dir):
            continue
        if only and name not in only:
            continue
        role_key = role_map.get(name)
        if not role_key:
            manifest["missing"].append(f"role-map:{name}")
            continue

        out_dir = os.path.join(site_dir, role_key)
        os.makedirs(out_dir, exist_ok=True)

        pngs = [f for f in os.listdir(src_dir) if f.lower().endswith(".png")]
        if not pngs:
            manifest["missing"].append(f"persona:{name}")
            continue
        make_persona(os.path.join(src_dir, pngs[0]), out_dir)

        gifts = []
        for cn, key in GIFT_MAP.items():
            src = os.path.join(src_dir, f"{cn}.mp4")
            if not os.path.isfile(src):
                manifest["missing"].append(f"{role_key}:{cn}.mp4")
                continue
            shutil.copy2(src, os.path.join(out_dir, f"{key}.mp4"))
            gifts.append(key)
        manifest["roles"][role_key] = {"name": name, "gifts": gifts}
        print(f"  {role_key:8s} {name:14s} gifts={len(gifts)}")

    with open(os.path.join(STAGING, "manifest.json"), "w", encoding="utf-8") as f:
        json.dump(manifest, f, ensure_ascii=False, indent=2)

    print(f"\n角色 {len(manifest['roles'])}，图标 {len(manifest['icons'])}，缺失 {len(manifest['missing'])}")
    if manifest["missing"]:
        print("缺失明细：")
        for m in manifest["missing"]:
            print("  -", m)


if __name__ == "__main__":
    main()
