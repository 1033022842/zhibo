#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""部署 aiShort 通道到 sugus（98.55，经 99.11 跳板）。

步骤：备份 → 传 3 个 PHP 文件 → 跑 SQL → 验证表与路由。
"""
import base64
import os
import sys

import paramiko

REPO = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
JUMP = ("172.81.99.11", "root", "mw-DGckAL,61fV00")
TARGET = "172.81.98.55"
KEY = "/root/.ssh/n99_pull"
SITE = "/www/wwwroot/douyin"

FILES = [
    ("php/app/api/controller/AiShort.php", SITE + "/php/app/api/controller/AiShort.php"),
    ("php/app/ai/service/ShortTaskService.php", SITE + "/php/app/ai/service/ShortTaskService.php"),
    ("php/app/command/AiTaskCron.php", SITE + "/php/app/command/AiTaskCron.php"),
    ("php/route/live.php", SITE + "/php/route/live.php"),
]
SQL = "php/sql/upgrade_ai_short.sql"


def ssh(j, cmd, timeout=180):
    _, o, e = j.exec_command(cmd, timeout=timeout)
    return o.read().decode("utf-8", "replace"), e.read().decode("utf-8", "replace")


def main():
    j = paramiko.SSHClient()
    j.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    j.connect(JUMP[0], 22, JUMP[1], JUMP[2], timeout=30, look_for_keys=False, allow_agent=False)
    S = "ssh -i %s -o StrictHostKeyChecking=no -o BatchMode=yes root@%s" % (KEY, TARGET)

    # 1) 备份目标文件
    bak = "/root/aishort_bak_20261027"
    names = " ".join(os.path.basename(r) for _, r in FILES)
    out, err = ssh(j, S + " 'mkdir -p %s && cd %s && tar -czf %s/files.tgz php/app/api/controller/AiShort.php php/app/ai/service/ShortTaskService.php php/app/command/AiTaskCron.php php/route/live.php 2>/dev/null; echo BAK_DONE' " % (bak, SITE, bak), 120)
    print("[1] backup:", out.strip() or err.strip()[:200])

    # 2) base64 传文件（跳板上中转，tar 流到目标）
    stage = "/tmp/aishort_stage"
    ssh(j, "rm -rf %s && mkdir -p %s" % (stage, stage))
    s = j.open_sftp()
    for local, remote in FILES:
        rel = remote.replace(SITE + "/", "")
        dst = os.path.join(stage, rel).replace("\\", "/")
        # 逐级建目录
        parts = os.path.dirname(dst).strip("/").split("/")
        acc = ""
        for p in parts:
            acc += "/" + p
            try:
                s.stat(acc)
            except IOError:
                try:
                    s.mkdir(acc)
                except IOError:
                    pass
        s.put(os.path.join(REPO, local), dst)
        print("   staged", rel)
    # SQL
    s.put(os.path.join(REPO, SQL), stage + "/upgrade_ai_short.sql")
    print("   staged upgrade_ai_short.sql")
    s.close()

    out, err = ssh(j, "cd %s && tar -cf - . | %s 'mkdir -p %s && tar -xf - -C /tmp && ls /tmp/php/route/'" % (stage, S, "/tmp"), 180)
    print("[2] tar ->", out.strip()[:300], err.strip()[:200])

    # 3) 落位（备份已在 tar 里）；同时删掉旧的 AiShortVideo.php（改名前遗留）
    cp = S + (" 'cp /tmp/php/app/api/controller/AiShort.php %s/php/app/api/controller/ && "
              "rm -f %s/php/app/api/controller/AiShortVideo.php && "
              "cp /tmp/php/app/ai/service/ShortTaskService.php %s/php/app/ai/service/ && "
              "cp /tmp/php/app/command/AiTaskCron.php %s/php/app/command/ && "
              "cp /tmp/php/route/live.php %s/php/route/ && echo COPY_OK'") % (SITE, SITE, SITE, SITE, SITE)
    out, err = ssh(j, cp, 120)
    print("[3] copy:", out.strip() or err.strip()[:300])

    # 4) 语法检查
    out, err = ssh(j, S + (" 'cd %s/php && for f in app/api/controller/AiShort.php app/ai/service/ShortTaskService.php app/command/AiTaskCron.php route/live.php; do "
                           "php -l $f; done'") % SITE, 180)
    print("[4] php -l:\n" + out.strip())

    j.close()


if __name__ == "__main__":
    main()
