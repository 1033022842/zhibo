#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""只读抓取四站 PHP 漂移文件，供离线 diff 分类。

不写远端、不改仓库。产出：
  _phpdrift/<site>/<relpath>        远端当前文件
  _phpdrift/<site>/_drift.json      {relpath: {"md5":..., "repo_md5":..., "status":...}}
"""
import hashlib
import json
import os
import subprocess
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))   # D:\ever\douyin\douyin
sys.path.insert(0, os.path.join(ROOT, ".zcode"))
import paramiko
import deploy_creds as creds
import sync_clients as sc

OUT = os.path.join(os.path.dirname(ROOT), "_phpdrift")
PHP_ROOT = "/www/wwwroot/douyin/php"


def local_php_hashes():
    """仓库 HEAD 里 in-scope 的 php 文件 -> md5（按工作区内容，与 HEAD 应一致）"""
    files = subprocess.run(
        ["git", "ls-tree", "-r", "HEAD", "--name-only", "php/"],
        capture_output=True, text=True, cwd=ROOT).stdout.split()
    out = {}
    for f in files:
        if not sc.in_scope_php(f):
            continue
        p = os.path.join(ROOT, f.replace("/", os.sep))
        if os.path.isfile(p):
            out[f] = hashlib.md5(open(p, "rb").read()).hexdigest()
    return out


REMOTE_LIST = (
    f"cd {PHP_ROOT} && find app config route sql public -type f "
    "\\( -name '*.php' -o -name '*.json' -o -name '*.phtml' -o -name '*.sql' \\) "
    "-exec md5sum {} + 2>/dev/null"
)


def handle_site(name, cli=None):
    cfg = creds.conn(name)
    own = cli is None
    if own:
        cli = paramiko.SSHClient()
        cli.set_missing_host_key_policy(paramiko.AutoAddPolicy())
        cli.connect(cfg[0], cfg[1], cfg[2], cfg[3], timeout=30,
                    look_for_keys=False, allow_agent=False)

    _, out, _ = cli.exec_command(REMOTE_LIST, timeout=300)
    rows = {}
    for line in out.read().decode("utf-8", "replace").splitlines():
        parts = line.split(None, 1)
        if len(parts) != 2:
            continue
        h, rel = parts[0], parts[1].strip().lstrip("./")
        rows["php/" + rel] = h

    sftp = cli.open_sftp()
    sftp.get_channel().settimeout(180)
    site_dir = os.path.join(OUT, name)
    os.makedirs(site_dir, exist_ok=True)

    local = local_php_hashes()
    drift = {}
    for rel, rh in rows.items():
        lh = local.get(rel)
        if lh is None:
            drift[rel] = {"md5": rh, "repo_md5": None, "status": "remote_only"}
            continue
        if lh != rh:
            drift[rel] = {"md5": rh, "repo_md5": lh, "status": "differs"}
            # 下载远端内容
            rp = PHP_ROOT + "/" + rel[len("php/"):]
            dst = os.path.join(site_dir, rel[len("php/"):].replace("/", os.sep))
            os.makedirs(os.path.dirname(dst), exist_ok=True)
            try:
                sftp.get(rp, dst)
            except Exception as e:
                print(f"   !! 下载失败 {rel}: {e}")

    # 远端缺失（仓库有、服务器没有）
    for rel in local:
        if rel not in rows:
            drift[rel] = {"md5": None, "repo_md5": local[rel], "status": "repo_only"}

    with open(os.path.join(site_dir, "_drift.json"), "w", encoding="utf-8") as f:
        json.dump(drift, f, ensure_ascii=False, indent=1)

    counts = {}
    for v in drift.values():
        counts[v["status"]] = counts.get(v["status"], 0) + 1
    print(f"== {name}: {counts}")

    sftp.close()
    if own:
        cli.close()
    return drift


def main():
    os.makedirs(OUT, exist_ok=True)
    which = sys.argv[1:] or ["heygirl", "kelymi", "kiss", "sugus"]
    for name in which:
        if name == "sugus":
            jc = creds.site_dicts("heygirl")["heygirl"]
            jump = paramiko.SSHClient()
            jump.set_missing_host_key_policy(paramiko.AutoAddPolicy())
            jump.connect(jc["host"], jc["port"], "root", jc["password"], timeout=30,
                         look_for_keys=False, allow_agent=False)
            sg = creds.site_dicts("sugus")["sugus"]
            ch = jump.get_transport().open_channel(
                "direct-tcpip", (sg["host"], 22), ("127.0.0.1", 0))
            cli = paramiko.SSHClient()
            cli.set_missing_host_key_policy(paramiko.AutoAddPolicy())
            cli.connect(sg["host"], 22, "root", sg["password"], sock=ch, timeout=30,
                        look_for_keys=False, allow_agent=False)
            handle_site("sugus", cli)
            cli.close()
            jump.close()
        else:
            handle_site(name)


if __name__ == "__main__":
    main()
