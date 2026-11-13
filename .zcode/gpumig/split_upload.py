#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Upload a large file as N byte ranges, then concatenate on the remote and verify md5.

Why ranges instead of one stream: the link to 166.124 gives ~0.23 MB/s per SFTP
channel, so 4 ranges in parallel give ~0.9 MB/s. Each range is written to its own
file (`<remote>.part0..N-1`) and appended on resume, which is cheap; the final
`cat` + md5 check on the server is a single fast local operation.

Usage:
  python split_upload.py <local> <remote> [parts=4]
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
BLOCK = 4 << 20


def connect():
    for i in range(8):
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
        for chunk in iter(lambda: f.read(8 << 20), b""):
            h.update(chunk)
    return h.hexdigest()


def sh(cmd, t=1800):
    c = connect()
    try:
        _, o, e = c.exec_command(cmd, timeout=t)
        out = o.read().decode("utf-8", "replace")
        err = e.read().decode("utf-8", "replace")
        return o.channel.recv_exit_status(), out.strip(), err.strip()
    finally:
        c.close()


def send_range(idx, local, remote_part, start, end, stats, lock):
    pos = start
    # how much of this range already landed
    rc, out, _ = sh("stat -c%%s '%s' 2>/dev/null || echo 0" % remote_part)
    try:
        got = int(out or 0)
    except ValueError:
        got = 0
    if got > (end - start):
        sh("rm -f '%s'" % remote_part)
        got = 0
    pos = start + got
    while pos < end:
        c = None
        try:
            c = connect()
            sftp = c.open_sftp()
            with open(local, "rb") as lf:
                lf.seek(pos)
                mode = "ab" if got else "wb"
                f = sftp.open(remote_part, mode)
                while pos < end:
                    buf = lf.read(min(BLOCK, end - pos))
                    if not buf:
                        break
                    f.write(buf)
                    pos += len(buf)
                    with lock:
                        stats[0] += len(buf)
                f.close()
            sftp.close()
            c.close()
        except Exception as e:
            print("  part %d error at %d/%d: %s" % (idx, pos, end, e), flush=True)
            try:
                c.close()
            except Exception:
                pass
            time.sleep(2)


def main():
    local, remote = sys.argv[1], sys.argv[2]
    nparts = int(sys.argv[3]) if len(sys.argv) > 3 else 4
    size = os.path.getsize(local)
    want = local_md5(local)
    print("local %d B, md5 %s, %d parts" % (size, want, nparts), flush=True)

    rc, out, _ = sh("md5sum '%s' 2>/dev/null | awk '{print $1}'" % remote)
    if out == want:
        print("remote already complete & verified", flush=True)
        return

    bounds = [(size * i // nparts, size * (i + 1) // nparts) for i in range(nparts)]
    stats = [0]
    lock = threading.Lock()
    t0 = time.time()

    def report():
        while threading.active_count() > 1:
            time.sleep(20)
            with lock:
                done = stats[0]
            print("  %.1f%% (%d/%d MB) %.2f MB/s"
                  % (100.0 * done / size, done >> 20, size >> 20,
                     done / max(0.001, time.time() - t0) / 1048576), flush=True)

    ths = [threading.Thread(target=send_range,
                            args=(i, local, "%s.part%d" % (remote, i), a, b, stats, lock))
           for i, (a, b) in enumerate(bounds)]
    mon = threading.Thread(target=report, daemon=True)
    for t in ths:
        t.start()
    mon.start()
    for t in ths:
        t.join()

    parts = " ".join("'%s.part%d'" % (remote, i) for i in range(nparts))
    rc, out, err = sh("cat %s > '%s.tmp' && mv -f '%s.tmp' '%s' && rm -f %s && md5sum '%s'"
                      % (parts, remote, remote, remote, parts, remote))
    print(out, err, flush=True)
    got_md5 = out.split()[0] if out else ""
    if got_md5 != want:
        print("MD5 MISMATCH want=%s got=%s" % (want, got_md5), flush=True)
        sys.exit(3)
    print("VERIFIED %s (%d B) in %.1fs" % (remote, size, time.time() - t0), flush=True)


if __name__ == "__main__":
    main()
