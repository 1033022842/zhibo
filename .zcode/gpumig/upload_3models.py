#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Upload the 3 models missing from 99.11 staging straight to the GPU node.

MMH3_Perfect_fingering / MysticXXX / rife425 exist only on the AI PC (the old
staging relay never carried them), so they go over the cross-border link from
this machine. Resumable: compares remote size and appends the remainder.
"""
import os
import sys
import time

import paramiko

import sys as _s, os as _o
_s.path.insert(0, _o.path.dirname(_o.path.dirname(_o.path.abspath(__file__))))
import deploy_creds as creds  # noqa: E402

HOST, PORT, USER, PASSWORD = creds.conn("gpu166")
LOCAL = r"D:\ever\douyin\douyin\.zcode\_staging\models"

FILES = [
    ("MMH3_Perfect_fingering_v1_000002000.safetensors", "/data/models/loras", 155110392),
    ("MysticXXX_MMH3-V4.safetensors", "/data/models/loras", 155095800),
    ("rife425_ensemble_False_scale_1_sim.onnx", "/data/models/onnx", 22777748),
]


def connect():
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect(HOST, PORT, USER, PASSWORD, timeout=30, banner_timeout=30,
              auth_timeout=30, look_for_keys=False, allow_agent=False)
    return c


def mkdirs(sftp, path):
    cur = ""
    for p in path.strip("/").split("/"):
        cur += "/" + p
        try:
            sftp.stat(cur)
        except IOError:
            sftp.mkdir(cur)


def upload(name, remote_dir, want):
    local = os.path.join(LOCAL, name)
    remote = remote_dir + "/" + name
    attempt = 0
    while True:
        attempt += 1
        c = None
        try:
            c = connect()
            sftp = c.open_sftp()
            mkdirs(sftp, remote_dir)
            try:
                cur = sftp.stat(remote).st_size
            except IOError:
                cur = 0
            if cur == want:
                print("DONE %s %d" % (name, want), flush=True)
                return
            if cur > want:
                print("TRUNC %s remote=%d > want=%d" % (name, cur, want), flush=True)
                sftp.remove(remote)
                cur = 0
            print("UP %s from %d/%d (attempt %d)" % (name, cur, want, attempt), flush=True)
            t0 = time.time()
            with open(local, "rb") as lf:
                lf.seek(cur)
                rf = sftp.open(remote, "r+b" if cur else "wb")
                rf.seek(cur)
                sent = cur
                last = 0
                while True:
                    buf = lf.read(1 << 20)
                    if not buf:
                        break
                    rf.write(buf)
                    sent += len(buf)
                    if sent - last >= 16 << 20:
                        sp = (sent - cur) / max(0.001, time.time() - t0)
                        print("  %s %d/%d (%.1f%%) %.2f MB/s"
                              % (name, sent, want, 100.0 * sent / want, sp / 1048576), flush=True)
                        last = sent
                rf.close()
            got = sftp.stat(remote).st_size
            c.close()
            if got == want:
                print("DONE %s %d B" % (name, want), flush=True)
                return
            print("SHORT %s %d/%d retry" % (name, got, want), flush=True)
        except Exception as e:
            print("ERR %s attempt %d: %s" % (name, attempt, e), flush=True)
            try:
                c.close()
            except Exception:
                pass
            time.sleep(min(20, 3 * attempt))


if __name__ == "__main__":
    for name, rdir, want in FILES:
        src = os.path.join(LOCAL, name)
        if not os.path.exists(src):
            print("MISS local", src, flush=True)
            continue
        if os.path.getsize(src) != want:
            print("BAD local size", name, os.path.getsize(src), "!=", want, flush=True)
            continue
        upload(name, rdir, want)
    print("=== upload batch done ===", flush=True)
