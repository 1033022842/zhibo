#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""check_drift.py —— 检测各客户服务器与 gitee/main 的「代码漂移」

背景（2026-11-10）：同事的 shell-new.js 语言修复只存在于 3 台服务器，gitee 里没有
（服务器 `lang1` vs 上游 `uinfo1`），下次同步就被覆盖。服务器 /www/wwwroot/douyin
不是 git 仓库，没有 git status 可用，只能靠内容比对；手工 md5 慢且容易漏。

做法：
  1. 以 gitee/main 为「应然状态」，逐文件取 blob，算出归一化 md5
  2. 一次 SSH 在服务器上跑 md5sum 拿全部文件 md5（比逐文件 sftp 快两个数量级）
  3. 分类：一致 / 仅版本号不同 / 内容漂移 / 缺失；--diff 时再单独拉漂移文件正文

归一化 = 品牌词替换（apply_brand 会改品牌词，不抹掉会全站误报）+ 行尾统一。

用法：
  python .zcode/check_drift.py                  # 全部站点
  python .zcode/check_drift.py --client sugus
  python .zcode/check_drift.py --diff sugus     # 附漂移文件的具体差异
  python .zcode/check_drift.py --quiet          # 只看汇总

只读：不修改服务器任何文件。
"""
import argparse
import base64
import difflib
import hashlib
import os
import posixpath
import re
import subprocess
import sys

import paramiko

HERE = os.path.dirname(os.path.abspath(__file__))
REPO = os.path.dirname(HERE)
SITE_DIR = "ai-girl-malaysia.com"
REV = "gitee/main"
REMOTE_ROOT = "/www/wwwroot/douyin"

# 归一化：品牌词抹平（apply_brand 会替换）+ 行尾统一
BRAND_RE = re.compile(r"sugus|heygirl|kelymi|kiss|kisss", re.I)
# 品牌域名后缀：sugus.ai / heygirl.live / kelymi.autos / kisss.ai 会同时出现在页面里，
# 只抹品牌词会留下 ".ai" vs ".live" 的差异，误报成漂移
TLD_RE = re.compile(r"\.(ai|live|autos|com|sbs|cam|fun|one|chat)", re.I)
# apply_brand 会把静态资源地址换到客户自己的 CDN（*-live.b-cdn.net 等），
# 这类域名差异不是代码漂移，统一抹成 <CDN> 再比。
CDN_RE = re.compile(r"https?://[\w.-]*(?:b-cdn\.net|cloudfront\.net|akamaihd\.net)", re.I)
# 静态资源引用：<CDN>/ai/assets/x 与 ./assets/x 等价
MAIL_RE = re.compile(r"support@[\w.-]*mail[.]\w+", re.I)
RES_RE = re.compile(r"(?:<CDN>/ai|\.)/(assets|_shared|js|media)/([^\"'\s)]+)")

SCOPE_PREFIXES = (
    "ai-girl-malaysia.com/js/",
    "ai-girl-malaysia.com/assets/",
    "ai-girl-malaysia.com/_shared/",
    "php/app/",
    "php/config/",
    "php/route/",
)
SCOPE_SUFFIX = (".js", ".css", ".php")

# 默认排除：静态资源清单类大文件（几百 KB、几乎不会有人手改，扫描纯浪费时间）
EXCLUDE_NAME = ("site-manifest.json",)
# 默认只扫这些「最容易被手改、出了事影响最大」的目录，全量用 --all
CORE_PREFIXES = (
    "ai-girl-malaysia.com/js/",
    "php/app/live/service/",
    "php/app/api/controller/",
    "php/config/",
)


def sh(cmd):
    r = subprocess.run(cmd, shell=True, capture_output=True, cwd=REPO)
    return r.returncode, r.stdout.decode("utf-8", "replace"), r.stderr.decode("utf-8", "replace")


def upstream_list(core_only=False):
    """gitee/main 中需要校验的仓库相对路径清单"""
    rc, out, err = sh(f'git ls-tree -r --name-only "{REV}" -- {SITE_DIR} php')
    if rc:
        sys.exit(f"读取 {REV} 失败: {err}")
    res = []
    for p in out.splitlines():
        p = p.strip()
        if not p:
            continue
        if p.startswith(SITE_DIR + "/"):
            rel = p[len(SITE_DIR) + 1:]
            if rel.startswith(("_files/", "media/")):
                continue
            if "/" not in rel:
                # 站点根 HTML 不逐字节比：apply_brand 会换 favicon/域名/品牌名，
                # 每页都有差异且不是「有人手改代码」。HTML 的漂移用版本号体检：
                # 见 check_html_versions()，只报「引用了上游不存在的脚本版本」。
                continue
            elif p.startswith(SCOPE_PREFIXES) and rel.endswith(SCOPE_SUFFIX):
                res.append(p)
        elif p.startswith(("php/app/", "php/config/", "php/route/")):
            if "/vendor/" in p or "/runtime/" in p:
                continue
            if p.endswith((".php", ".json", ".phtml")):
                res.append(p)
        elif p.startswith("php/sql/") and p.endswith(".sql"):
            res.append(p)
    if core_only:
        res = [x for x in res
               if posixpath.basename(x) not in EXCLUDE_NAME
               and (x.startswith(CORE_PREFIXES) or ("/" not in x.split(SITE_DIR + "/", 1)[-1]))]
    return res


def upstream_blob(path):
    rc, out, _ = sh(f'git show "{REV}:{path}"')
    return out.encode("utf-8", "replace") if rc == 0 else None


def norm_text(content):
    """归一化：apply_brand 会改品牌词 / 域名后缀 / CDN 资源地址 / 资源引用形式，
    这些都不是「有人手改了代码」，比对前统一抹平，否则全站误报。"""
    if not content:
        return ""
    s = content.decode("utf-8", "replace")
    s = s.replace(chr(13) + chr(10), chr(10))
    s = MAIL_RE.sub("<MAIL>", s)
    s = CDN_RE.sub("<CDN>", s)
    # 资源引用统一：<CDN>/ai/assets/x、./assets/x 都是同一个文件 → <A>/assets/x
    s = RES_RE.sub(lambda m: "<A>/" + m.group(1) + "/" + m.group(2), s)
    s = BRAND_RE.sub("<B>", s)
    return TLD_RE.sub(".<TLD>", s)


def norm_md5(content):
    return hashlib.md5(norm_text(content).encode("utf-8")).hexdigest()


class Site:
    """一台客户服务器的读取句柄（直连或经跳板）"""

    def __init__(self, cfg):
        self.cfg = cfg
        self.ssh = paramiko.SSHClient()
        self.ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
        if cfg.get("jump"):
            j = cfg["jump"]
            self.ssh.connect(j["host"], 22, j["user"], j["password"],
                             timeout=30, look_for_keys=False, allow_agent=False)
            self.jump = True
        else:
            self.ssh.connect(cfg["host"], int(cfg.get("port", 22)), cfg["user"],
                             cfg.get("password", ""), timeout=30, banner_timeout=30,
                             auth_timeout=30, look_for_keys=False, allow_agent=False)
            self.jump = False

    def _run(self, remote_cmd, timeout=300):
        """在目标机上执行命令（跳板时用 ssh 嵌套）"""
        if self.jump:
            cmd = (f'ssh -i {self.cfg["key_on_jump"]} -o StrictHostKeyChecking=no '
                   f'-o BatchMode=yes {self.cfg["user"]}@{self.cfg["host"]} '
                   f'{_q(remote_cmd)}')
            _, out, err = self.ssh.exec_command(cmd, timeout=timeout)
            o = out.read().decode("utf-8", "replace")
            out.channel.recv_exit_status()
            return o, err.read().decode("utf-8", "replace")
        _, out, err = self.ssh.exec_command(remote_cmd, timeout=timeout)
        o = out.read().decode("utf-8", "replace")
        out.channel.recv_exit_status()
        return o, err.read().decode("utf-8", "replace")

    def md5_map(self, rels, normalize=False, strip_ver=False):
        """一次调用拿全部文件的 md5：返回 {rel: md5}（缺失的不在结果里）

        normalize=True 时在服务器端用 python3 做「品牌词抹平 + 去 CR」再算 md5，
        与上游侧 norm_md5 同口径 —— 这样 apply_brand 改过的文件不会被误报。
        （不用 shell 循环：680 个文件 × sed/tr/md5sum 三个进程会跑到超时）
        """
        if not rels:
            return {}
        result = {}
        BATCH = 400
        for i in range(0, len(rels), BATCH):
            chunk = rels[i:i + BATCH]
            if normalize:
                # 归一化逻辑放独立文件（.zcode/remote_norm_md5.py），base64 传过去执行。
                # 曾在字符串里内联写多行 python：\r\n / \b 这类转义经过
                # Python→base64→ssh 多层传递会被还原成真实字符，把脚本切断。
                with open(os.path.join(HERE, "remote_norm_md5.py"), "rb") as f:
                    py_b64 = base64.b64encode(f.read()).decode()
                payload = "\n".join(chunk) + "\n"
                lst_b64 = base64.b64encode(payload.encode()).decode()
                mode = "stripver" if strip_ver else "plain"
                script = (
                    f"echo {py_b64} | base64 -d > /tmp/_normmd5.py; "
                    f"echo {lst_b64} | base64 -d > /tmp/_drift_list; "
                    f"cd {REMOTE_ROOT} && python3 /tmp/_normmd5.py {mode} {REMOTE_ROOT} /tmp/_drift_list"
                )
                o, err = self._run(script, timeout=600)
                # 只保留清单里列出的文件（脚本是递归扫目录）
                wanted = set(chunk)
                for line in o.splitlines():
                    parts = line.split(None, 1)
                    if len(parts) == 2 and len(parts[0]) == 32 and parts[1].strip() in wanted:
                        result[parts[1].strip()] = parts[0]
            else:
                quoted = " ".join("'" + r.replace("'", "'\\''") + "'" for r in chunk)
                script = f"md5sum {quoted} 2>/dev/null"
            o, _ = self._run(f"cd {REMOTE_ROOT} && {script}", timeout=600)
            for line in o.splitlines():
                parts = line.split(None, 1)
                if len(parts) == 2 and len(parts[0]) == 32:
                    result[parts[1].strip()] = parts[0]
        return result

    def fetch(self, rel):
        """取单个文件正文"""
        rp = posixpath.join(REMOTE_ROOT, rel)
        o, _ = self._run(f'base64 -w0 {_q(rp)} 2>/dev/null')
        raw = o.strip()
        if not raw:
            return None
        try:
            return base64.b64decode(raw)
        except Exception:
            return None

    def close(self):
        try:
            self.ssh.close()
        except Exception:
            pass


def _q(s):
    """shell 单引号转义"""
    return "'" + s.replace("'", "'\\''") + "'"


def load_clients():
    import importlib.util
    spec = importlib.util.spec_from_file_location("sc", os.path.join(HERE, "sync_clients.py"))
    sc = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(sc)
    return sc.CLIENTS


# 远端扫描脚本（HTML 版本号体检用）：base64 传过去执行，避免 ssh 多层引号转义
REMOTE_VER_SCRIPT = 'import glob, os, re\nPAT = re.compile(rb\'(?:src|href)="\\./([^"?]+)[?]v=([A-Za-z0-9]+)"\')\nfor f in sorted(glob.glob(\'ai-girl-malaysia.com/*.html\')):\n    try:\n        t = open(f, \'rb\').read()\n    except Exception:\n        continue\n    n = os.path.basename(f)\n    for m in PAT.finditer(t):\n        print(n + chr(9) + m.group(1).decode() + chr(9) + m.group(2).decode())'


def check_html_versions(site):
    """HTML 版本号体检：抓站点根 .html 引用的脚本版本，与上游同页面比对，
    只报「服务器引用了上游没有的版本」——这正是 2026-11-10 那次漂移的信号
    （服务器 lang1 / 上游 uinfo1）。

    不逐字节比 HTML：apply_brand 会改品牌词、域名、favicon 文件名，
    整页比对全是噪音，真问题反而被淹没。
    """
    up_pages = {}
    rc, out, _ = sh(f'git ls-tree -r --name-only "{REV}" -- {SITE_DIR}')
    for p in out.splitlines():
        p = p.strip()
        rel = p[len(SITE_DIR) + 1:] if p.startswith(SITE_DIR + "/") else p
        if "/" in rel or not rel.endswith(".html"):
            continue
        c = upstream_blob(p)
        if c is None:
            continue
        pairs = {}
        for m in re.finditer(rb'(?:src|href)="\./([^"?]+)\?v=([A-Za-z0-9]+)"', c):
            pairs.setdefault(m.group(1).decode(), set()).add(m.group(2).decode())
        up_pages[rel] = pairs
    if not up_pages:
        return []

    # 在服务器上一次 python3 扫完全部 html（脚本走 base64，避开多层引号转义）
    b64 = base64.b64encode(REMOTE_VER_SCRIPT.encode()).decode()
    o, _ = site._run(
        f"echo {b64} | base64 -d > /tmp/_drift_ver.py; cd {REMOTE_ROOT} && python3 /tmp/_drift_ver.py",
        timeout=300)
    srv = {}
    for line in o.splitlines():
        parts = line.split(chr(9))
        if len(parts) == 3:
            srv.setdefault(parts[0], {}).setdefault(parts[1], set()).add(parts[2])

    problems = []
    for page, assets in srv.items():
        up = up_pages.get(page)
        if up is None:
            continue
        for asset, vers in assets.items():
            uvers = up.get(asset)
            if uvers is None:
                continue
            extra = vers - uvers
            if extra:
                problems.append((page, asset, sorted(extra), sorted(uvers)))
    return problems


def show_diff(rel, exp, got, max_lines=50):
    a = norm_text(exp).splitlines()
    b = norm_text(got).splitlines()
    d = list(difflib.unified_diff(a, b, fromfile=f"gitee/main:{rel}",
                                  tofile=f"server:{rel}", lineterm="", n=2))
    print(f"\n  --- {rel} ---")
    for line in d[:max_lines]:
        print("   " + line[:200])
    if len(d) > max_lines:
        print(f"    ... 还有 {len(d) - max_lines} 行")


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--client")
    ap.add_argument("--files", type=int, default=10, help="每站最多列几个漂移文件")
    ap.add_argument("--diff", nargs="?", const="ALL", metavar="CLIENT",
                    help="显示漂移文件差异（可指定单站）")
    ap.add_argument("--quiet", action="store_true", help="只输出汇总")
    ap.add_argument("--all", action="store_true", help="扫全部文件（默认只扫核心目录）")
    a = ap.parse_args()

    clients = load_clients()
    targets = [a.client] if a.client else [n for n in clients if clients[n].get("host")]
    files = upstream_list(core_only=not a.all)
    if not files:
        sys.exit("上游文件清单为空")
    if not a.quiet:
        print(f"基准: {REV} | 待校验 {len(files)} 个文件 | 站点 {', '.join(targets)}\n", flush=True)

    # 上游期望值（归一化 md5）
    expect = {}
    for p in files:
        c = upstream_blob(p)
        if c is not None:
            expect[p] = c
    exp_md5 = {p: norm_md5(c) for p, c in expect.items()}

    summary = {}
    for name in targets:
        cfg = clients.get(name)
        if not cfg:
            print(f"未知客户: {name}")
            continue
        port = f":{cfg['port']}" if cfg.get("port") else ""
        if not a.quiet:
            print(f"===== {name} ({cfg['host']}{port}) =====", flush=True)
        try:
            site = Site(cfg)
        except Exception as e:
            print(f"  ❌ 连接失败: {type(e).__name__}: {e}")
            summary[name] = "unreachable"
            continue

        try:
            # 服务器端已做品牌词归一，与 exp_md5 同口径，可直接比对
            got_md5 = site.md5_map(list(expect.keys()), normalize=True)
            same, drift, missing = 0, [], []
            for p in expect:
                got = got_md5.get(p)
                if got is None:
                    missing.append(p)
                elif got == exp_md5[p]:
                    same += 1
                else:
                    drift.append(p)
        finally:
            site.close()

        # 区分「只差版本号」与「真的改了逻辑」：版本号差异最普遍（每次发版全站 +1），
        # 逐文件拉正文只为分类，只对已确认不同的文件跑，且限制条数避免超时。
        ver_only = []
        if drift:
            try:
                site = Site(cfg)
            except Exception as e:
                print(f"  ❌ 二次连接失败: {e}")
                summary[name] = "unreachable"
                continue
            # 一次算出「只差版本号」的文件：服务端对正文做 ?v= 抹平后再算 md5，
            # 与上游 strip_versions 后的归一 md5 比对。避免逐文件 fetch（221 个要跑几分钟）。
            exp_nover = {}
            for p in drift:
                body = strip_versions(expect[p]).replace(b"\r\n", b"\n")
                text = BRAND_RE.sub("<B>", body.decode("utf-8", "replace"))
                exp_nover[p] = hashlib.md5(text.encode("utf-8")).hexdigest()
            try:
                got_nover = site.md5_map(drift, normalize=True, strip_ver=True)
            finally:
                site.close()
            real = []
            for p in drift:
                g = got_nover.get(p)
                if g is not None and g == exp_nover[p]:
                    ver_only.append(p)
                else:
                    real.append(p)
            drift = real

        # HTML 版本号体检（单独一轮，不逐字节比 HTML）
        html_problems = []
        try:
            site = Site(cfg)
            html_problems = check_html_versions(site)
            site.close()
        except Exception as e:
            if not a.quiet:
                print(f"  ⚠️ HTML 版本体检失败: {type(e).__name__}: {e}")

        # 注意：「缺失」不计入漂移 —— 客户站可能根本没部署上游某些功能模块，
        # 那不是「有人手改了文件」。只有「有但内容不同」才算漂移。
        summary[name] = "ok" if (not drift and not html_problems) else "drift"
        if not a.quiet:
            print(f"  {'✅' if (not drift and not html_problems) else '❌'} "
                  f"一致 {same} | 仅版本号 {len(ver_only)} | "
                  f"内容漂移 {len(drift)} | 缺失 {len(missing)} | "
                  f"版本号异常 {len(html_problems)}")
            for p in drift[:a.files]:
                print(f"      ❌ {p}")
            for page, asset, extra, up in html_problems[:a.files]:
                print(f"      ⚠️ {page} 引用 {asset}?v={','.join(extra)}（上游 {'/'.join(up)}）")
            if len(drift) > a.files:
                print(f"      ... 另有 {len(drift) - a.files} 个")
            if missing:
                for p in missing[:5]:
                    print(f"      ⚠️ 缺失 {p}")
            print()

            # 差异详情
            if a.diff and drift and (a.diff in ("ALL", name)):
                try:
                    site = Site(cfg)
                    for p in drift[:2]:
                        got = site.fetch(p)
                        if got is not None:
                            show_diff(p, expect[p], got)
                    site.close()
                except Exception as e:
                    print(f"    取差异失败: {e}")

    print("===== 汇总 =====")
    bad = 0
    for k, v in summary.items():
        label = {"ok": "✅ 无漂移", "drift": "❌ 有漂移", "unreachable": "⚠️ 连不上"}[v]
        print(f"  {k:10s} {label}")
        if v == "drift":
            bad += 1
    if bad:
        print("\n漂移 = 有人在服务器上直接改了文件。把改动搬回仓库"
              "（改 → commit → push → sync）才是正路，否则下次同步会被覆盖。")
        sys.exit(1)


def strip_versions(content):
    """抹掉 ?v=xxx，用于判断「只有版本号不同」"""
    if not content:
        return b""
    return re.sub(rb"\?v=[A-Za-z0-9]+", b"?v=X", content)


if __name__ == "__main__":
    main()
