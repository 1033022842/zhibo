#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""SSH helper for the consolidated GPU node 38.247.166.124.

  python .zcode/gpu2.py "<cmd>" [timeout]          # run as administrator
  python .zcode/gpu2.py --sudo "<cmd>" [timeout]   # run via sudo, password on stdin
  python .zcode/gpu2.py --put <local> <remote>     # upload
  python .zcode/gpu2.py --get <remote> <local>     # download
"""
import os
import shlex
import sys
import paramiko

HOST = "38.247.166.124"
PORT = 22
USER = "administrator"
PASSWORD = "WB*!uZfGJa9K7eG@"


def connect(timeout=30):
    cli = paramiko.SSHClient()
    cli.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    cli.connect(HOST, port=PORT, username=USER, password=PASSWORD,
                timeout=timeout, banner_timeout=timeout, auth_timeout=timeout,
                look_for_keys=False, allow_agent=False)
    return cli


def run(cmd, timeout=180, sudo=False):
    cli = connect()
    try:
        if sudo:
            real = "sudo -S -p '' bash -lc " + shlex.quote(cmd)
        else:
            real = "bash -lc " + shlex.quote(cmd)
        stdin, out, err = cli.exec_command(real, timeout=timeout)
        if sudo:
            stdin.write(PASSWORD + "\n")
            stdin.flush()
        o = out.read().decode("utf-8", "replace")
        e = err.read().decode("utf-8", "replace")
        return out.channel.recv_exit_status(), o, e
    finally:
        cli.close()


def put(local, remote):
    cli = connect()
    try:
        sftp = cli.open_sftp()
        sftp.put(local, remote)
        return sftp.stat(remote).st_size
    finally:
        cli.close()


def get(remote, local):
    cli = connect()
    try:
        sftp = cli.open_sftp()
        sftp.get(remote, local)
        return os.path.getsize(local)
    finally:
        cli.close()


if __name__ == "__main__":
    args = sys.argv[1:]
    sudo = False
    if args and args[0] == "--sudo":
        sudo = True
        args = args[1:]
    if args and args[0] == "--put":
        n = put(args[1], args[2])
        print("uploaded %d B -> %s" % (n, args[2]))
        sys.exit(0)
    if args and args[0] == "--get":
        n = get(args[1], args[2])
        print("downloaded %d B -> %s" % (n, args[2]))
        sys.exit(0)
    cmd = args[0] if args else "uname -a"
    t = int(args[1]) if len(args) > 1 else 180
    code, out, err = run(cmd, t, sudo=sudo)
    sys.stdout.write(out)
    if err:
        sys.stdout.write("\n[STDERR]\n" + err)
    sys.exit(code)
