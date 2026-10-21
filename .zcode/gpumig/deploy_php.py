#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Push the GPU-consolidation PHP changes to all four customer sites.

Files (all four sites verified identical before the change):
  app/api/controller/CustomVideo.php   deadline_at + paid_tokens/retry in content + stats()
  app/ai/service/AiTaskService.php     reapStuckCustomTasks() + refund on failTask()
  app/command/AiTaskCron.php           run the reaper every minute
  route/live.php                       GET customVideo/stats

Also ensures /etc/cron.d/douyin has the ai:task line (kiss had no cron file at all).

Pushes LF-normalised content, keeps a timestamped backup, and runs `php -l` on the
server before reloading php-fpm.
"""
import os
import sys
import time

import paramiko

REPO = os.path.abspath(os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", ".."))
SITES = [
    ("sugus", "172.81.98.55", "0wJfy`50tJQzG/0X"),
    ("heygirl", "172.81.99.11", "mw-DGckAL,61fV00"),
    ("kisss", "103.217.252.52", "Aa1033022842"),
    ("kelymi", "172.81.99.42", "ffG2dAUGc{V572{j"),
]
FILES = [
    "app/api/controller/CustomVideo.php",
    "app/ai/service/AiTaskService.php",
    "app/command/AiTaskCron.php",
    "route/live.php",
]
CRON_LINE = ("* * * * * root cd /www/wwwroot/douyin/php && php think ai:task >> "
             "/root/cron_ai_task.log 2>&1\n")


def connect(host, pw):
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect(host, 22, "root", pw, timeout=30, look_for_keys=False, allow_agent=False)
    return c


def sh(c, cmd, t=120):
    _, o, e = c.exec_command(cmd, timeout=t)
    out = o.read().decode("utf-8", "replace")
    err = e.read().decode("utf-8", "replace")
    return o.channel.recv_exit_status(), out.strip(), err.strip()


def main():
    dry = "--apply" not in sys.argv
    stamp = time.strftime("%Y%m%d%H%M")
    for name, host, pw in SITES:
        print("=" * 20, name, host, "=" * 20)
        c = connect(host, pw)
        sftp = c.open_sftp()
        bak = "/root/gpu_php_bak_%s" % stamp
        rc, o, e = sh(c, "mkdir -p %s" % bak)
        if rc != 0:
            print("  mkdir backup failed:", e)
            c.close()
            continue

        ok = True
        for rel in FILES:
            remote = "/www/wwwroot/douyin/php/" + rel
            local = os.path.join(REPO, "php", rel)
            if not os.path.exists(local):
                print("  MISSING local", local)
                ok = False
                continue
            data = open(local, "rb").read().replace(b"\r\n", b"\n")
            # backup
            sh(c, "mkdir -p %s/%s && cp -a %s %s/%s/" % (bak, os.path.dirname(rel), remote, bak, os.path.dirname(rel)))
            if dry:
                print("  [dry] would push", rel, len(data), "B")
                continue
            with sftp.open(remote, "wb") as f:
                f.write(data)
            rc, o, e = sh(c, "cd /www/wwwroot/douyin/php && php -l %s 2>&1 | tail -1" % rel)
            print("  ", rel, "->", o.strip()[:100])
            if "No syntax errors" not in o:
                ok = False

        # cron entry
        if not dry:
            rc, o, e = sh(c, "ls /etc/cron.d/douyin 2>/dev/null || echo NOFILE")
            has_file = "NOFILE" not in o
            rc, o, e = sh(c, "grep -c 'ai:task' /etc/cron.d/douyin 2>/dev/null || echo 0")
            has_line = (o.strip() or "0") != "0"
            if not has_line:
                print("   adding ai:task cron line (existing file: %s)" % has_file)
                sh(c, "printf '%s' >> /etc/cron.d/douyin" % CRON_LINE.replace("'", "'\\''"))
                sh(c, "chmod 644 /etc/cron.d/douyin")
            else:
                print("   ai:task cron already present")
            rc, o, e = sh(c, "grep 'ai:task' /etc/cron.d/douyin")

        if dry:
            print("  [dry] no changes applied")
            sftp.close()
            c.close()
            continue

        if ok:
            rc, o, e = sh(c, "systemctl reload php8.3-fpm && echo RELOADED", 120)
            print("  php-fpm:", o.strip() or e.strip())
            rc, o, e = sh(c, "cd /www/wwwroot/douyin/php && php think ai:task 2>&1 | tail -6", 180)
            print("  ai:task test output:\n" + o[:500])
        else:
            print("  !! lint failed, NOT reloading")

        sftp.close()
        c.close()


if __name__ == "__main__":
    main()
