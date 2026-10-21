#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""SSH helper for 38.247.166.124, tunnelled through 172.81.99.11 (Tokyo relay).

The direct cross-border path to 166.124 gets blocked for hours at a time; 99.11
reaches it reliably, so all control/transfer traffic goes through a jump channel.

  python .zcode/gpu.py "<cmd>" [timeout]
  python .zcode/gpu.py --sudo "<cmd>" [timeout]
  python .zcode/gpu.py --put <local> <remote>
  python .zcode/gpu.py --get <remote> <local>
"""
import os
import shlex
import socket
import sys
import time

import paramiko

JUMP_HOST, JUMP_PORT, JUMP_USER, JUMP_PASS = "172.81.99.11", 22, "root", "mw-DGckAL,61fV00"
HOST, PORT, USER, PASSWORD = "38.247.166.124", 22, "administrator", "WB*!uZfGJa9K7eG@"


class Jump(socket.socket):
    """socket-like object that tunnels through a paramiko jump channel."""

    def __init__(self, jump_client, host, port):
        super().__init__(socket.AF_INET, socket.SOCK_STREAM)
        self._t = jump_client.get_transport()
        self._ch = self._t.open_channel("direct-tcpip", (host, port), ("127.0.0.1", 0))

    def recv(self, n):
        return self._ch.recv(n)

    def send(self, data):
        return self._ch.send(data)

    def sendall(self, data):
        self._ch.sendall(data)

    def settimeout(self, t):
        self._ch.settimeout(t)

    def gettimeout(self):
        return self._ch.gettimeout()

    def close(self):
        try:
            self._ch.close()
        finally:
            super().close()

    def fileno(self):
        return self._ch.fileno()


def _jump():
    j = paramiko.SSHClient()
    j.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    j.connect(JUMP_HOST, JUMP_PORT, JUMP_USER, JUMP_PASS, timeout=30,
              banner_timeout=30, auth_timeout=30, look_for_keys=False, allow_agent=False)
    return j


def connect(timeout=40, use_jump=True, tries=5):
    last = None
    for i in range(tries):
        jump = None
        try:
            cli = paramiko.SSHClient()
            cli.set_missing_host_key_policy(paramiko.AutoAddPolicy())
            if use_jump:
                jump = _jump()
                sock = Jump(jump, HOST, PORT)
                sock.settimeout(timeout)
            else:
                sock = None
            cli.connect(HOST, PORT, USER, PASSWORD, sock=sock, timeout=timeout,
                        banner_timeout=timeout, auth_timeout=timeout,
                        look_for_keys=False, allow_agent=False)
            cli._jump = jump  # keep the jump channel alive
            return cli
        except Exception as e:
            last = e
            try:
                if jump:
                    jump.close()
            except Exception:
                pass
            if i + 1 < tries:
                time.sleep(2 + 2 * i)
    raise last


def run(cmd, timeout=180, sudo=False):
    cli = connect()
    try:
        inner = ("sudo -S -p '' bash -lc " if sudo else "bash -lc ") + shlex.quote(cmd)
        stdin, out, err = cli.exec_command(inner, timeout=timeout)
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
        print("uploaded", put(args[1], args[2]), "B ->", args[2])
        sys.exit(0)
    if args and args[0] == "--get":
        print("downloaded", get(args[1], args[2]), "B ->", args[2])
        sys.exit(0)
    cmd = args[0] if args else "uname -a"
    t = int(args[1]) if len(args) > 1 else 180
    code, out, err = run(cmd, t, sudo=sudo)
    sys.stdout.write(out)
    if err:
        sys.stdout.write("\n[STDERR]\n" + err)
    sys.exit(code)
