#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""把防刷版 UserService.php 推到 4 站（备份 → 上传 → lint → reload php-fpm）

只推这一个文件，避免 --full 覆盖客户定制。
"""
import os
import sys

import paramiko

REPO = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
LOCAL = os.path.join(REPO, "php", "app", "live", "service", "UserService.php")
REMOTE = "/www/wwwroot/douyin/php/app/live/service/UserService.php"

SITES = {
    "heygirl": dict(host="172.81.99.11", port=22, password="***REMOVED-SSH-PW***"),
    "kelymi":  dict(host="172.81.99.42", port=22, password="***REMOVED-SSH-PW***"),
    "kiss":    dict(host="111.90.150.230", port=20203, password="***REMOVED-SSH-PW***"),
}
SUGUS_JUMP = dict(host="172.81.99.11", password="***REMOVED-SSH-PW***")


def run(cli, cmd, t=180):
    _, out, err = cli.exec_command(cmd, timeout=t)
    return out.read().decode("utf-8", "replace").strip(), err.read().decode("utf-8", "replace").strip()


def deploy(name, cfg):
    print(f"\n===== {name} ({cfg['host']}:{cfg['port']}) =====", flush=True)
    cli = paramiko.SSHClient()
    cli.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    cli.connect(cfg["host"], cfg["port"], "root", cfg["password"],
                timeout=30, look_for_keys=False, allow_agent=False)

    # 备份
    bak = REMOTE + ".bak.antibrush"
    run(cli, f"cp -a {REMOTE} {bak}")
    print(f"  备份: {bak}")

    # 上传
    sftp = cli.open_sftp()
    sftp.put(LOCAL, REMOTE)
    sftp.close()
    print("  上传完成")

    # lint
    o, e = run(cli, f"php -l {REMOTE} 2>&1 | tail -1")
    line = (o or e)
    print(f"  lint: {line[:100]}")
    if "No syntax errors" not in line:
        run(cli, f"cp -a {bak} {REMOTE}")
        print("  !! 语法错误，已回滚")
        cli.close()
        return False

    # reload
    o, e = run(cli, "systemctl reload php*-fpm 2>/dev/null || systemctl reload php8.3-fpm 2>&1; echo RC=$?")
    print(f"  reload: {(o or e)[:80]}")

    # 验证常量
    o, _ = run(cli, f"grep -c 'REGISTER_RATE_MAX\\|WELCOME_BONUS_IP_LIMIT\\|assertRegisterRate' {REMOTE}")
    print(f"  防刷代码就位: {o} 处")
    cli.close()
    return True


def deploy_sugus():
    print(f"\n===== sugus (172.81.98.55 via jump) =====", flush=True)
    j = paramiko.SSHClient()
    j.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    j.connect(SUGUS_JUMP["host"], 22, "root", SUGUS_JUMP["password"],
              timeout=30, look_for_keys=False, allow_agent=False)
    sftp = j.open_sftp()
    sftp.put(LOCAL, "/tmp/UserService_new.php")
    sftp.close()

    inner = (
        f"cp -a {REMOTE} {REMOTE}.bak.antibrush; "
        f"cp /tmp/UserService_new.php {REMOTE}; "
        f"php -l {REMOTE} 2>&1 | tail -1; "
        "systemctl reload php*-fpm 2>/dev/null || systemctl reload php8.3-fpm 2>&1; "
        f"grep -c 'REGISTER_RATE_MAX' {REMOTE}"
    )
    cmd = ('scp -i /root/.ssh/n99_pull -o StrictHostKeyChecking=no /tmp/UserService_new.php '
           'root@172.81.98.55:/tmp/UserService_new.php && '
           'ssh -i /root/.ssh/n99_pull -o StrictHostKeyChecking=no root@172.81.98.55 '
           "'" + inner + "'")
    o, e = run(j, cmd, t=240)
    print(o[:600] or e[:400])
    j.close()


def main():
    targets = sys.argv[1:] or list(SITES)
    ok = []
    for n in targets:
        if n == "sugus":
            deploy_sugus()
            ok.append(n)
        elif n in SITES:
            if deploy(n, SITES[n]):
                ok.append(n)
    print(f"\n完成: {ok}")


if __name__ == "__main__":
    main()
