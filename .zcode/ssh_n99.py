#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""SSH helper for the SECOND new server 172.81.99.11"""
import sys
import paramiko

import os, sys as _s
_s.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import deploy_creds as creds  # noqa: E402

HOST = creds.get("heygirl")["host"]
USER = "root"
PASSWORD = creds.get("heygirl")["password"]

def run(cmd, timeout=120):
    cli = paramiko.SSHClient()
    cli.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    cli.connect(HOST, port=22, username=USER, password=PASSWORD, timeout=30,
                look_for_keys=False, allow_agent=False)
    try:
        _, out, err = cli.exec_command(cmd, timeout=timeout)
        o = out.read().decode("utf-8", "replace")
        e = err.read().decode("utf-8", "replace")
        return out.channel.recv_exit_status(), o, e
    finally:
        cli.close()

if __name__ == "__main__":
    cmd = sys.argv[1] if len(sys.argv) > 1 else "hostname"
    t = int(sys.argv[2]) if len(sys.argv) > 2 else 120
    rc, o, e = run(cmd, t)
    sys.stdout.write(o)
    if e.strip():
        sys.stdout.write("\n[STDERR]\n" + e)
    sys.exit(rc)
