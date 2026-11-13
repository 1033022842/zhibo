#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Resumable parallel SFTP upload with per-chunk completion markers and md5 verify.

The link from this machine to 166.124 is ~0.23 MB/s per stream and drops channels
mid-transfer; four streams get ~0.9 MB/s. Because a dropped channel can leave a
correctly-sized but incomplete file, resume state is tracked in a sidecar
`<remote>.chunks` (one completed chunk index per line), and the final file is
renamed into place only after its md5 matches the local file.

Usage:
  python parallel_upload.py <local> <remote> [streams=4] [chunk_mb=8]
"""
import hashlib
import os
import sys
import threading
import time

import paramiko

import sys as _s, os as _o
_s.path.insert(0, _o.path.dirname(_o.path.dirname(_o.path.abspath(__file__))))
import deploy_creds as creds  # noqa: E402

HOST, PORT, USER, PASSWORD = creds.conn("gpu166")


def connect():
    for i in range(6):
        try:
            c = paramiko.SSHClient()
            c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
            c.connect(HOST, PORT, USER, PASSWORD, timeout=40, banner_timeout=40,
                      auth_timeout=40, look_for_keys=False, allow_agent=False)
            return c
        except Exception as e:
            print("  connect retry %d: %s" % (i + 1, e), flush=True)
            time.sleep(2 + 2 * i)
    raise RuntimeError("cannot connect")


def local_md5(path):
    h = hashlib.md5()
    with open(path, "rb") as f:
        for chunk in iter(lambda: f.read(4 << 20), b""):
            h.update(chunk)
    return h.hexdigest()


def remote_md5(path):
    c = connect()
    try:
        _, o, _ = c.exec_command("md5sum '%s' 2>/dev/null | awk '{print $1}'" % path, timeout=600)
        return o.read().decode().strip()
    finally:
        c.close()


def load_done(marker):
    c = connect()
    try:
        sftp = c.open_sftp()
        try:
            with sftp.open(marker, "r") as f:
                txt = f.read().decode()
        except IOError:
            txt = ""
        sftp.close()
        return {int(x) for x in txt.split() if x.strip().isdigit()}
    finally:
        c.close()


def save_done(marker, done, lock, added):
    with lock:
        done |= set(added)
        payload = "\n".join(str(i) for i in sorted(done)) + "\n"
    c = connect()
    try:
        sftp = c.open_sftp()
        with sftp.open(marker, "w") as f:
            f.write(payload)
        sftp.close()
    finally:
        c.close()


def main():
    local, remote = sys.argv[1], sys.argv[2]
    nstr = int(sys.argv[3]) if len(sys.argv) > 3 else 4
    cmb = int(sys.argv[4]) if len(sys.argv) > 4 else 8
    chunk = cmb << 20

    size = os.path.getsize(local)
    nchunks = (size + chunk - 1) // chunk
    want = local_md5(local)
    part = remote + ".part"
    marker = remote + ".chunks"
    print("local %d B, %d chunks of %d MiB, md5 %s" % (size, nchunks, cmb, want), flush=True)

    if remote_md5(remote) == want:
        print("remote already complete & verified", flush=True)
        return

    # ensure the part file is the right size
    c = connect()
    sftp = c.open_sftp()
    try:
        cur = sftp.stat(part).st_size
    except IOError:
        cur = -1
    if cur != size:
        print("create %s at %d B" % (part, size), flush=True)
        f = sftp.open(part, "wb")
        f.truncate(size)
        f.close()
    sftp.close()
    c.close()

    done = load_done(marker)
    todo = [i for i in range(nchunks) if i not in done]
    print("chunks already done: %d, to send: %d" % (len(done), len(todo)), flush=True)

    lock = threading.Lock()
    sent = [0]
    t0 = time.time()
    q = list(todo)
    qlock = threading.Lock()

    def work(idx_stream):
        while True:
            with qlock:
                if not q:
                    return
                ci = q.pop(0)
            off = ci * chunk
            n = min(chunk, size - off)
            for attempt in range(1, 9):
                c = None
                try:
                    c = connect()
                    sftp = c.open_sftp()
                    with open(local, "rb") as lf:
                        lf.seek(off)
                        buf = lf.read(n)
                    f = sftp.open(part, "r+b")
                    f.seek(off)
                    f.write(buf)
                    f.close()
                    sftp.close()
                    c.close()
                    save_done(marker, done, lock, [ci])
                    with lock:
                        sent[0] += n
                        if sent[0] % (64 << 20) < (16 << 20):
                            sp = sent[0] / max(0.001, time.time() - t0)
                            print("  %.1f%% (%d MB) %.2f MB/s"
                                  % (100.0 * sent[0] / size, sent[0] >> 20, sp / 1048576), flush=True)
                    break
                except Exception as e:
                    print("  chunk %d attempt %d: %s" % (ci, attempt, e), flush=True)
                    try:
                        c.close()
                    except Exception:
                        pass
                    time.sleep(min(10, attempt * 2))

    ths = [threading.Thread(target=work, args=(i,), daemon=True) for i in range(nstr)]
    for t in ths:
        t.start()
    for t in ths:
        t.join()

    done = load_done(marker)
    missing = [i for i in range(nchunks) if i not in done]
    print("chunks done %d/%d, missing %d" % (len(done), nchunks, len(missing)), flush=True)
    if missing:
        print("INCOMPLETE — re-run to resume", flush=True)
        sys.exit(2)

    print("verifying md5 (this reads %d MB on the remote)..." % (size >> 20), flush=True)
    if remote_md5(part) != want:
        print("MD5 MISMATCH — deleting part file, please re-run", flush=True)
        c = connect()
        sftp = c.open_sftp()
        sftp.remove(part)
        sftp.remove(marker)
        sftp.close()
        c.close()
        sys.exit(3)

    c = connect()
    sftp = c.open_sftp()
    try:
        sftp.remove(remote)
    except IOError:
        pass
    sftp.rename(part, remote)
    try:
        sftp.remove(marker)
    except IOError:
        pass
    sftp.close()
    c.close()
    print("VERIFIED & INSTALLED %s (%d B) in %.1fs"
          % (remote, size, time.time() - t0), flush=True)


if __name__ == "__main__":
    main()
