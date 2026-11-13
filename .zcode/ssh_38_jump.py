#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""SSH to 38.247.166.124 through the 172.81.99.11 jump host (paramiko ProxyJump).

Usage:
    python .zcode/ssh_38_jump.py "<remote command>" [timeout]
"""
import sys
import paramiko

import os, sys as _s
_s.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import deploy_creds as creds  # noqa: E402

JUMP_HOST, JUMP_USER, JUMP_PASS = creds.jump("heygirl")
GPU_HOST, GPU_PORT, GPU_USER, GPU_PASS = creds.conn("gpu166")


def connect():
    jump = paramiko.SSHClient()
    jump.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    jump.connect(JUMP_HOST, 22, JUMP_USER, JUMP_PASS, timeout=30,
                 look_for_keys=False, allow_agent=False)

    transport = jump.get_transport()
    chan = transport.open_channel("direct-tcpip", (GPU_HOST, 22), ("127.0.0.1", 0), timeout=30)

    cli = paramiko.SSHClient()
    cli.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    cli.connect(GPU_HOST, 22, GPU_USER, GPU_PASS, sock=chan, timeout=30,
                banner_timeout=30, auth_timeout=30, look_for_keys=False, allow_agent=False)
    cli._jump = jump  # keep alive
    return cli


def run(cmd, timeout=120):
    cli = connect()
    try:
        _, out, err = cli.exec_command(cmd, timeout=timeout)
        o = out.read().decode("utf-8", "replace")
        e = err.read().decode("utf-8", "replace")
        code = out.channel.recv_exit_status()
        return code, o, e
    finally:
        cli.close()


if __name__ == "__main__":
    cmd = sys.argv[1] if len(sys.argv) > 1 else "hostname"
    t = int(sys.argv[2]) if len(sys.argv) > 2 else 120
    code, out, err = run(cmd, t)
    sys.stdout.write(out)
    if err:
        sys.stdout.write("\n[STDERR]\n" + err)
    sys.exit(code)
