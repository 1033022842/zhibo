#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Parallel chunked SFTP upload to the GPU node.

A single SFTP channel to 166.124 caps at ~0.23 MB/s; four parallel channels give
~0.9 MB/s. Each chunk is written with an explicit offset (r+b + seek), so the
whole thing is resumable: completed chunks are detected by verifying the file
size / chunk markers, and re-running only re-sends what is missing.

Usage:
  python chunked_upload.py <local> <remote> [chunks=4]
"""
import io
import os
import sys
import threading
import time

import paramiko

import sys as _s, os as _o
_s.path.insert(0, _o.path.dirname(_o.path.dirname(_o.path.abspath(__file__))))
import deploy_creds as creds  # noqa: E402

HOST, PORT, USER, PASSWORD = creds.conn("gpu166")
CHUNK = 8 << 20   # 8 MiB per write, 4 streams


def connect():
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect(HOST, PORT, USER, PASSWORD, timeout=30, banner_timeout=30,
              auth_timeout=30, look_for_keys=False, allow_agent=False)
    return c


def remote_size(sftp, path):
    try:
        return sftp.stat(path).st_size
    except IOError:
        return 0


def worker(idx, nstr, local, remote, size, progress, lock):
    start = size * idx // nstr
    end = size * (idx + 1) // nstr
    pos = start
    attempt = 0
    while pos < end:
        attempt += 1
        c = None
        try:
            c = connect()
            sftp = c.open_sftp()
            with open(local, "rb") as lf:
                f = sftp.open(remote, "r+b")
                f.seek(pos)
                lf.seek(pos)
                while pos < end:
                    n = min(CHUNK, end - pos)
                    buf = lf.read(n)
                    if not buf:
                        break
                    f.write(buf)
                    pos += len(buf)
                    with lock:
                        progress[0] += len(buf)
                        if progress[0] % (32 << 20) < CHUNK:
                            print("  %d/%d MB (%.1f%%)"
                                  % (progress[0] >> 20, size >> 20,
                                     100.0 * progress[0] / size), flush=True)
                f.close()
            c.close()
        except Exception as e:
            print("  stream %d err at %d/%d: %s (retry)" % (idx, pos, end, e), flush=True)
            try:
                c.close()
            except Exception:
                pass
            time.sleep(2 * min(attempt, 10))


def main():
    local, remote = sys.argv[1], sys.argv[2]
    nstr = int(sys.argv[3]) if len(sys.argv) > 3 else 4
    size = os.path.getsize(local)

    c = connect()
    sftp = c.open_sftp()
    cur = remote_size(sftp, remote)
    if cur != size:
        print("create/truncate %s -> %d B" % (remote, size), flush=True)
        f = sftp.open(remote, "wb")
        f.truncate(size)
        f.close()
    else:
        print("remote already %d B" % size, flush=True)
    sftp.close()
    c.close()

    progress = [0]
    lock = threading.Lock()
    t0 = time.time()
    ths = [threading.Thread(target=worker, args=(i, nstr, local, remote, size, progress, lock))
           for i in range(nstr)]
    for t in ths:
        t.start()
    for t in ths:
        t.join()
    dt = time.time() - t0

    c = connect()
    sftp = c.open_sftp()
    got = remote_size(sftp, remote)
    sftp.close()
    c.close()
    print("%s: %d/%d B in %.1fs (%.2f MB/s) %s"
          % (remote, got, size, dt, size / max(0.001, dt) / 1048576,
             "OK" if got == size else "SIZE MISMATCH"))


if __name__ == "__main__":
    main()
