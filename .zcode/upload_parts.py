#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""upload_parts.py —— 把 staging 打包并多线程分片上传到存储服务器，解包后按项目前缀写入对象存储

为什么要分片并行：本机到机房的单流约 0.1~0.3 MB/s，4 路并行能到 0.8~1 MB/s。

用法：
  python .zcode/upload_parts.py --tar .zcode/chat_gifts_new.tar --remote-dir /root/cg_new [--parts 4]
"""
import argparse
import os
import threading
import time

import paramiko

HOST = "155.103.194.69"
PASSWORD = "MyoSTyXKK4nV4B8fkmNI"
MEDIA_KEY = "50ec0e642ccf97ce9c85b19c76b5360d561c4a461bbd1888"


def connect():
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect(HOST, username="root", password=PASSWORD, timeout=30,
              look_for_keys=False, allow_agent=False)
    return c


def run(cmd, timeout=3600):
    c = connect()
    _, out, err = c.exec_command(cmd, timeout=timeout)
    o = out.read().decode("utf-8", "ignore") + err.read().decode("utf-8", "ignore")
    c.close()
    return o


def split(path, n, out_dir):
    size = os.path.getsize(path)
    chunk = (size + n - 1) // n
    parts = []
    with open(path, "rb") as f:
        for i in range(n):
            p = os.path.join(out_dir, f"part{i:02d}")
            with open(p, "wb") as g:
                remaining = chunk
                while remaining > 0:
                    buf = f.read(min(4 << 20, remaining))
                    if not buf:
                        break
                    g.write(buf)
                    remaining -= len(buf)
            parts.append(p)
    return parts


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--tar", required=True)
    ap.add_argument("--parts", type=int, default=6)
    args = ap.parse_args()
    # 远端目录写死：Git Bash 会把命令行里的 /root/... 当作 Windows 路径做 MSYS 转换
    args.remote_dir = "/root/cg_new"

    print(run("mkdir -p /root/cg_new && echo ok"))
    t0 = time.time()
    parts = split(args.tar, args.parts, os.path.dirname(os.path.abspath(args.tar)))
    print(f"split into {len(parts)} parts in {time.time()-t0:.0f}s")

    results = []
    lock = threading.Lock()

    def up(p):
        name = os.path.basename(p)
        c = connect()
        s = c.open_sftp()
        t = time.time()
        s.put(p, f"{args.remote_dir}/{name}")
        dt = time.time() - t
        s.close()
        c.close()
        with lock:
            results.append((name, dt, os.path.getsize(p)))
            print(f"  {name}: {os.path.getsize(p)/1048576:.0f} MB in {dt:.0f}s "
                  f"({os.path.getsize(p)/1048576/max(dt,0.01):.2f} MB/s)")

    ths = [threading.Thread(target=up, args=(p,)) for p in parts]
    [t.start() for t in ths]
    [t.join() for t in ths]

    total = sum(r[2] for r in results)
    print(f"\nuploaded {total/1048576:.0f} MB in {time.time()-t0:.0f}s "
          f"({total/1048576/(time.time()-t0):.2f} MB/s)")

    print(run(f"cd {args.remote_dir} && cat part* > all.tar && rm -f part* && "
              f"ls -l all.tar && tar -tf all.tar | head -3"))
    for p in parts:
        os.remove(p)


if __name__ == "__main__":
    main()
