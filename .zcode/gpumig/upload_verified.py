#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Upload one file to the GPU node and verify by md5, retrying until it matches.

The cross-border link drops SSH channels mid-transfer, and a partially-written
file still has the right size if it was pre-truncated — so size alone is not a
safe check here. This writes to <remote>.part, then renames only after md5
matches the local file.
"""
import hashlib
import os
import sys
import time

import paramiko

HOST, PORT, USER, PASSWORD = "38.247.166.124", 22, "administrator", "WB*!uZfGJa9K7eG@"


def connect():
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect(HOST, PORT, USER, PASSWORD, timeout=30, banner_timeout=30,
              auth_timeout=30, look_for_keys=False, allow_agent=False)
    return c


def local_md5(path):
    h = hashlib.md5()
    with open(path, "rb") as f:
        for chunk in iter(lambda: f.read(4 << 20), b""):
            h.update(chunk)
    return h.hexdigest()


def remote_md5(path):
    c = connect()
    try:
        _, o, e = c.exec_command("md5sum %s 2>/dev/null | awk '{print $1}'" % path, timeout=300)
        return o.read().decode().strip()
    finally:
        c.close()


def main():
    local, remote = sys.argv[1], sys.argv[2]
    want = local_md5(local)
    size = os.path.getsize(local)
    print("local md5 %s size %d" % (want, size), flush=True)

    if remote_md5(remote) == want:
        print("already uploaded and verified", flush=True)
        return

    part = remote + ".part"
    for attempt in range(1, 15):
        have = 0
        c = connect()
        try:
            sftp = c.open_sftp()
            try:
                have = sftp.stat(part).st_size
            except IOError:
                have = 0
            if have > size:
                sftp.remove(part)
                have = 0
            print("attempt %d: resume from %d/%d" % (attempt, have, size), flush=True)
            t0 = time.time()
            with open(local, "rb") as lf:
                lf.seek(have)
                f = sftp.open(part, "r+b" if have else "wb")
                f.seek(have)
                pos = have
                while True:
                    buf = lf.read(1 << 20)
                    if not buf:
                        break
                    f.write(buf)
                    pos += len(buf)
                    if pos % (32 << 20) < (1 << 20):
                        sp = (pos - have) / max(0.001, time.time() - t0)
                        print("  %d/%d (%.1f%%) %.2f MB/s"
                              % (pos, size, 100.0 * pos / size, sp / 1048576), flush=True)
                f.close()
            sftp.close()
        except Exception as e:
            print("  stream error: %s" % e, flush=True)
        finally:
            try:
                c.close()
            except Exception:
                pass

        if remote_md5(part) == want:
            c = connect()
            sftp = c.open_sftp()
            try:
                sftp.remove(remote)
            except IOError:
                pass
            sftp.rename(part, remote)
            sftp.close()
            c.close()
            print("VERIFIED %s (%d B) in %d attempt(s)" % (remote, size, attempt), flush=True)
            return
        got = remote_md5(part)
        print("  md5 mismatch (want %s got %s), retrying" % (want, got), flush=True)
        time.sleep(3)

    print("FAILED after retries", flush=True)
    sys.exit(1)


if __name__ == "__main__":
    main()
