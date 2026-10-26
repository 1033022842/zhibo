#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""sync_clients.py —— 从 douyin 上游向客户项目分发 ai-girl-malaysia.com（多客户同步工具）

架构前提（见 docs/多客户同步SOP.md）：
  - douyin 仓库 = 唯一功能上游；客户仓库业务文件保持与本仓库一致的中性基线
  - 客户品牌收敛在客户服务器的 brand kit（/opt/brand-kit），分发后由 apply_brand.sh 打上
  - 无共同 git 历史，同步方式 = 内容对齐（不是 git merge）

用法：
  python .zcode/sync_clients.py                      # 全部客户
  python .zcode/sync_clients.py --client heygirl     # 单客户
  python .zcode/sync_clients.py --client heygirl --dry-run
  python .zcode/sync_clients.py --client heygirl --full   # 忽略快照，全量推送
  python .zcode/sync_clients.py --client kiss --seed-snapshot-from 7a38a72
                              # 服务器已部署该提交 → 用它做基线，首次只推之后的变更
                              # （不预热快照的话首次会把 assets/ 几十 MB 全量重传）

客户差异化页面：某个页面在个别客户上要完全不同的实现时，不要改上游同名文件、
也不要手改客户服务器，而是把客户版放进 client_overrides/<变体>/（目录结构 = 站点根镜像），
再在 CLIENTS 里给该客户加 "overrides": "client_overrides/<变体>"。详见 client_overrides/README.md。

首次给新客户使用前：先按 SOP 完成服务器 brand kit 初始化 + 客户仓库中性基线，
并在下面 CLIENTS 里补齐该客户配置。
"""
import argparse, json, os, posixpath, re, shutil, subprocess, sys, time
import paramiko

HERE = os.path.dirname(os.path.abspath(__file__))
REPO = os.path.dirname(HERE)                       # douyin 仓库根
SITE_DIR = "ai-girl-malaysia.com"
STATE_FILE = lambda c: os.path.join(HERE, f".sync_state_{c}.json")

# ── 客户配置 ────────────────────────────────────────────────
CLIENTS = {
    # heygirl：网站机直连；服务器 brand kit 已就绪（/opt/brand-kit）
    "heygirl": {
        "host": "172.81.99.11", "user": "root", "password": "mw-DGckAL,61fV00",
        "site": "/www/wwwroot/douyin/ai-girl-malaysia.com",
        "brand": "heygirl",                        # 服务器上 apply_brand 的客户名；None=不打品牌
        "brand_kit": "/opt/brand-kit",
        "verify_url": "https://heygirl.live/ai/Home.html",
        "verify_expect": "heygirl",                # 验证页面应包含的品牌词
        # 客户仓库跟进（防「只改服务器不进仓库」的漂移事故）
        "repo": r"D:\ever\heygirl",
        "keep_files": {                             # 客户特有文件：仓库 checkout 后需恢复/保留
            "ai-girl-malaysia.com/privacy.html": "keep",   # 上游没有、客户有 → checkout 不动它
            "ai-girl-malaysia.com/terms.html": "keep",
        },
        # 客户差异化页面：Image.html 与 sugus / kelymi / kiss 完全不同实现
        "overrides": "client_overrides/image-heygirl",
    },
    # sugus（上游自己的生产）：走 99.11 跳板（99.11 有 n99_pull key 可连 172）
    "sugus": {
        "jump": {"host": "172.81.99.11", "user": "root", "password": "mw-DGckAL,61fV00"},
        "host": "172.81.98.55", "user": "root",
        "key_on_jump": "/root/.ssh/n99_pull",
        "site": "/www/wwwroot/douyin/ai-girl-malaysia.com",
        "brand": None, "brand_kit": None,
        "verify_url": "http://172.81.98.55/ai/Home.html",
        "verify_expect": "Sugus",
        "repo": None,
        # 客户差异化页面：Image.html 与 heygirl / kelymi / kiss 完全不同实现
        "overrides": "client_overrides/image-sugus",
    },
    # kelymi（第三项目）：2026-10-18 初始化；服务器 brand kit 已就绪（/opt/brand-kit）
    "kelymi": {
        "host": "172.81.99.42", "user": "root", "password": "ffG2dAUGc{V572{j",
        "site": "/www/wwwroot/douyin/ai-girl-malaysia.com",
        "brand": "kelymi",
        "brand_kit": "/opt/brand-kit",
        "verify_url": "https://kelymi.autos/ai/Home.html",
        "verify_expect": "Kelymi",
        "repo": r"D:\ever\kelymi",   # 2026-10-18 按 SOP 三.A 初始化（douyin 基线 + brand kit）
    },
    # kiss（第四项目）：2026-10-19 初始化；kisss.ai 已解析+证书（10-20）；换脸只砍前台
    "kiss": {
        "host": "103.217.252.52", "user": "root", "password": "Aa1033022842",
        "site": "/www/wwwroot/douyin/ai-girl-malaysia.com",
        "brand": "kiss",
        "brand_kit": "/opt/brand-kit",
        # 80 端口是 Certbot 的 default_server return 404，必须走域名 https
        "verify_url": "https://kisss.ai/ai/Home.html",
        "verify_expect": "Kiss",
        "repo": r"D:\ever\kiss",   # 2026-10-20 按 SOP 三.A 初始化（douyin 基线 + brand kit）
        # 换脸前台文件不同步（apply_brand 也会兜底删除，这里避免白推）
        "exclude": [
            "ai-girl-malaysia.com/face_swap.html",
            "ai-girl-malaysia.com/js/face_swap.js",
        ],
    },
}

def sh(cmd, cwd=None):
    r = subprocess.run(cmd, shell=True, capture_output=True, cwd=cwd)
    return r.returncode, r.stdout.decode("utf-8", "replace"), r.stderr.decode("utf-8", "replace")

def tree_hashes(rev):
    """返回 <rev> 下站点目录的 {路径: blob hash}"""
    rc, out, err = sh(f'git ls-tree -r "{rev}" "{SITE_DIR}"', cwd=REPO)
    if rc: sys.exit(f"git ls-tree {rev} 失败: {err}")
    cur = {}
    for line in out.splitlines():
        meta, path = line.split("\t", 1)
        cur[path.strip()] = meta.split()[2]
    return cur

def changed_files(client, full):
    """git blob 快照对比：返回自上次同步以来 ai-girl 目录变更/新增的文件（相对仓库根）
    默认只管 *.html / js/** / assets/** / site-manifest.json；
    _files/（保存页素材，几十万文件）默认不同步，--include-files 开启。"""
    cur = tree_hashes("HEAD")
    state = {} if full else json.load(open(STATE_FILE(client), encoding="utf-8")) if os.path.exists(STATE_FILE(client)) else {}
    changed = [p for p, h in cur.items() if state.get(p) != h]
    return cur, changed

def in_scope(path, include_files):
    if path.endswith(".html") or path == f"{SITE_DIR}/site-manifest.json":
        return True
    rel = path[len(SITE_DIR) + 1:]
    # 站点根 index.php：后台「站点首页」的入口（/ 与 /ai/ 302），见 .zcode/sitehome/
    if rel == "index.php":
        return True
    # _shared/：10-18 起页面间共享大资源（52 个内页引用），漏推会整页 404 JS/CSS
    if rel.startswith(("js/", "assets/", "_shared/")):
        return True
    # media/：站点本地素材（如 media/gs 的 clip 视频/封图），漏推列表页整屏空白
    if rel.startswith("media/"):
        return True
    if include_files and rel.split("/", 1)[0].endswith("_files"):
        return True
    return False

# ── 客户差异化页面（overrides）─────────────────────────────────
# 覆盖目录 = 站点根镜像：client_overrides/image-sugus/Image.html → 站点 /Image.html
# 这些文件不在 SITE_DIR 里，天然不进 blob 快照；每次同步按需补传（同尺寸自动跳过）。

# 覆盖目录里只放「要发布到站点根」的文件；以下不进站点：
#   media-src/  —— 素材清单 + 服务器抓取脚本（体积大，由服务器自己拉，见该目录 README）
#   README.md   —— 变体说明
OVERRIDE_SKIP_DIRS = {"media-src"}
OVERRIDE_SKIP_FILES = {"README.md"}
def override_plan(cfg):
    """返回 (覆盖目录绝对路径, [(本地绝对路径, 站点相对路径)])；未配置返回 (None, [])"""
    od = cfg.get("overrides")
    if not od:
        return None, []
    root = os.path.join(REPO, od.replace("/", os.sep))
    if not os.path.isdir(root):
        print(f"  !! 覆盖目录不存在: {od}（该客户将使用上游基线）")
        return None, []
    files = []
    for dirpath, dirnames, filenames in os.walk(root):
        dirnames[:] = [d for d in dirnames
                       if not d.startswith(".") and d not in OVERRIDE_SKIP_DIRS]
        for f in filenames:
            if f.startswith("."):      # .gitkeep 等占位文件不发布
                continue
            if dirpath == root and f in OVERRIDE_SKIP_FILES:
                continue               # 覆盖目录的说明文档不是站点文件
            lp = os.path.join(dirpath, f)
            rel = os.path.relpath(lp, root).replace(os.sep, "/")
            files.append((lp, rel))
    files.sort(key=lambda x: x[1])
    return root, files

# ── 发布前自检：原站残留 / 品牌串 / 错误 canonical ────────────────
# 这些在同步上游时会反复出现（10-18~10-20 各踩过一次）：
#   1) candy.ai / uta.candy.ai：原站品牌、JSON-LD、第三方埋点（会把数据发去别家）
#   2) 非客户品牌串：sugusmail / MotionGen / 硬编码 sugus.ai 绝对地址
#   3) og:url / canonical 指向别家域名或不存在的根路径（分享与 SEO 都会错）
ORIGIN_PATTERNS = [
    # (正则, 标签, 是否会被 apply_brand 自动修掉)
    (re.compile(rb"\bcandy\.ai\b", re.I),          "candy.ai 原站引用",  True),
    (re.compile(rb"uta-api|data-uta-api-", re.I),  "原站 uta-api 埋点",  False),
    (re.compile(rb"sugusmail\.ai", re.I),          "sugus 客服邮箱",     True),
    (re.compile(rb"MotionGen", re.I),              "MotionGen 旧品牌",   True),
]
CANON_PAT = re.compile(rb'<meta property="og:url" content="([^"]*)"|<link rel="canonical" href="([^"]*)"')
COMMENT_HINT = re.compile(rb"/\*|^\s*//|<!--")

def preflight(client, cfg, files=None, quiet=False):
    """扫描仓库中将被发布的文件，返回问题列表。files=None 时扫整个 SITE_DIR。"""
    import glob as _glob
    targets = files or sorted(
        p for p in _glob.glob(os.path.join(REPO, SITE_DIR, "*.html"))
        + _glob.glob(os.path.join(REPO, SITE_DIR, "js", "*.js")))
    problems = []
    brand = (cfg.get("brand") or "").lower()

    for p in targets:
        if not os.path.isfile(p):
            continue
        rel = os.path.relpath(p, REPO).replace(os.sep, "/")
        raw = open(p, "rb").read()
        for pat, label, auto in ORIGIN_PATTERNS:
            for m in pat.finditer(raw):
                line = raw[:m.start()].count(b"\n") + 1
                row = raw.splitlines()[line - 1]
                if COMMENT_HINT.search(row):   # 开发注释里的参考说明不算问题
                    continue
                problems.append((rel, line, label, row.decode("utf-8", "replace")[:110], auto))
        for m in CANON_PAT.finditer(raw):
            val = (m.group(1) or m.group(2) or b"").decode("utf-8", "replace")
            if not val:
                continue
            bad = ("candy.ai" in val.lower()
                   or (val.startswith("/") and not val.startswith("//"))
                   or (brand and brand not in val.lower() and val.startswith("http")
                       and "heygirl" in val.lower() and brand != "heygirl"))
            if bad:
                line = raw[:m.start()].count(b"\n") + 1
                auto = "candy.ai" in val.lower()   # apply_brand 会把 candy.ai 域转成客户域
                problems.append((rel, line, "og:url/canonical 指向错误", val[:110], auto))
    return problems

def report_preflight(problems, client):
    if not problems:
        print("  自检: 通过（无原站残留）")
        return True
    fatal = [p for p in problems if not p[4]]      # 品牌脚本修不掉的
    auto  = [p for p in problems if p[4]]          # apply_brand 会处理的
    if fatal:
        print(f"  !! 自检发现 {len(fatal)} 处【需手工修复】的问题：")
        shown = set()
        for rel, line, label, ctx, _ in fatal:
            if (rel, label) in shown: continue
            shown.add((rel, label))
            print(f"     {rel}:{line}  [{label}]  {ctx}")
        if len(fatal) > len(shown):
            print(f"     …（同类问题共 {len(fatal)} 处，仅列每文件首个）")
    if auto:
        print(f"  ·  另有 {len(auto)} 处品牌残留，apply_brand.sh 会自动替换（无需处理）")
    if fatal:
        print("     提示：uta-api 埋点会把用户数据发往原站，务必手工删除。")
    return not fatal

def connect(cfg):
    cli = paramiko.SSHClient(); cli.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    cli.connect(cfg["host"], 22, cfg["user"], cfg.get("password", ""),
                 timeout=30, look_for_keys=False, allow_agent=False)
    return cli

def upload(cli, local_root, files, site):
    sftp = cli.open_sftp()
    # 长连接静默挂死时，写阻塞会在超时后抛异常，交给外层重连续传
    sftp.get_channel().settimeout(120)
    n = 0
    for rel in files:
        lp = os.path.join(local_root, rel.replace("/", os.sep))
        if not os.path.isfile(lp):
            continue
        # rel 带仓库内前缀（ai-girl-malaysia.com/...），远程要剥掉拼到 site 根——
        # 不剥会推进 site/ai-girl-malaysia.com/ 嵌套目录，真实站点一个文件都不动
        rel_site = rel[len(SITE_DIR) + 1:] if rel.startswith(SITE_DIR + "/") else rel
        rp = posixpath.join(site, rel_site)
        d = posixpath.dirname(rp)
        parts = d.split("/")[1:]
        acc = ""
        for p in parts:
            acc += "/" + p
            try: sftp.stat(acc)
            except IOError:
                try: sftp.mkdir(acc)
                except IOError: pass
        # 断点续传：远端同尺寸视为已就位（大目录全量推送被断连后，重跑不必从头再来）
        try:
            if sftp.stat(rp).st_size == os.path.getsize(lp):
                continue
        except IOError:
            pass
        sftp.put(lp, rp); n += 1
    sftp.close()
    return n

def sync_client(name, cfg, dry, full, init_snapshot=False, include_files=False, seed_from=None, strict=False):
    print(f"\n===== [{name}] =====")
    if not cfg.get("host") and not cfg.get("jump"):
        print("  !! 未配置服务器（占位），跳过。补齐 CLIENTS 配置后重试。"); return
    # 0) 源头必须干净（只看已跟踪文件；未跟踪的本地抓页残留不会被推送）
    rc, out, _ = sh(f'git status --short --untracked-files=no -- {SITE_DIR}', cwd=REPO)
    if out.strip():
        sys.exit(f"!! douyin 工作区 {SITE_DIR} 有未提交改动，先提交再分发：\n{out}")

    if seed_from:
        # 服务器已部署 seed_from 时用它当基线：首次同步只推该提交之后的变更
        # （--init-snapshot 记的是 HEAD，会把没推过的变更一并标成「已同步」）
        base = tree_hashes(seed_from)
        json.dump(base, open(STATE_FILE(name), "w", encoding="utf-8"))
        print(f"  已用 {seed_from} 的 blob 快照初始化基线（不推送）。")
        return

    cur, changed = changed_files(name, full)
    changed = [p for p in changed if in_scope(p, include_files)]
    excluded = set(cfg.get("exclude", []))
    if excluded:
        changed = [p for p in changed if p not in excluded]
    # 配了 overrides 的客户：同路径的上游文件不推（否则先推上游版再被覆盖，白传且易误判）
    ov_root, ov_files = override_plan(cfg)
    if ov_files:
        ov_targets = {f"{SITE_DIR}/{rel}" for _, rel in ov_files}
        changed = [p for p in changed if p not in ov_targets]
    print(f"  变更文件 {len(changed)} 个（范围: html/js/assets/manifest{' +_files' if include_files else ''}）")
    if cfg.get("overrides"):
        detail = "、".join(rel for _, rel in ov_files[:6]) if ov_files else "（目录为空，沿用上游版）"
        print(f"  覆盖文件 {len(ov_files)} 个（{cfg['overrides']}）: {detail}")
    if init_snapshot:
        json.dump(cur, open(STATE_FILE(name), "w", encoding="utf-8"))
        print("  已记录快照（不推送）。")
        return

    # 发布前自检：扫本次要发布的文件（无变更时扫全站，便于随时体检）；覆盖文件一并体检
    check_targets = [os.path.join(REPO, p.replace("/", os.sep)) for p in changed]
    check_targets += [lp for lp, _ in ov_files]
    problems = preflight(name, cfg, check_targets or None)
    if not report_preflight(problems, name) and strict:
        sys.exit(f"!! --strict: 自检未通过，已中止分发（共 {len(problems)} 处问题）")

    if not changed:
        print("  无变更，仅刷新验证。")
    if dry:
        for p in changed[:20]: print("   dry:", p)
        for _, rel in ov_files[:20]: print("   dry: [覆盖]", rel)
        return

    if cfg.get("jump"):  # 两跳：先到跳板，再一次 tar 流过去（逐文件 scp = 几百次建连，必被限流）
        tmp = f"/tmp/sync_{name}"
        j = connect(cfg["jump"])
        _, out, err = j.exec_command(f"rm -rf {tmp} && mkdir -p {tmp}", timeout=30); err.channel.recv_exit_status()
        j.close()
        n = 0
        for attempt in range(1, 4):  # 断连后重连续传（同尺寸文件跳过）
            j = connect(cfg["jump"])
            try:
                n += upload(j, REPO, changed, tmp)
                if ov_files:
                    n += upload(j, ov_root, [rel for _, rel in ov_files], tmp)
                break
            except Exception as e:
                print(f"  跳板上传中断（第 {attempt}/3 次）: {e}")
                if attempt == 3:
                    raise
                time.sleep(20)
            finally:
                try: j.close()
                except Exception: pass
        key = cfg["key_on_jump"]
        j = connect(cfg["jump"])
        _, out, err = j.exec_command(
            f'cd {tmp} && tar -cf - . | ssh -i {key} -o StrictHostKeyChecking=no -o BatchMode=yes '
            f'{cfg["user"]}@{cfg["host"]} "tar -xf - -C {cfg["site"]}"', timeout=1800)
        rc = out.channel.recv_exit_status()
        print(f"  经跳板推送 {n} 个文件 -> {cfg['host']}（tar rc={rc}）")
        j.close()
    else:
        n = 0
        for attempt in range(1, 4):
            cli = None
            try:
                cli = connect(cfg)
                n += upload(cli, REPO, changed, cfg["site"])
                if ov_files:
                    n += upload(cli, ov_root, [rel for _, rel in ov_files], cfg["site"])
                break
            except Exception as e:
                print(f"  上传中断（第 {attempt}/3 次）: {e}")
                if attempt == 3:
                    raise
                time.sleep(20)
            finally:
                if cli:
                    try: cli.close()
                    except Exception: pass
        print(f"  推送 {n} 个文件 -> {cfg['host']}")
        # 打品牌
        if cfg.get("brand"):
            cli = connect(cfg)
            _, out, err = cli.exec_command(
                f'bash {cfg["brand_kit"]}/tools/apply_brand.sh {cfg["brand"]} {cfg["site"]} {cfg["brand_kit"]} 2>&1 | tail -3',
                timeout=180)
            print("  apply_brand:", out.read().decode().strip())
            cli.close()

    # 记录快照（推送成功后才落）
    json.dump(cur, open(STATE_FILE(name), "w", encoding="utf-8"))

    # 线上验证
    if cfg.get("verify_url"):
        import urllib.request, ssl
        ctx = ssl.create_default_context(); ctx.check_hostname = False; ctx.verify_mode = ssl.CERT_NONE
        try:
            html = urllib.request.urlopen(cfg["verify_url"] + "?v=" + str(os.getpid()), timeout=15, context=ctx).read().decode("utf-8", "replace")
            ok = cfg["verify_expect"] in html
            print(f"  线上验证: {'OK' if ok else '!! 失败'}（应含 {cfg['verify_expect']!r}）")
        except Exception as e:
            print(f"  线上验证异常: {e}")

    # 客户仓库跟进：内容对齐 + 保留客户特有文件 + 提交
    repo = cfg.get("repo")
    if repo and os.path.isdir(repo):
        rc, _, err = sh(f'git checkout douyin/main -- {SITE_DIR}/ 2>&1', cwd=repo) if os.path.isdir(repo) else (1, "", "no remote")
        # 客户仓库没有 douyin remote 时先加
        if rc:
            sh('git remote add douyin git@gitee.com:autumn_birds_in_the_moon_sea/zhibo.git', cwd=repo)
            sh('git fetch douyin', cwd=repo)
            rc, _, err = sh(f'git checkout douyin/main -- {SITE_DIR}/', cwd=repo)
        print(f"  客户仓库内容对齐: {'OK' if rc == 0 else err}")
        # 覆盖文件回写：checkout 会把覆盖页还原成上游版，必须把客户版写回仓库，
        # 否则「服务器 = 仓库」这条约束当场破掉（下次同步又得靠脚本兜）
        if ov_files:
            for lp, rel in ov_files:
                dest = os.path.join(repo, SITE_DIR, rel.replace("/", os.sep))
                os.makedirs(os.path.dirname(dest), exist_ok=True)
                shutil.copyfile(lp, dest)
            print(f"  覆盖文件已写回客户仓库: {len(ov_files)} 个")
        # keep 文件恢复：checkout 不会删除上游没有的文件，但被上游同名覆盖的需注意
        msg = f"sync(upstream): 从 douyin 上游同步 {SITE_DIR}（{len(changed)} 文件" \
              f"{f'，含覆盖 {len(ov_files)} 个' if ov_files else ''}）"
        sh(f'git add -A {SITE_DIR} && git commit -m "{msg}"', cwd=repo)
        sh('git push origin master', cwd=repo)
        print(f"  客户仓库已提交推送: {msg}")
    print(f"  [{name}] 完成")

if __name__ == "__main__":
    ap = argparse.ArgumentParser()
    ap.add_argument("--client", choices=list(CLIENTS) + ["all"], default="all")
    ap.add_argument("--dry-run", action="store_true")
    ap.add_argument("--full", action="store_true", help="忽略快照全量对比")
    ap.add_argument("--init-snapshot", action="store_true", help="只记录当前 blob 快照，不推送（服务器已是最新时预热用）")
    ap.add_argument("--include-files", action="store_true", help="连 *_files/ 素材目录一起同步（默认排除）")
    ap.add_argument("--strict", action="store_true", help="自检发现问题时中止分发（CI/发布前推荐）")
    ap.add_argument("--check", action="store_true", help="只跑发布前自检，不分发")
    ap.add_argument("--seed-snapshot-from", metavar="REV",
                    help="服务器已部署该提交时，用它（而非 HEAD）初始化快照基线，首次只推之后的变更")
    a = ap.parse_args()
    names = list(CLIENTS) if a.client == "all" else [a.client]
    for n in names:
        if a.check:
            p = preflight(n, CLIENTS[n])
            report_preflight(p, n)
        else:
            sync_client(n, CLIENTS[n], a.dry_run, a.full, a.init_snapshot, a.include_files,
                        a.seed_snapshot_from, a.strict)
