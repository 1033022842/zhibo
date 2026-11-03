#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""SSH helper for 38.247.166.124, tunnelled through 172.81.99.11 (Tokyo relay).

The direct cross-border path to 166.124 gets blocked for hours at a time; 99.11
reaches it reliably, so all control/transfer traffic goes through a jump channel.

  python .zcode/gpu.py "<cmd>" [timeout]
  python .zcode/gpu.py --sudo "<cmd>" [timeout]
  python .zcode/gpu.py --put <local> <remote>
  python .zcode/gpu.py --get <remote> <local>
  python .zcode/gpu.py --get-cdn <remote> <local>      # 大文件走 CDN，约 7 倍速
  python .zcode/gpu.py --pull-cdn <remote_dir> <local_dir> [pattern]

=== 为什么有 --get-cdn / --pull-cdn ===
SSH/SFTP 跨境直连实测只有 17 KB/s（高延迟下每包等 ACK，效率极差）。
实测经本机梯子直连 sugus.ai 可达 121-255 KB/s。

链路：GPU --ssh(modelpull)--> sugus 网站 /media/qa_tmp/ --> 本地经梯子 curl
临时目录公开可访问，下载完自动删除。需要 GPU 上有 modelpull key
（已在 98.55 的 authorized_keys 里）。
"""
import os
import shlex
import socket
import subprocess
import sys
import time
import urllib.parse

import paramiko

JUMP_HOST, JUMP_PORT, JUMP_USER, JUMP_PASS = "172.81.99.11", 22, "root", "***REMOVED-SSH-PW***"
# 2026-11-03：同 IP 换新机（原机 11-01 失联后供应商重开），全新系统，按附录 B 重装中。
# 登录名沿用 administrator；失联史见 docs/单GPU四站部署.md 顶部事件记录。
HOST, PORT, USER, PASSWORD = "38.247.166.124", 22, "administrator", "***REMOVED-SSH-PW***"
# 历史密码留档：***REMOVED-SSH-PW***（2026-11-01 前的旧机）

# CDN 中继（sugus 网站服务器）
SITE_HOST, SITE_USER = "172.81.98.55", "root"
SITE_KEY = "/home/administrator/.ssh/modelpull"
PUB_DIR = "/www/wwwroot/douyin/ai-girl-malaysia.com/media/qa_tmp"
PUB_URL = "https://sugus.ai/ai/media/qa_tmp"
PROXY = os.environ.get("GPU_CDN_PROXY", "socks5h://127.0.0.1:7891")


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


# ── CDN 中继下载（大文件推荐，比 SFTP 快约 7 倍）────────────────────
def _site_prepare(task):
    """在网站服务器上清理并建好临时目录"""
    code, out, err = run(
        "ssh -i %s -o BatchMode=yes -o StrictHostKeyChecking=no %s@%s "
        "'rm -rf %s/%s && mkdir -p %s/%s && echo READY'"
        % (SITE_KEY, SITE_USER, SITE_HOST, PUB_DIR, task, PUB_DIR, task), timeout=120)
    if "READY" not in out:
        raise RuntimeError("准备远端目录失败: %s %s" % (out[:200], err[:200]))


def _site_cleanup(task):
    run("ssh -i %s -o BatchMode=yes -o StrictHostKeyChecking=no %s@%s "
        "'rm -rf %s/%s'"
        % (SITE_KEY, SITE_USER, SITE_HOST, PUB_DIR, task), timeout=120)


def _site_upload(remote_dir, pattern, task):
    """GPU → 网站服务器：用 tar -T 清单避开文件名空格问题

    pattern 可以是通配符（*.mp4）也可以是精确文件名；含空格时需由调用方
    保证 remote_dir 与文件名分开传入。
    """
    manifest = "/tmp/_xfer_%s.txt" % task
    # 用 find 而不是 ls：通配符不做 shell 展开（避免 shlex.quote 把 *.mp4 变字面量），
    # 且文件多时 ls 输出可能被分页/截断
    code, cnt, err = run(
        "cd {rd} && find . -maxdepth 1 -type f -name {pat} -printf '%f\\n' > {mf} && wc -l < {mf}".format(
            rd=shlex.quote(remote_dir), pat=shlex.quote(pattern), mf=manifest),
        timeout=120)
    n = cnt.strip()
    if not n.isdigit() or int(n) == 0:
        raise RuntimeError("GPU 上 %s 下没有匹配 %r 的文件（%s）"
                           % (remote_dir, pattern, err.strip()[:150]))
    print("  待传 %s 个文件" % n)
    _site_prepare(task)
    code, out, err = run(
        "set -e; cd {rd} && tar -cf - -T {mf} | "
        "ssh -i {key} -o StrictHostKeyChecking=no -o BatchMode=yes {user}@{host} "
        "'tar -xf - -C {pub}/{task}' && echo TRANSFER_OK"
        .format(rd=shlex.quote(remote_dir), mf=manifest, key=SITE_KEY,
                user=SITE_USER, host=SITE_HOST, pub=PUB_DIR, task=task), timeout=3600)
    if "TRANSFER_OK" not in out:
        raise RuntimeError("传输失败: %s %s" % (out[:200], err[:300]))


def _site_list(task):
    """列出网站服务器临时目录里的文件（按行切，文件名可能含空格）"""
    code, out, err = run(
        "ssh -i %s -o BatchMode=yes -o StrictHostKeyChecking=no %s@%s ls -1 %s/%s"
        % (SITE_KEY, SITE_USER, SITE_HOST, PUB_DIR, task), timeout=180)
    return [ln.strip() for ln in out.splitlines() if ln.strip()]


def _cdn_fetch(task, files, local_dir, quiet=False):
    """本地经梯子从 sugus.ai 拉文件"""
    os.makedirs(local_dir, exist_ok=True)
    t0 = time.time()
    ok = fail = 0
    got = 0
    for fn in files:
        lp = os.path.join(local_dir, fn)
        url = "%s/%s/%s" % (PUB_URL, task, urllib.parse.quote(fn))
        r = subprocess.run(
            ["curl", "-s", "-x", PROXY, "-o", lp, "-w", "%{http_code} %{size_download}",
             "--max-time", "900", url], capture_output=True, text=True)
        parts = (r.stdout or "").split()
        if len(parts) == 2 and parts[0] == "200" and int(parts[1]) > 0:
            ok += 1
            got += int(parts[1])
            if not quiet:
                print("  OK  %-42s %.1f MB" % (fn, int(parts[1]) / 1024 / 1024), flush=True)
        else:
            fail += 1
            if os.path.exists(lp):
                os.remove(lp)
            if not quiet:
                print("  FAIL %-42s %s" % (fn, (r.stdout or r.stderr)[:50]), flush=True)
    dt = time.time() - t0
    kbs = got / 1024 / dt if dt else 0
    print("CDN 下载: %d 成功 / %d 失败，%.0fs (%.0f KB/s)" % (ok, fail, dt, kbs))
    return ok, fail


def pull_cdn(remote_dir, local_dir, pattern="*", quiet=False):
    """目录级：GPU 目录 → 本地（经 CDN 中继）"""
    task = os.path.basename(remote_dir.rstrip("/")) or "pull"
    print("=== GPU → 网站服务器 → 本地（CDN 中继）")
    _site_upload(remote_dir, pattern, task)
    try:
        files = [f for f in _site_list(task) if not f.startswith(".")
                 and (pattern == "*" or f.endswith(pattern.lstrip("*")))]
        print("  线上文件数:", len(files))
        if not files:
            raise RuntimeError("线上目录为空")
        return _cdn_fetch(task, files, local_dir, quiet)
    finally:
        _site_cleanup(task)
        print("  已清理线上临时目录")


def get_cdn(remote_file, local_file):
    """单文件：GPU 文件 → 本地（经 CDN 中继）"""
    d = os.path.dirname(remote_file.rstrip("/"))
    fn = os.path.basename(remote_file)
    out_dir = os.path.dirname(os.path.abspath(local_file))
    task = "single_%d" % int(time.time())
    _site_upload(d, fn, task)
    try:
        files = [f for f in _site_list(task) if not f.startswith(".")]
        if not files:
            raise RuntimeError("线上目录为空")
        tmp_dir = os.path.join(out_dir, ".cdn_tmp_%s" % task)
        _cdn_fetch(task, files, tmp_dir, quiet=True)
        os.makedirs(out_dir, exist_ok=True)
        os.replace(os.path.join(tmp_dir, files[0]), local_file)
        try:
            os.rmdir(tmp_dir)
        except OSError:
            pass
        return os.path.getsize(local_file)
    finally:
        _site_cleanup(task)


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
    if args and args[0] == "--get-cdn":
        print("downloaded", get_cdn(args[1], args[2]), "B ->", args[2])
        sys.exit(0)
    if args and args[0] == "--pull-cdn":
        pat = args[3] if len(args) > 3 else "*"
        ok, fail = pull_cdn(args[1], args[2], pat)
        sys.exit(0 if fail == 0 else 1)
    cmd = args[0] if args else "uname -a"
    t = int(args[1]) if len(args) > 1 else 180
    code, out, err = run(cmd, t, sudo=sudo)
    sys.stdout.write(out)
    if err:
        sys.stdout.write("\n[STDERR]\n" + err)
    sys.exit(code)
