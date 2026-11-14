#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""为 sync_clients.py 的快照预热 PHP 部分。

为什么需要：
  sync_clients.py 的历史快照里一个 php/ 路径都没有（tree_hashes 以前只扫站点目录）。
  刚把 php/ 纳入后，首次跑会把 in-scope 的 461 个 PHP 全判为「变更」并推送 ——
  其中 400+ 个其实服务器上就有且内容一致，白传；而真正内容不同的那几十个才需要推。

做法（只改本地快照文件，不碰服务器、不推任何文件）：
  对每个站，把「服务器 md5 == 仓库 md5」的 php 路径直接写进快照（视为已同步）；
  内容不同 / 服务器缺失的**不写入**，留给下一次真实同步去处理。

用法:
  python .zcode/_phpdrift_seed_snapshot.py            # 预览，不写
  python .zcode/_phpdrift_seed_snapshot.py --apply    # 写入快照
"""
import hashlib
import json
import os
import subprocess
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, os.path.join(ROOT, ".zcode"))
import paramiko
import deploy_creds as creds
import sync_clients as sc

PHP_ROOT = "/www/wwwroot/douyin/php"
REMOTE_LIST = (
    f"cd {PHP_ROOT} && find app config route extend public -type f "
    "\\( -name '*.php' -o -name '*.json' -o -name '*.phtml' \\) "
    "-exec md5sum {} + 2>/dev/null"
)


def repo_blobs():
    """仓库 HEAD 里 in-scope 的 php 文件 -> (blob_hash, 工作区内容 md5)"""
    rc, out, _ = sc.sh('git ls-tree -r "HEAD" "php"', cwd=sc.REPO)
    blobs = {}
    for line in out.splitlines():
        meta, path = line.split("\t", 1)
        path = path.strip()
        if not sc.in_scope_php(path):
            continue
        blob = meta.split()[2]
        lp = os.path.join(sc.REPO, path.replace("/", os.sep))
        md5 = hashlib.md5(open(lp, "rb").read()).hexdigest() if os.path.isfile(lp) else None
        blobs[path] = (blob, md5)
    return blobs


def remote_hashes(cli):
    _, out, _ = cli.exec_command(REMOTE_LIST, timeout=300)
    rows = {}
    for line in out.read().decode("utf-8", "replace").splitlines():
        p = line.split(None, 1)
        if len(p) == 2:
            rows["php/" + p[1].strip().lstrip("./")] = p[0]
    return rows


def seed(name, blobs, apply_):
    dfile = os.path.join(sc.HERE, f".sync_state_{name}.json")
    state = json.load(open(dfile, encoding="utf-8")) if os.path.isfile(dfile) else {}

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
    else:
        c = creds.conn(name)
        cli = paramiko.SSHClient()
        cli.set_missing_host_key_policy(paramiko.AutoAddPolicy())
        cli.connect(c[0], c[1], c[2], c[3], timeout=30, look_for_keys=False, allow_agent=False)

    remote = remote_hashes(cli)
    cli.close()
    if name == "sugus":
        jump.close()

    same = []
    diff = []
    missing = []
    for path, (blob, md5) in blobs.items():
        rh = remote.get(path)
        if rh is None:
            missing.append(path)
        elif md5 is not None and rh == md5:
            same.append(path)
        else:
            diff.append(path)

    print(f"== {name}: 一致 {len(same)} / 不一致 {len(diff)} / 服务器缺失 {len(missing)}"
          f"（in-scope 共 {len(blobs)}）")
    if missing:
        print("   缺失（下次同步会新增）:", missing[:8])

    if apply_:
        for path in same:
            state[path] = blobs[path][0]
        with open(dfile, "w", encoding="utf-8") as f:
            json.dump(state, f)
        print(f"   已写入快照：新增 {len(same)} 条 php 记录，总 {len(state)} 条")
    return len(same), len(diff), len(missing)


def main():
    apply_ = "--apply" in sys.argv
    blobs = repo_blobs()
    print(f"仓库 in-scope php: {len(blobs)} 个（{'写入' if apply_ else '预览'}模式）\n")
    for name in ("heygirl", "sugus", "kelymi", "kiss"):
        seed(name, blobs, apply_)
        print()


if __name__ == "__main__":
    main()
