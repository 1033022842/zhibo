#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""轮询 QA 进度，跑完自动打包下载并生成对比报告。

本机运行，避免 bash 在 Windows 下的退出码问题。
"""
import os
import subprocess
import sys
import time

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
import gpu  # noqa: E402

QA = "/opt/cv/short/qa"
LOCAL = r"D:\ever\douyin\qa_out"
POLL = 240
MAX_WAIT = 8 * 3600


def ssh(cmd, timeout=300):
    cli = gpu.connect()
    try:
        _, o, e = cli.exec_command(cmd, timeout=timeout)
        return o.read().decode("utf-8", "replace"), e.read().decode("utf-8", "replace")
    finally:
        cli.close()


def count_done():
    out, _ = ssh("wc -l < %s/results.jsonl 2>/dev/null || echo 0" % QA, 120)
    try:
        return int(out.strip() or 0)
    except ValueError:
        return 0


def alive():
    out, _ = ssh("pgrep -c -f 'python qa_50.py' 2>/dev/null || echo 0", 120)
    try:
        return int(out.strip() or 0)
    except ValueError:
        return 0


def main():
    t0 = time.time()
    last = -1
    while time.time() - t0 < MAX_WAIT:
        n = count_done()
        a = alive()
        print("[%s] 已完成 %d/50，进程 %s" % (time.strftime("%H:%M:%S"), n, "运行中" if a else "已退出"),
              flush=True)
        if n >= 50:
            break
        if not a and n == last:
            print("进程已退出且进度未变，停止等待", flush=True)
            break
        last = n
        time.sleep(POLL)

    print("打包远端产物…", flush=True)
    ssh("cd %s && rm -f /tmp/qa2_videos.tgz /tmp/qa2_sheets.tgz && "
        "tar -czf /tmp/qa2_videos.tgz videos/ && tar -czf /tmp/qa2_sheets.tgz sheets/ && "
        "cp results.jsonl /tmp/qa2_results.jsonl && ls -lh /tmp/qa2_*" % QA, 1800)

    cli = gpu.connect()
    s = cli.open_sftp()
    os.makedirs(LOCAL, exist_ok=True)
    for remote, local in [("/tmp/qa2_videos.tgz", os.path.join(LOCAL, "v2_videos.tgz")),
                          ("/tmp/qa2_sheets.tgz", os.path.join(LOCAL, "v2_sheets.tgz")),
                          ("/tmp/qa2_results.jsonl", os.path.join(LOCAL, "v2_results.jsonl"))]:
        s.get(remote, local)
        print("  下载 %-20s %8.2f MB" % (os.path.basename(local), os.path.getsize(local) / 1024 / 1024),
              flush=True)
    s.close()
    cli.close()
    print("DOWNLOAD_DONE", flush=True)


if __name__ == "__main__":
    main()
