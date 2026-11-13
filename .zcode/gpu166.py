#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""SSH to 38.247.166.124 (heygirl GPU node) — direct, fallback to jump host.

Usage:
    python .zcode/gpu166.py "<remote command>" [timeout] [--sudo]
"""
import sys
import paramiko

import os, sys as _s
_s.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import deploy_creds as creds  # noqa: E402

HOST, _, USER, _pw = creds.conn("gpu166")
PASSWORDS = [_pw]
JUMP = creds.jump("heygirl")


def connect_direct():
    last = None
    for pw in PASSWORDS:
        try:
            cli = paramiko.SSHClient()
            cli.set_missing_host_key_policy(paramiko.AutoAddPolicy())
            cli.connect(HOST, 22, USER, pw, timeout=25, banner_timeout=25,
                        auth_timeout=25, look_for_keys=False, allow_agent=False)
            return cli, pw
        except Exception as e:  # noqa: BLE001
            last = e
    raise RuntimeError(f"direct connect failed: {last}")


def connect_jump():
    jump = paramiko.SSHClient()
    jump.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    jump.connect(JUMP[0], 22, JUMP[1], JUMP[2], timeout=30,
                 look_for_keys=False, allow_agent=False)
    tr = jump.get_transport()
    chan = tr.open_channel("direct-tcpip", (HOST, 22), ("127.0.0.1", 0), timeout=30)
    last = None
    for pw in PASSWORDS:
        try:
            cli = paramiko.SSHClient()
            cli.set_missing_host_key_policy(paramiko.AutoAddPolicy())
            cli.connect(HOST, 22, USER, pw, sock=chan, timeout=30,
                        banner_timeout=30, auth_timeout=30,
                        look_for_keys=False, allow_agent=False)
            cli._jump = jump
            return cli, pw
        except Exception as e:  # noqa: BLE001
            last = e
    raise RuntimeError(f"jump connect failed: {last}")


def connect():
    try:
        return connect_direct()
    except Exception:  # noqa: BLE001
        return connect_jump()


def run(cmd, timeout=120):
    cli, _ = connect()
    try:
        _, out, err = cli.exec_command(cmd, timeout=timeout)
        o = out.read().decode("utf-8", "replace")
        e = err.read().decode("utf-8", "replace")
        return out.channel.recv_exit_status(), o, e
    finally:
        cli.close()


if __name__ == "__main__":
    args = [a for a in sys.argv[1:] if not a.startswith("--")]
    sudo = "--sudo" in sys.argv
    cmd = args[0] if args else "hostname; whoami"
    tmo = int(args[1]) if len(args) > 1 else 120
    if sudo:
        _, pw = connect()
        cmd = f"echo '{pw}' | sudo -S -p '' bash -c {repr(cmd)}"
    code, o, e = run(cmd, tmo)
    sys.stdout.write(o)
    if e:
        sys.stderr.write(e)
    sys.exit(code)
