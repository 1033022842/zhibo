#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""把 _phpdrift/<site>/ 下的远端文件与仓库版本做 diff，自动归类。

分类规则（按优先级）：
  brand_only   —— 差异全部是品牌替换（Sugus->客户名 / 域名 / 邮箱），apply_brand 的产物
  whitespace   —— 忽略空白后完全一致
  repo_newer   —— 仓库里有远端没有的新增逻辑（远端是旧版），可安全覆盖
  custom       —— 其它：线上有仓库没有的实际逻辑，必须人工确认

用法: python .zcode/_phpdrift_classify.py [site...]
"""
import json
import os
import re
import subprocess
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUT = os.path.join(os.path.dirname(ROOT), "_phpdrift")

BRAND_SUBS = {
    "heygirl": [
        (r"heygirl\.live", "Sugus.ai"), (r"heygirl\.chat", "Sugus.ai"), (r"heygirl\.me", "Sugus.ai"),
        (r"Heey?girl\.live", "Sugus.ai"), (r"\bheygirl\b", "Sugus"),
        (r"media\.heygirl\.live", "media.sugus.ai"),
        (r"support@heygirl\.live", "support@sugusmail.ai"),
    ],
    "kelymi": [
        (r"kelymi\.autos", "Sugus.ai"), (r"kelymi\.live", "Sugus.ai"),
        (r"kelymi\.chat", "Sugus.ai"), (r"kelymi\.me", "Sugus.ai"),
        (r"\bKelymi\b", "Sugus"), (r"\bkelymi\b", "sugus"),
        (r"media\.kelymi\.autos", "media.sugus.ai"),
        (r"support@kelymi\.autos", "support@sugusmail.ai"),
    ],
    "kiss": [
        (r"kisss\.ai", "Sugus.ai"), (r"\bKisss\b", "Sugus"), (r"\bkisss\b", "sugus"),
        (r"media\.kisss\.ai", "media.sugus.ai"),
        (r"support@kisss\.ai", "support@sugusmail.ai"),
    ],
    "sugus": [],
}


def repo_text(rel):
    r = subprocess.run(["git", "show", f"HEAD:{rel}"], capture_output=True, cwd=ROOT)
    return r.stdout.decode("utf-8", "replace") if r.returncode == 0 else None


def normalize_brand(text, site):
    """把远端文本里的客户品牌还原成上游中性写法，便于与仓库比较"""
    for pat, rep in BRAND_SUBS.get(site, []):
        text = re.sub(pat, rep, text, flags=re.I)
    return text


def squash(text):
    """去掉所有空白，只看字符序列"""
    return re.sub(r"\s+", "", text)


def main():
    sites = sys.argv[1:] or ["heygirl", "kelymi", "kiss", "sugus"]
    report = {}
    for site in sites:
        dfile = os.path.join(OUT, site, "_drift.json")
        if not os.path.isfile(dfile):
            print(f"== {site}: 无 _drift.json，跳过")
            continue
        drift = json.load(open(dfile, encoding="utf-8"))
        cats = {}
        for rel, info in drift.items():
            if info["status"] != "differs":
                continue
            local_path = os.path.join(OUT, site, rel[len("php/"):].replace("/", os.sep))
            if not os.path.isfile(local_path):
                cats.setdefault("missing_local_copy", []).append(rel)
                continue
            remote = open(local_path, encoding="utf-8", errors="replace").read()
            repo = repo_text(rel)
            if repo is None:
                cats.setdefault("repo_missing", []).append(rel)
                continue

            if squash(remote) == squash(repo):
                cats.setdefault("whitespace", []).append(rel)
            elif squash(normalize_brand(remote, site)) == squash(repo):
                cats.setdefault("brand_only", []).append(rel)
            elif squash(normalize_brand(remote, site)) == squash(normalize_brand(repo, site)):
                cats.setdefault("brand_only", []).append(rel)
            else:
                # 判断方向：仓库版本里是否包含远端没有的行块
                nr = squash(normalize_brand(repo, site))
                nr_remote = squash(normalize_brand(remote, site))
                if len(nr) > len(nr_remote):
                    cats.setdefault("repo_longer", []).append(rel)
                else:
                    cats.setdefault("custom_or_remote_newer", []).append(rel)
        report[site] = cats
        print(f"== {site}")
        for k, v in sorted(cats.items(), key=lambda x: -len(x[1])):
            print(f"   {k}: {len(v)}")
            if k in ("custom_or_remote_newer", "repo_missing", "missing_local_copy"):
                for f in v:
                    print(f"      {f}")

    with open(os.path.join(OUT, "_classify.json"), "w", encoding="utf-8") as f:
        json.dump(report, f, ensure_ascii=False, indent=1)
    print(f"\n报告: {os.path.join(OUT, '_classify.json')}")


if __name__ == "__main__":
    main()
