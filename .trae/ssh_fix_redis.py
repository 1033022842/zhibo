import sys
import paramiko

HOST = "38.181.44.164"
USER = "root"
PWD = "Mr3$Ye7]Dx7|"

# 1. 修复 ThinkPHP .env 的 Redis 密码
fix_env = r'''
import re
p = "/www/wwwroot/douyin/php/.env"
s = open(p, encoding="utf-8").read()
lines = s.splitlines()
in_redis = False
for i, ln in enumerate(lines):
    t = ln.strip()
    if t.startswith("[REDIS]"):
        in_redis = True
    elif t.startswith("[") and t != "[REDIS]":
        in_redis = False
    elif in_redis and t.startswith("PASSWORD"):
        lines[i] = "PASSWORD = redis4live2026"
open(p, "w", encoding="utf-8").write("\n".join(lines) + "\n")
print("ENV_FIXED")
'''

CMDS = [
    "python3 -c " + repr(fix_env) if False else "python3 - <<'PY'\n" + fix_env + "PY",
    "grep -iA4 '\\[REDIS\\]' /www/wwwroot/douyin/php/.env",
    "rm -rf /www/wwwroot/douyin/php/runtime/cache/* 2>/dev/null; echo CACHE_CLEARED",
    # 用正确密码写关键词指令
    "redis-cli -a redis4live2026 rpush 'list:keyword:room:1' '{\"room_id\":1,\"command_type\":\"keyword\",\"params\":{\"keyword\":\"玫瑰\"}}' 2>&1 | tail -1",
    # 公网拉取指令
    "curl -s -m 15 -H 'X-Api-Key: live-ai-api-key-2026' 'http://38.181.44.164/api/v1/liveportrait/instructions?room_id=1'",
    "echo ''",
]


def main():
    cli = paramiko.SSHClient()
    cli.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    cli.connect(HOST, username=USER, password=PWD, timeout=20)
    for c in CMDS:
        print("$", c[:120])
        stdin, stdout, stderr = cli.exec_command(c, timeout=60)
        out = stdout.read().decode("utf-8", "replace")
        err = stderr.read().decode("utf-8", "replace")
        if out:
            print(out.rstrip())
        if err and "Warning" not in err:
            print("[stderr]", err.rstrip())
        print()
    cli.close()
    return 0


if __name__ == "__main__":
    sys.exit(main())
