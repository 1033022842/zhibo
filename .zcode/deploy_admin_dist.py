#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""部署后台前端 dist 到 4 站（只传有变化的文件，先备份旧 dist）"""
import io
import os
import posixpath
import tarfile
import tempfile

import paramiko

import deploy_creds as creds

REPO = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DIST = os.path.join(REPO, "php", "web", "dist")
REMOTE = "/www/wwwroot/douyin/admin/dist"

SITES = creds.site_dicts("heygirl", "kelymi", "kiss")
SUGUS = dict(jump=(creds.get("heygirl")["host"], creds.get("heygirl")["port"],
                  creds.get("heygirl")["password"]),
             host=creds.get("sugus")["host"], key=creds.get("sugus")["key"])


def run(cli, cmd, t=600):
    _, out, err = cli.exec_command(cmd, timeout=t)
    return out.read().decode("utf-8", "replace").strip(), err.read().decode("utf-8", "replace").strip()


def make_tar():
    """打包 dist（assets 里只保留本次构建产物，旧 hash 文件可以留）"""
    tmp = tempfile.NamedTemporaryFile(delete=False, suffix=".tar.gz")
    tmp.close()
    with tarfile.open(tmp.name, "w:gz") as tar:
        for root, dirs, files in os.walk(DIST):
            for f in files:
                full = os.path.join(root, f)
                rel = os.path.relpath(full, DIST)
                tar.add(full, arcname=rel)
    return tmp.name


def deploy_direct(name, cfg, tarpath):
    print(f"\n===== {name} ({cfg['host']}) =====", flush=True)
    cli = paramiko.SSHClient()
    cli.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    cli.connect(cfg["host"], cfg["port"], "root", cfg["password"],
                timeout=30, look_for_keys=False, allow_agent=False)

    run(cli, f"mkdir -p /root/admin_dist_bak && "
             f"test -d {REMOTE} && tar czf /root/admin_dist_bak/dist_$(date +%Y%m%d%H%M%S).tgz -C {REMOTE} . || true")
    sftp = cli.open_sftp()
    sftp.put(tarpath, "/tmp/admin_dist.tar.gz")
    sftp.close()
    o, e = run(cli, f"cd {REMOTE} && tar xzf /tmp/admin_dist.tar.gz && echo EXTRACT_OK && ls -la index.html | awk '{{print $5}}'")
    print("  " + (o or e)[:200].replace("\n", " "))
    o, _ = run(cli, f"ls {REMOTE}/assets | wc -l")
    print(f"  assets 文件数: {o.strip()}")
    cli.close()


def deploy_sugus(tarpath):
    print("\n===== sugus (via jump) =====", flush=True)
    j = paramiko.SSHClient()
    j.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    j.connect(SUGUS["jump"][0], 22, "root", SUGUS["jump"][2],
              timeout=30, look_for_keys=False, allow_agent=False)
    sftp = j.open_sftp()
    sftp.put(tarpath, "/tmp/admin_dist.tar.gz")
    sftp.close()
    key, host = SUGUS["key"], SUGUS["host"]
    cmd = (
        f"scp -i {key} -o StrictHostKeyChecking=no /tmp/admin_dist.tar.gz root@{host}:/tmp/admin_dist.tar.gz && "
        f"ssh -i {key} -o StrictHostKeyChecking=no root@{host} "
        f"'mkdir -p /root/admin_dist_bak && "
        f"test -d {REMOTE} && tar czf /root/admin_dist_bak/dist_$(date +%Y%m%d%H%M%S).tgz -C {REMOTE} . || true; "
        f"cd {REMOTE} && tar xzf /tmp/admin_dist.tar.gz && echo EXTRACT_OK && ls assets | wc -l'"
    )
    o, e = run(j, cmd, t=900)
    print("  " + (o or e)[:300].replace("\n", "\n  "))
    j.close()


def main():
    import sys
    targets = sys.argv[1:] or ["heygirl", "kelymi", "kiss", "sugus"]
    tar = make_tar()
    print(f"打包完成: {os.path.getsize(tar)/1024/1024:.2f} MB")
    try:
        for n in targets:
            if n == "sugus":
                deploy_sugus(tar)
            elif n in SITES:
                deploy_direct(n, SITES[n], tar)
    finally:
        os.unlink(tar)
    print("\n完成")


if __name__ == "__main__":
    main()
