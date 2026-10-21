#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""verify_client.py —— 客户站同步后的硬验证（sync_clients.py 的配套工具）

为什么需要它：sync_clients.py 的「线上验证」只看页面里有没有品牌关键词，
曾经因为「推送落进嵌套目录、线上读旧文件」而假通过（2026-10-18）。
本脚本改用**内容级证据**判断：

  blob    未被品牌脚本触碰的文件（assets/ _shared/）与上游 git blob 逐字节一致
  shared  _shared/ 目录存在且内页真的引用了它（漏推会整页 JS/CSS 404）
  title   <title> 含预期品牌
  logo    logo 文案是客户品牌
  leak    没有高信号的上游品牌残留（Sugus.ai / support@sugusmail.ai / www.sugus.ai …）
  junk    站点目录下没有 sync 早期 bug 留下的嵌套目录 site/ai-girl-malaysia.com/
  region  没有撞上区域封锁 403 页（sugus 有 if($is_cn) 规则，会伪装成「旧内容」）
  --payment  MKPAY 配置段 / 回调路由 / 网关连通（可选，后端改动后跑）

用法：
  python .zcode/verify_client.py --client heygirl
  python .zcode/verify_client.py --client sugus --payment
  python .zcode/verify_client.py --all
退出码 0 = 全过；1 = 有 FAIL。
"""
import argparse, os, posixpath, re, ssl, subprocess, sys, urllib.request

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from sync_clients import CLIENTS, SITE_DIR, REPO, connect  # noqa: E402

# 上游品牌脚本故意保留的标识符（不是泄漏，别误报）
ALLOWED_LEAK = re.compile(
    r"cdn\.sugus\.ai|sugus\.ai-[0-9a-f]{16,}|SugusDialog|SugusTrack|try_sugus_banner"
    r"|SUGUS_BRAND|__sugusPromo\w*|allow-permissions-sugus"
)
# 高信号泄漏：出现即说明品牌没打上
HARD_LEAK = re.compile(r"Sugus\.ai|support@sugusmail\.ai|Sugus AI|>Sugus<|//(www\.)?sugus\.ai/(?!ai/assets)")
REGION_BLOCK = "not available in your region"

class Result:
    def __init__(self, name):
        self.name, self.items = name, []
    def add(self, ok, label, detail=""):
        self.items.append((ok, label, detail))
        print(f"  {'PASS' if ok else 'FAIL'}  {label}" + (f"  — {detail}" if detail else ""))
    @property
    def ok(self):
        return all(i[0] for i in self.items)

def _normalize(d):
    """文本去掉 CRLF 差异（仓库 blob 存 LF），二进制必须保持原字节"""
    return d.replace(b"\r\n", b"\n") if b"\x00" not in d[:8192] else d

def git_blob(path):
    """工作区文件（LF 归一后）的 git blob hash；与 git ls-tree 的 hash 同口径"""
    try:
        with open(path, "rb") as f:
            d = _normalize(f.read())
        h = subprocess.run(["git", "hash-object", "--stdin"], input=d, capture_output=True, cwd=REPO)
        return h.stdout.decode().strip()
    except OSError:
        return None

def tracked_blob(rel):
    r = subprocess.run(["git", "ls-tree", "HEAD", "--", rel], capture_output=True, cwd=REPO)
    parts = r.stdout.decode().split()
    return parts[2] if len(parts) >= 3 else None

PROBE = r'''
import hashlib, sys
for p in sys.argv[1:]:
    try:
        d = open(p, 'rb').read()
    except Exception:
        print('MISSING', p); continue
    if b'\x00' not in d[:8192]:
        d = d.replace(b'\r\n', b'\n')
    print(p, hashlib.sha1(b'blob %d\x00' % len(d) + d).hexdigest())
'''

def run(cli, cmd, timeout=60):
    _, out, err = cli.exec_command(cmd, timeout=timeout)
    o = out.read().decode("utf-8", "replace").strip()
    e = err.read().decode("utf-8", "replace").strip()
    return o, e

def remote_sh(cmd, via):
    """跳板客户：命令要在目标机上跑，套一层 ssh"""
    if not via:
        return cmd
    tgt, key = via
    return (f"ssh -i {key} -o StrictHostKeyChecking=no -o BatchMode=yes {tgt} "
            f"{shlex_quote(cmd)}")

def shlex_quote(s):
    return "'" + s.replace("'", "'\"'\"'") + "'"

def remote_probe(cli, site, rels, via_jump):
    """在目标机上算一组文件的 git blob hash"""
    with cli.open_sftp().open("/tmp/_verify_probe.py", "w") as f:
        f.write(PROBE)
    args = " ".join(f"{posixpath.join(site, r)}" for r in rels)
    if via_jump:
        # cli 是跳板，探针要先送到目标机再执行
        j = cli
        tgt, key = via_jump
        j.exec_command(f"scp -q -i {key} -o StrictHostKeyChecking=no -o BatchMode=yes "
                       f"/tmp/_verify_probe.py {tgt}:/tmp/")[1].channel.recv_exit_status()
        cmd = (f"ssh -i {key} -o StrictHostKeyChecking=no -o BatchMode=yes {tgt} "
               f"'python3 /tmp/_verify_probe.py {args}'")
        o, _ = run(j, cmd, 120)
    else:
        o, _ = run(cli, f"python3 /tmp/_verify_probe.py {args}", 120)
    got = {}
    for line in o.splitlines():
        p = line.split()
        if len(p) == 2:
            got[p[0]] = p[1]
    return got

def http_body(cfg, path, cli=None):
    """取页面正文。sugus 走跳板 curl（大陆 IP 会被 403 区域封锁）"""
    url = (cfg.get("verify_url") or "").split("/ai/")[0].rstrip("/") + path
    if cli is not None:
        o, _ = run(cli, f"curl -sL --max-time 20 '{url}'", 40)
        return url, o
    ctx = ssl.create_default_context()
    ctx.check_hostname = False
    ctx.verify_mode = ssl.CERT_NONE
    req = urllib.request.Request(f"{url}?v={os.getpid()}", headers={"User-Agent": "Mozilla/5.0"})
    try:
        return url, urllib.request.urlopen(req, timeout=20, context=ctx).read().decode("utf-8", "replace")
    except Exception as e:
        return url, f"__ERROR__ {e}"

def verify(name, cfg, payment=False):
    print(f"\n===== [{name}] 硬验证 =====")
    res = Result(name)
    jump = cfg.get("jump")
    cli = connect(jump) if jump else connect(cfg)
    site = cfg["site"]
    via = (f'{cfg["user"]}@{cfg["host"]}', cfg["key_on_jump"]) if jump else None
    if jump:
        # 跳板上跑 curl 更真实（外围网络），HTTP 检查都用它
        http_cli = cli
    else:
        http_cli = None

    # ① blob：所有不该被品牌脚本触碰的文件（assets/ 与 _shared/）全量比对。
    #    固定小样本会漏掉漂移（曾只取 assets 前 5 个，改 fy.css 检不出来）。
    ls = subprocess.run(["git", "ls-tree", "-r", "--name-only", "HEAD", "--", f"{SITE_DIR}/_shared",
                         f"{SITE_DIR}/assets"], capture_output=True, cwd=REPO).stdout.decode().splitlines()
    excluded = set(cfg.get("exclude", []))
    rels = [p[len(SITE_DIR) + 1:] for p in ls
            if not p.endswith((".html", ".json")) and p not in excluded]
    shared = [r for r in rels if r.startswith("_shared/")]
    assets = [r for r in rels if r.startswith("assets/")]
    got = remote_probe(cli, site, shared + assets, via)
    bad = []
    for r in shared + assets:
        want = tracked_blob(f"{SITE_DIR}/{r}")
        have = got.get(posixpath.join(site, r))
        if not have or have == "MISSING":
            bad.append(f"{r}(缺失)")
        elif want and have != want:
            bad.append(f"{r}(内容不符)")
    total = len(shared) + len(assets)
    res.add(not bad, f"blob 一致性（{total} 个文件全量）",
            f"{len(bad)} 个不一致: " + ", ".join(bad[:4]) if bad else "与上游逐字节一致")

    # ② _shared 真实可达（内页引用 + 文件在）
    home, body = http_body(cfg, "/ai/characters.html", http_cli)
    if "__ERROR__" in body:
        res.add(False, "页面抓取", body[:80])
    elif REGION_BLOCK in body:
        res.add(False, "区域封锁", f"{home} 返回 403 地域页 —— 换个出口验证，别当旧内容")
        body = ""
    else:
        refs = body.count("_shared/")
        res.add(refs > 0 and len(shared) > 0, "_shared 落地", f"内页引用 {refs} 处 / 目录 {len(shared)} 个文件")

    if body:
        # ③④ 品牌
        _, home_body = http_body(cfg, "/ai/Home.html", http_cli)
        title = re.search(r"<title>([^<]{0,80})", home_body)
        logo = re.search(r'class="logo">([^<]{0,32})<em>([^<]{0,8})', home_body)
        expect = cfg.get("verify_expect") or ""
        res.add(bool(title) and expect.lower() in title.group(1).lower(),
                "标题品牌", title.group(1) if title else "(无 title)")
        res.add(bool(logo) and expect.lower() in (logo.group(1) + logo.group(2)).lower(),
                "logo 品牌", f"{logo.group(1)}{logo.group(2)}" if logo else "(未匹配 class=logo)")

        # ⑤ 高信号上游品牌残留（只对「打过品牌」的客户有意义；sugus 自己就是上游品牌）
        if cfg.get("brand"):
            hits = [m.group(0) for m in HARD_LEAK.finditer(home_body)]
            res.add(not hits, "品牌泄漏", ", ".join(sorted(set(hits))[:4]) if hits else "无 Sugus 高信号残留")
            soft = [m.group(0) for m in ALLOWED_LEAK.finditer(home_body)]
            if soft:
                print(f"  note  保留的上游标识符（正常）: {', '.join(sorted(set(soft))[:4])}")
        else:
            print("  note  该客户无 brand kit（自己就是上游品牌），跳过泄漏检查")

        # ⑥ 嵌套垃圾目录（早期 upload 双前缀 bug 的遗迹）
        o, _ = run(cli, remote_sh(f"ls -d {site}/{SITE_DIR} 2>/dev/null | head -1", via), 60)
        res.add(not o.strip(), "无嵌套目录", o.strip() or "站点根干净")

    # ⑦ 支付后端（可选）
    if payment:
        php = "/www/wwwroot/douyin/php"
        # awk 直接取 [MKPAY] 段，避开 grep 正则在中转 shell 里的转义问题；
        # SIGNKEY 必须打码（等号两侧可能有空格）
        env_cmd = ("awk '/^\\[MKPAY\\]/{f=1} f&&/^\\[/&&!/MKPAY/{f=0} f' " + php + "/.env "
                   "| sed -E 's/(SIGNKEY *= *).*/\\1<已配置>/'")
        o, _ = run(cli, remote_sh(env_cmd, via), 60)
        res.add("APPID" in o and "ORDER_PREFIX" in o, "MKPAY 配置段", o.replace("\n", " | ")[:160])

        bal_cmd = f"cd {php} && php _test_mkpay.php balance 2>&1 | tail -2"
        o, _ = run(cli, remote_sh(bal_cmd, via), 120)
        res.add('"code":200' in o.replace(" ", ""), "网关连通（balance）", o.replace("\n", " ")[:120])

        _, nbody = http_body(cfg, "/api/v1/recharge/notifyMkPay", http_cli)
        res.add(bool(nbody) and "__ERROR__" not in nbody, "回调路由可达", "/api/v1/recharge/notifyMkPay")

    cli.close()
    print(f"  ==> [{name}] {'全部通过' if res.ok else '有 FAIL，先排查再算同步完成'}")
    return res.ok

if __name__ == "__main__":
    ap = argparse.ArgumentParser()
    ap.add_argument("--client", choices=list(CLIENTS) + ["all"], default="all")
    ap.add_argument("--payment", action="store_true", help="同时验证支付后端（MKPAY 配置/网关/回调路由）")
    a = ap.parse_args()
    names = list(CLIENTS) if a.client == "all" else [a.client]
    ok = True
    for n in names:
        if not CLIENTS[n].get("verify_url"):
            print(f"\n===== [{n}] 未配置 verify_url，跳过 =====")
            continue
        ok = verify(n, CLIENTS[n], a.payment) and ok
    sys.exit(0 if ok else 1)
