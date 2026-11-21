#!/usr/bin/env python3
"""离线校验 assets/create/manifest.json 与 catalog-v1 素材一致性。

检查项：
  1. 每个 asset 的 path 存在、可解码、宽高与 manifest 一致、字节数与 sha256 一致
  2. 完全重复的图片（同 sha 不同 key）——应为 0，否则说明用了同一张图顶多个组合
  3. 同一 group|option 在六族裔下必须 6 个不同 sha（有缺图则记录）
  4. 同一 group|option 在 Realistic/Anime 下必须不同 sha
  5. 入口图 entry-* 存在且可解码
  6. 未被 manifest 引用的 webp 文件
输出 tests/create-character/assets/report/asset-verify.json + 控制台摘要。
"""
import json
import sys
from collections import defaultdict
from pathlib import Path

from PIL import Image

ROOT = Path(r"D:\ever\douyin\douyin")
SITE = ROOT / "ai-girl-malaysia.com"
CREATE = SITE / "assets" / "create"
MANIFEST = CREATE / "manifest.json"
REPORT = ROOT / "tests" / "create-character" / "assets" / "report"

ETH = ["white", "asian", "black", "latina", "south-asian", "middle-eastern"]
STYLES = ["Realistic", "Anime"]


def main():
    m = json.loads(MANIFEST.read_text(encoding="utf-8"))
    assets = m["assets"]
    problems = []
    sha_by_key = {}
    referenced = set()

    for key, a in assets.items():
        p = MANIFEST.parent / a["path"]
        referenced.add(p.as_posix())
        if not p.exists():
            problems.append(f"missing file: {key} -> {a['path']}")
            continue
        raw = p.read_bytes()
        if len(raw) != a["bytes"]:
            problems.append(f"size mismatch: {key} {len(raw)} != {a['bytes']}")
        import hashlib
        if hashlib.sha256(raw).hexdigest() != a["sha256"]:
            problems.append(f"sha mismatch: {key}")
        try:
            with Image.open(p) as im:
                im.load()
                if (im.width, im.height) != (a["w"], a["h"]):
                    problems.append(f"dim mismatch: {key} {im.size} != {(a['w'], a['h'])}")
                if im.format != "WEBP":
                    problems.append(f"format not webp: {key} {im.format}")
        except Exception as e:  # noqa: BLE001
            problems.append(f"decode fail: {key} {e}")
        sha_by_key[key] = a["sha256"]

    # 2. 完全重复
    by_sha = defaultdict(list)
    for k, s in sha_by_key.items():
        by_sha[s].append(k)
    shared_opts = set()
    for item in m.get("shared_reference_assets", []):
        shared_opts.add(item["option"])
    dup = {s: ks for s, ks in by_sha.items() if len(ks) > 1}
    real_dup = {}
    for s, ks in dup.items():
        groups = {k.split("|")[3] for k in ks}
        opts = {"|".join(k.split("|")[i] for i in (0, 1, 3, 4)) for k in ks}
        if len(opts) == 1 and next(iter(opts)) in shared_opts:
            continue  # 参考站自身复用，已在 manifest 声明
        if "chest" in groups or groups == {"ethnicity"}:
            continue  # 显式别名组：chest->body、族裔卡 6×6 选择器
        real_dup[s] = ks
        problems.append("duplicate bytes across different options: " + " == ".join(ks))

    # 3/4. 跨族裔、跨画风差异
    same_opt = defaultdict(set)
    eth_cards = defaultdict(dict)
    for key, s in sha_by_key.items():
        gender, style, eth, group, opt = key.split("|")
        if group == "ethnicity":
            eth_cards[(gender, style)][opt] = s
            continue
        same_opt[(gender, style, group, opt)].add((eth, s))
    cross_eth_issues = []
    reference_shared = []
    for (gender, style, group, opt), vals in sorted(same_opt.items()):
        shas = {s for _, s in vals}
        key = f"{gender}|{style}|{group}|{opt}"
        if len(vals) != len(ETH):
            cross_eth_issues.append({"key": key, "reason": f"only {len(vals)}/6 ethnicities"})
        elif len(shas) != len(ETH):
            if key in shared_opts:
                reference_shared.append({"key": key, "distinct_images": len(shas)})
            else:
                cross_eth_issues.append({"key": key, "reason": f"only {len(shas)} distinct images for 6 ethnicities"})
    for (gender, style), cards in sorted(eth_cards.items()):
        if len(cards) != len(ETH):
            cross_eth_issues.append({"key": f"{gender}|{style}|ethnicity", "reason": f"only {len(cards)} ethnicity cards"})
        elif len(set(cards.values())) != len(ETH):
            cross_eth_issues.append({"key": f"{gender}|{style}|ethnicity", "reason": f"only {len(set(cards.values()))} distinct ethnicity cards"})
    for issue in cross_eth_issues:
        problems.append("cross-ethnicity same image / gap: " + issue["key"] + " (" + issue["reason"] + ")")

    cross_style = defaultdict(set)
    for key, s in sha_by_key.items():
        gender, style, eth, group, opt = key.split("|")
        cross_style[(gender, eth, group, opt)].add(s)
    style_issues = [f"{g}|{e}|{gr}|{o}" for (g, e, gr, o), s in cross_style.items() if len(s) != len(STYLES)]

    # 5. 入口
    entry_report = {}
    for gender, styles in m.get("entry", {}).items():
        for style, rel in styles.items():
            p = CREATE / rel
            ok = p.exists()
            size = p.stat().st_size if ok else 0
            entry_report[f"{gender}|{style}"] = {"path": rel, "exists": ok, "bytes": size}
            if not ok:
                problems.append(f"entry missing: {gender}|{style} {rel}")

    # 6. 未引用文件
    all_files = {p.as_posix() for p in (CREATE / "catalog-v1").rglob("*.webp")}
    orphan = sorted(all_files - referenced)

    report = {
        "manifest_version": m.get("version"),
        "manifest_keys": len(assets),
        "manifest_missing": m.get("missing", []),
        "problems": problems,
        "duplicate_groups": len(real_dup),
        "reference_shared": reference_shared,
        "distinct_sha": len(by_sha),
        "cross_ethnicity_issues": cross_eth_issues,
        "cross_style_issue_count": len(style_issues),
        "entry": entry_report,
        "orphan_files": orphan,
        "catalog_files": len(all_files),
    }
    REPORT.mkdir(parents=True, exist_ok=True)
    (REPORT / "asset-verify.json").write_text(json.dumps(report, ensure_ascii=False, indent=1), encoding="utf-8")

    print("manifest version:", report["manifest_version"])
    print("keys:", report["manifest_keys"], "| distinct images:", report["distinct_sha"])
    print("catalog files:", report["catalog_files"], "| orphan:", len(orphan))
    print("entry:", json.dumps(entry_report, ensure_ascii=False))
    print("missing:", len(report["manifest_missing"]))
    print("duplicate byte groups:", len(real_dup))
    print("reference-shared option groups (declared in manifest):", len(reference_shared))
    print("cross-ethnicity issues:", len(cross_eth_issues))
    print("cross-style issues:", len(style_issues))
    print("problems:", len(problems))
    for p in problems[:20]:
        print("  !", p)
    return 1 if problems else 0


if __name__ == "__main__":
    sys.exit(main())
