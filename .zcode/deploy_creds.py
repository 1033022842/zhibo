#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""运维脚本的凭据入口 —— 明文口令一律不写进代码 / 不入 git。

凭据来源（低的被高的覆盖）：
  1) 本文件 _BASE 里的主机、端口、用户、库名（非敏感，便于脚本复用）
  2) .zcode/secrets/sites.json（本地文件，已 gitignore，通常只写 password）
  3) 环境变量 DOUYIN_<NAME>_<FIELD>，如 DOUYIN_HEYGIRL_PASSWORD

本地文件只需写与内置值不同的字段，例如：:

    {
      "heygirl": {"password": "..."},
      "gpu166":  {"password": "..."}
    }

用法：:

    import deploy_creds as creds
    creds.get("heygirl")["password"]           # 缺配置时抛 RuntimeError，带提示
    creds.conn("heygirl")                  # (host, port, user, password)
    creds.pair("heygirl")                  # (host, password)
    creds.site_dicts("heygirl", "kelymi")  # {"heygirl": {"host","port","password"}, ...}
    creds.db("sugus")                      # (db, db_user, db_password)

缺密码时在脚本构建配置（通常是 import 期间）就抛 RuntimeError 并提示该补哪里，
避免 paramiko 拿空口令去认证、只报一个看不出原因的 AuthenticationException。
"""
from __future__ import annotations

import json
import os
from pathlib import Path

_HERE = Path(__file__).resolve().parent
_LOCAL_FILE = _HERE / "secrets" / "sites.json"
_ENV_PREFIX = "DOUYIN_"
_FIELDS = ("host", "port", "user", "password", "db", "db_user", "db_password", "key")

# 非敏感连接信息：主机 / SSH 端口 / 登录用户 / 数据库名。
# 密码、私钥路径等敏感字段一律留空，由本地文件或环境变量补齐。
_BASE = {
    "heygirl": {"host": "172.81.99.11", "port": 22, "user": "root",
                "db": "live_platform", "db_user": "root"},
    "kelymi": {"host": "172.81.99.42", "port": 22, "user": "root",
               "db": "live_platform", "db_user": "root"},
    # 2026-11-10 旧机（103.217.252.52）故障后迁到新服务器，SSH 端口 20203
    # kiss 的库用户与其他三站不同，是 live_platform
    "kiss": {"host": "111.90.150.230", "port": 20203, "user": "root",
             "db": "live_platform", "db_user": "live_platform"},
    # sugus 走 heygirl 跳板 + 内网 key，直连密码仅个别脚本用
    "sugus": {"host": "172.81.98.55", "port": 22, "user": "root",
              "db": "zhibo", "db_user": "zhibo", "key": "/root/.ssh/n99_pull"},
    "gpu166": {"host": "38.247.166.124", "port": 22, "user": "administrator"},
    "upload_host": {"host": "155.103.194.69", "port": 22, "user": "root"},
}


def _hint(name: str, field: str) -> str:
    env = f"{_ENV_PREFIX}{name.upper()}_{field.upper()}"
    return (f"{name}.{field} 未配置：在 {_LOCAL_FILE} 里写 "
            f"{{\"{name}\": {{\"{field}\": \"...\"}}}}，或设置环境变量 {env}")


class _Entry(dict):
    """取到空字段时给出可操作的报错，而不是让 paramiko 静默认证失败。"""

    def __init__(self, name: str, data: dict):
        super().__init__(data)
        self._name = name

    def __getitem__(self, field):
        value = dict.get(self, field)
        if value is None or value == "":
            raise RuntimeError(_hint(self._name, field))
        return value

    def get(self, field, default=None):
        value = dict.get(self, field)
        return default if value is None or value == "" else value

    def __repr__(self) -> str:  # 避免日志/异常里打出密码
        return f"<creds {self._name} host={dict.get(self, 'host')}>"


def _load_local() -> dict:
    path = Path(os.environ.get("DOUYIN_SECRETS_FILE") or _LOCAL_FILE)
    if not path.exists():
        return {}
    try:
        data = json.loads(path.read_text(encoding="utf-8"))
    except json.JSONDecodeError as exc:
        raise SystemExit(f"凭据文件不是合法 JSON：{path}（{exc}）")
    if not isinstance(data, dict):
        raise SystemExit(f"凭据文件顶层必须是对象：{path}")
    return data


def _build() -> dict:
    raw = {name: dict(cfg) for name, cfg in _BASE.items()}
    for name, cfg in _load_local().items():
        if name.startswith("_"):          # "_说明" 之类的注释字段
            continue
        if not isinstance(cfg, dict):
            raise SystemExit(f"凭据文件里 {name} 必须是对象")
        raw.setdefault(name, {}).update({k: v for k, v in cfg.items() if v not in (None, "")})
    for name, cfg in raw.items():
        for field in _FIELDS:
            env = os.environ.get(f"{_ENV_PREFIX}{name.upper()}_{field.upper()}")
            if env:
                cfg[field] = int(env) if field == "port" else env
        if "port" in cfg:
            cfg["port"] = int(cfg["port"])
    return {name: _Entry(name, cfg) for name, cfg in raw.items()}


SITES: dict = _build()
NAMES = tuple(SITES)


def get(name: str) -> _Entry:
    try:
        return SITES[name]
    except KeyError:
        raise SystemExit(f"未知站点 {name!r}，可选：{', '.join(NAMES)}") from None


def conn(name: str) -> tuple:
    """(host, port, user, password)"""
    cfg = get(name)
    return cfg["host"], cfg["port"], cfg["user"], cfg["password"]


def pair(name: str) -> tuple:
    """(host, password) —— 老脚本常见的两元组写法"""
    cfg = get(name)
    return cfg["host"], cfg["password"]


def jump(name: str) -> tuple:
    """(host, user, password) —— paramiko connect(host, 22, user, password) 的三元组"""
    cfg = get(name)
    return cfg["host"], cfg["user"], cfg["password"]


def db(name: str) -> tuple:
    """(db, db_user, db_password)"""
    cfg = get(name)
    return cfg["db"], cfg["db_user"], cfg["db_password"]


def site5(name: str) -> tuple:
    """(host, password, db, db_user, db_password) —— fix_task_media_urls 这类五元组"""
    cfg = get(name)
    return cfg["host"], cfg["password"], cfg["db"], cfg["db_user"], cfg["db_password"]


def site_dicts(*names: str) -> dict:
    """{"heygirl": {"host": ..., "port": ..., "password": ...}, ...}"""
    out = {}
    for name in names:
        cfg = get(name)
        out[name] = {"host": cfg["host"], "port": cfg["port"], "password": cfg["password"]}
    return out


if __name__ == "__main__":
    for name in NAMES:
        cfg = SITES[name]
        ready = "已配置密码" if cfg.get("password") else "缺 password"
        print(f"{name:<12} {cfg.get('user')}@{cfg.get('host')}:{cfg.get('port', 22)}  [{ready}]")
