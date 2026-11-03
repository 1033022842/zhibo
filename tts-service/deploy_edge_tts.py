#!/usr/bin/env python3
"""四站一键部署 Edge TTS（Python 常驻服务 + PHP 接入）

用法：
    python tts-service/deploy_edge_tts.py                 # 四站
    python tts-service/deploy_edge_tts.py --client sugus  # 单站
    python tts-service/deploy_edge_tts.py --dry-run       # 只看计划

做的事（每台服务器）：
    1. 上传 tts-service/server.py -> /www/tts-service/
    2. 建 venv 并安装 edge-tts（缺 python3-venv 时 apt 安装）
    3. 写入 systemd 单元并 restart edge-tts
    4. 健康检查 + 真实合成一句做冒烟测试
    5. 上传并 php -l 校验 3 个 PHP 文件，reload php-fpm

前端（Chat.html / js / assets 里的样音）仍走 .zcode/sync_clients.py，保持品牌脚本链路。
服务器与凭据统一从 .zcode/sync_clients.py 的 CLIENTS 读取，不在本文件重复。
"""
import argparse
import importlib.util
import io
import os
import sys
import tarfile
import tempfile

HERE = os.path.dirname(os.path.abspath(__file__))
REPO = os.path.dirname(HERE)
SYNC_PY = os.path.join(REPO, ".zcode", "sync_clients.py")

REMOTE_SERVICE = "/www/tts-service/server.py"
REMOTE_UNIT = "/etc/systemd/system/edge-tts.service"
REMOTE_PHP = "/www/wwwroot/douyin/php"

# 本地文件 -> 服务器绝对路径（打包成 tar 后直接解到 /）
UPLOADS = [
    (os.path.join(HERE, "server.py"), REMOTE_SERVICE),
    (os.path.join(REPO, "php", "config", "tts.php"), REMOTE_PHP + "/config/tts.php"),
    (os.path.join(REPO, "php", "app", "common", "service", "EdgeTtsService.php"),
     REMOTE_PHP + "/app/common/service/EdgeTtsService.php"),
    (os.path.join(REPO, "php", "app", "common", "service", "TtsService.php"),
     REMOTE_PHP + "/app/common/service/TtsService.php"),
    (os.path.join(REPO, "php", "app", "api", "controller", "Live.php"),
     REMOTE_PHP + "/app/api/controller/Live.php"),
]

UNIT_TEMPLATE = """[Unit]
Description=Edge TTS local service (for PHP /api/live/tts)
After=network-online.target
Wants=network-online.target

[Service]
Type=simple
User=www-data
Group=www-data
Environment=EDGE_TTS_HOST=127.0.0.1
Environment=EDGE_TTS_PORT=8100
Environment=EDGE_TTS_TOKEN={token}
ExecStart=/www/tts-service/venv/bin/python /www/tts-service/server.py
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
"""

SETUP_SH = """#!/bin/bash
set -e
echo "[tts] python: $(python3 --version 2>&1)"
if [ ! -x /www/tts-service/venv/bin/pip ]; then
  mkdir -p /www/tts-service
  rm -rf /www/tts-service/venv
  if ! python3 -m venv /www/tts-service/venv >/tmp/tts_venv.log 2>&1; then
    apt-get update -qq
    DEBIAN_FRONTEND=noninteractive apt-get install -y -qq python3-venv python3-pip
    rm -rf /www/tts-service/venv
    python3 -m venv /www/tts-service/venv
  fi
fi
/www/tts-service/venv/bin/pip install -q --upgrade pip
/www/tts-service/venv/bin/pip install -q --upgrade edge-tts
systemctl daemon-reload
systemctl enable edge-tts >/dev/null 2>&1 || true
systemctl restart edge-tts
sleep 1
for i in 1 2 3 4 5; do
  if curl -fsS --max-time 5 http://127.0.0.1:8100/health; then break; fi
  [ "$i" = "5" ] && exit 1
  sleep 2
done
echo
# 首次握手偶发 “No audio received”：重试 3 次再判定失败
ok=0
for i in 1 2 3; do
  if curl -fsS --max-time 25 -H 'Content-Type: application/json' \\
    -d '{"text":"Hello, nice to meet you.","voice":"sweet"}' \\
    -o /tmp/tts_smoke.mp3 http://127.0.0.1:8100/tts && [ -s /tmp/tts_smoke.mp3 ]; then
    ok=1; break
  fi
  sleep 4
done
[ "$ok" = "1" ] || { journalctl -u edge-tts -n 20 --no-pager; exit 1; }
echo "[tts] smoke mp3 bytes=$(wc -c < /tmp/tts_smoke.mp3)"
php -l %(php)s/config/tts.php
php -l %(php)s/app/common/service/EdgeTtsService.php
php -l %(php)s/app/common/service/TtsService.php
php -l %(php)s/app/api/controller/Live.php
systemctl reload php8.3-fpm 2>/dev/null || systemctl reload php-fpm 2>/dev/null || true
echo "[tts] deploy ok"
""" % {"php": REMOTE_PHP}


def load_clients():
    if not os.path.isfile(SYNC_PY):
        sys.exit("找不到 .zcode/sync_clients.py")
    spec = importlib.util.spec_from_file_location("sync_clients", SYNC_PY)
    mod = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(mod)
    return mod.CLIENTS, mod.connect


def build_tar(token: str) -> str:
    """把服务、systemd 单元、PHP 文件、安装脚本打成一个平铺绝对路径的 tar.gz"""
    fd, path = tempfile.mkstemp(prefix="tts_deploy_", suffix=".tar.gz")
    os.close(fd)
    with tarfile.open(path, "w:gz") as tar:
        for local, remote in UPLOADS:
            if not os.path.isfile(local):
                sys.exit("缺少本地文件: " + local)
            tar.add(local, arcname=remote.lstrip("/"))
        unit = UNIT_TEMPLATE.format(token=token)
        info = tarfile.TarInfo(REMOTE_UNIT.lstrip("/"))
        data = unit.encode("utf-8")
        info.size = len(data)
        info.mode = 0o644
        tar.addfile(info, io.BytesIO(data))
        setup = tarfile.TarInfo("tmp/tts_setup.sh")
        data = SETUP_SH.encode("utf-8")
        setup.size = len(data)
        setup.mode = 0o755
        tar.addfile(setup, io.BytesIO(data))
    return path


def run_remote(cli, cmd: str, timeout: int = 900) -> str:
    stdin, stdout, stderr = cli.exec_command(cmd, timeout=timeout)
    out = stdout.read().decode("utf-8", "replace")
    err = stderr.read().decode("utf-8", "replace")
    rc = stdout.channel.recv_exit_status()
    if rc != 0:
        raise RuntimeError((err or out).strip()[-1500:])
    return (out + err).strip()


def deploy_direct(cfg, connect, tar_path: str) -> str:
    cli = connect(cfg)
    try:
        sftp = cli.open_sftp()
        sftp.put(tar_path, "/tmp/tts_deploy.tar.gz")
        sftp.close()
        return run_remote(
            cli,
            "tar -xzf /tmp/tts_deploy.tar.gz -C / && bash /tmp/tts_setup.sh",
        )
    finally:
        cli.close()


def deploy_jump(cfg, connect, tar_path: str) -> str:
    jump = cfg["jump"]
    key = cfg["key_on_jump"]
    host = cfg["host"]
    user = cfg["user"]
    j = connect(jump)
    try:
        sftp = j.open_sftp()
        sftp.put(tar_path, "/tmp/tts_deploy.tar.gz")
        sftp.close()
        remote = (
            "cat /tmp/tts_deploy.tar.gz | ssh -i %s -o StrictHostKeyChecking=no "
            "-o BatchMode=yes %s@%s 'tar -xzf - -C / && bash /tmp/tts_setup.sh'"
            % (key, user, host)
        )
        return run_remote(j, remote, timeout=900)
    finally:
        j.close()


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--client", default="all", help="all / heygirl / sugus / kelymi / kiss")
    ap.add_argument("--dry-run", action="store_true", help="只打印计划，不连服务器")
    ap.add_argument("--token", default=os.environ.get("EDGE_TTS_TOKEN", ""),
                    help="可选：服务访问令牌（同时需在 php/.env 配 TTS_EDGE_TOKEN）")
    args = ap.parse_args()

    clients, connect = load_clients()
    names = list(clients.keys()) if args.client == "all" else [args.client]
    for n in names:
        if n not in clients:
            sys.exit("未知客户: %s（可选 %s）" % (n, ", ".join(clients.keys())))

    tar_path = build_tar(args.token)
    print("打包完成: %s" % tar_path)
    for n in names:
        cfg = clients[n]
        target = cfg.get("host", "")
        via = "经跳板 " + cfg["jump"]["host"] if cfg.get("jump") else "直连"
        print(" - %-8s %s（%s）" % (n, target, via))
    if args.dry_run:
        try:
            os.remove(tar_path)
        except OSError:
            pass
        print("dry-run：未连接服务器")
        return

    failures = []
    for n in names:
        cfg = clients[n]
        print("\n===== [%s] =====" % n)
        try:
            out = deploy_jump(cfg, connect, tar_path) if cfg.get("jump") else deploy_direct(cfg, connect, tar_path)
            print(out)
        except Exception as exc:
            failures.append(n)
            print("!! 失败: %s" % exc)
    try:
        os.remove(tar_path)
    except OSError:
        pass

    if failures:
        sys.exit("\n失败站点: " + ", ".join(failures))
    print("\n全部完成。前端改动请按 SOP 走：git commit && python .zcode/sync_clients.py")


if __name__ == "__main__":
    main()
