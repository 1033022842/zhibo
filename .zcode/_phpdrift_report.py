#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""逐文件判断漂移方向：线上是「仓库旧版」还是「线上独有改动」。

判据（保守，仅作人工复核的输入，不做自动决策）：
  repo_only_additions   —— 仓库里新增的、线上完全没有的关键标识符数量（说明仓库更新）
  live_only_additions   —— 线上有、仓库没有的关键标识符（说明线上独有改动）
其中「关键标识符」= 函数名 / 类名 / 关键字符串常量 / 方法调用。

用法: python .zcode/_phpdrift_report.py [site]
"""
import json
import os
import re
import subprocess
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUT = os.path.join(os.path.dirname(ROOT), "_phpdrift")

TOKEN_RE = re.compile(r"[A-Za-z_][A-Za-z0-9_]{3,}")


def tokens(text):
    return set(TOKEN_RE.findall(text))


def repo_text(rel):
    r = subprocess.run(["git", "show", f"HEAD:{rel}"], capture_output=True, cwd=ROOT)
    return r.stdout.decode("utf-8", "replace") if r.returncode == 0 else None


def main():
    site = sys.argv[1] if len(sys.argv) > 1 else None
    dfile = os.path.join(OUT, "_classify.json")
    cls = json.load(open(dfile, encoding="utf-8"))
    sites = [site] if site else list(cls.keys())

    for s in sites:
        cats = cls.get(s, {})
        targets = []
        for k in ("custom_or_remote_newer", "repo_longer"):
            for rel in cats.get(k, []):
                targets.append((k, rel))
        if not targets:
            continue
        print("=" * 78)
        print(f"{s}: {len(targets)} 个需判断方向的差异文件")
        for kind, rel in targets:
            lp = os.path.join(OUT, s, rel[len("php/"):].replace("/", os.sep))
            if not os.path.isfile(lp):
                continue
            live = open(lp, encoding="utf-8", errors="replace").read()
            repo = repo_text(rel)
            if repo is None:
                print(f"  [{kind}] {rel}  (仓库无此文件)")
                continue
            rt, lt = tokens(repo), tokens(live)
            only_repo = rt - lt
            only_live = lt - rt
            # 过滤掉品牌词与纯数字噪声，只看有意义的标识符
            noise = {"Sugus", "sugus", "heygirl", "kelymi", "Kelymi", "kisss", "Kisss",
                     "live", "chat", "true", "false", "null", "string", "return", "function"}
            only_repo = {t for t in only_repo if not t.isdigit()} - noise
            only_live = {t for t in only_live if not t.isdigit()} - noise
            flag = "← 仓库更新" if len(only_repo) > len(only_live) * 2 else "← 线上独有"
            print(f"\n  [{kind}] {rel}   {flag}")
            print(f"      仓库独有 {len(only_repo)}: {sorted(only_repo)[:12]}")
            print(f"      线上独有 {len(only_live)}: {sorted(only_live)[:12]}")


if __name__ == "__main__":
    main()
