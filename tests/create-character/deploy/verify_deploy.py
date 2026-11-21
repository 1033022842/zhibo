#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""部署后验证：四站 Create Character v8 是否已上线。

检查：页面 200 + 引 v8 脚本 + manifest 含男版入口 + 抽一张 catalog 图 200 + 品牌词。
用法：python tests/create-character/deploy/verify_deploy.py [--only heygirl]
"""
import argparse
import json
import ssl
import sys
import urllib.request

SITES = {
    "heygirl": {"base": "https://heygirl.live", "brand": "heygirl"},
    "sugus": {"base": "http://172.81.98.55", "brand": "Sugus"},
    "kelymi": {"base": "https://kelymi.autos", "brand": "Kelymi"},
    "kiss": {"base": "https://kisss.ai", "brand": "Kiss"},
}
CTX = ssl.create_default_context()
CTX.check_hostname = False
CTX.verify_mode = ssl.CERT_NONE
UA = "Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Safari/604.1"


def get(url, timeout=20):
    req = urllib.request.Request(url, headers={"User-Agent": UA, "Cache-Control": "no-cache"})
    with urllib.request.urlopen(req, timeout=timeout, context=CTX) as r:
        return r.status, r.read()


def check(name, cfg):
    base = cfg["base"]
    out = {"site": name, "base": base, "checks": [], "ok": True}

    def add(label, ok, detail=""):
        out["checks"].append({"label": label, "ok": bool(ok), "detail": detail})
        if not ok:
            out["ok"] = False

    page_url = base + "/ai/createCharacter.html"
    try:
        st, body = get(page_url)
        html = body.decode("utf-8", "replace")
        add("页面可达", st == 200, f"HTTP {st} {len(body)}B")
        add("引 v8 脚本", "create-character-v8.js?v=20261121v8d" in html,
            "create-character-v8.js?v=20261121v8d" if "create-character-v8.js?v=20261121v8d" in html else "未找到")
        add("含 #cc8Root", 'id="cc8Root"' in html)
        add("旧脚本已移出静态标签", 'src="./js/createCharacter.js"' not in html)
        add("品牌词", cfg["brand"] in html, cfg["brand"])
    except Exception as e:
        add("页面可达", False, repr(e))
        return out

    mf_url = base + "/ai/assets/create/manifest.json"
    try:
        st, body = get(mf_url)
        m = json.loads(body.decode("utf-8"))
        add("manifest 可达", st == 200, f"HTTP {st} {len(body)}B")
        male = (m.get("entry") or {}).get("male") or {}
        add("男版入口 Realistic", "Realistic" in male, str(male.get("Realistic")))
        add("男版入口 Anime", "Anime" in male, str(male.get("Anime")))
        add("missing 为空", not m.get("missing"), str(m.get("missing")))
        add("key 数 684", len(m.get("assets") or {}) == 684, str(len(m.get("assets") or {})))
    except Exception as e:
        add("manifest 可达", False, repr(e))

    for path in (
        "/ai/assets/create/entry-male-realistic.webp",
        "/ai/assets/create/catalog-v1/female/anime/asian/body-slim.webp",
        "/ai/js/create-character-v8.js?v=20261121v8d",
        "/ai/js/createCharacter-v8.css?v=20261121v8d",
    ):
        try:
            st, body = get(base + path)
            add(path.split("/")[-1], st == 200 and len(body) > 1000, f"HTTP {st} {len(body)}B")
        except Exception as e:
            add(path.split("/")[-1], False, repr(e))
    return out


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--only", default="")
    ap.add_argument("--json", default="")
    a = ap.parse_args()
    names = [a.only] if a.only else list(SITES)
    results = []
    for n in names:
        r = check(n, SITES[n])
        results.append(r)
        print(f"\n=== {n} {'PASS' if r['ok'] else 'FAIL'} ({r['base']})")
        for c in r["checks"]:
            print(f"  [{'ok' if c['ok'] else 'XX'}] {c['label']}  {c['detail']}")
    if a.json:
        with open(a.json, "w", encoding="utf-8") as f:
            json.dump(results, f, ensure_ascii=False, indent=1)
    bad = [r["site"] for r in results if not r["ok"]]
    print("\nSUMMARY:", "ALL PASS" if not bad else "FAIL " + ",".join(bad))
    return 1 if bad else 0


if __name__ == "__main__":
    sys.exit(main())
