import sys
import paramiko

HOST = "38.181.44.164"
USER = "root"
PWD = "Mr3$Ye7]Dx7|"
BASE = "/www/wwwroot/douyin"

# 本地根目录（Windows） -> 服务器路径
LOCAL_ROOT = r"d:\phpstudy_pro\WWW\douyin"
FILES = [
    (r"php\app\ai\service\LivePortraitService.php", "php/app/ai/service/LivePortraitService.php"),
    (r"php\app\api\controller\LivePortrait.php", "php/app/api/controller/LivePortrait.php"),
    (r"php\app\api\controller\MachineAsset.php", "php/app/api/controller/MachineAsset.php"),
    (r"php\app\api\route\ai.php", "php/app/api/route/ai.php"),
    (r"php\sql\upgrade_liveportrait.sql", "php/sql/upgrade_liveportrait.sql"),
]

DB = "zhibo"
DB_USER = "zhibo"
DB_PWD = "12345678"

MIGRATE = [
    ("lp_media_asset", "asset_role",
     "ALTER TABLE `lp_media_asset` ADD COLUMN `asset_role` VARCHAR(32) NOT NULL DEFAULT '' COMMENT '驱动角色:空=成品视频/portrait立绘/motion动作模板' AFTER `asset_type`, ADD INDEX `idx_role_persona` (`asset_role`, `persona`);"),
    ("lp_room_binding", "portrait_asset_id",
     "ALTER TABLE `lp_room_binding` ADD COLUMN `portrait_asset_id` BIGINT UNSIGNED NULL COMMENT '绑定立绘素材ID' AFTER `persona`;"),
]


def sh(cli, cmd):
    stdin, stdout, stderr = cli.exec_command(cmd, timeout=120)
    out = stdout.read().decode("utf-8", "replace")
    err = stderr.read().decode("utf-8", "replace")
    return out, err


def main():
    cli = paramiko.SSHClient()
    cli.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    cli.connect(HOST, username=USER, password=PWD, timeout=20)
    sftp = cli.open_sftp()

    # 1. 上传文件
    import os
    for local_rel, remote_rel in FILES:
        local = os.path.join(LOCAL_ROOT, local_rel)
        remote = f"{BASE}/{remote_rel}"
        sftp.put(local, remote)
        print(f"[upload] {remote_rel}")

    # 2. 数据库迁移（幂等）
    for table, col, ddl in MIGRATE:
        out, err = sh(cli, f"mysql -u{DB_USER} -p{DB_PWD} -N -e \"SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='{DB}' AND TABLE_NAME='{table}' AND COLUMN_NAME='{col}';\"")
        count = out.strip()
        if count == "0":
            o2, e2 = sh(cli, f"mysql -u{DB_USER} -p{DB_PWD} -D {DB} -e \"{ddl}\"")
            print(f"[migrate] {table}.{col} -> {o2 or e2 or 'OK'}")
        elif count in ("1",):
            print(f"[migrate] {table}.{col} already exists, skip")
        else:
            print(f"[migrate] {table}.{col} check failed: out={out!r} err={err!r}")

    # 3. 更新 .env（加 RTMP_PUSH_URL / API_KEY）
    env_path = f"{BASE}/php/.env"
    with sftp.open(env_path, "r") as f:
        env = f.read().decode("utf-8")

    lines = env.splitlines()
    additions = []
    if "RTMP_PUSH_URL" not in env:
        additions.append("RTMP_PUSH_URL = rtmp://38.181.44.164:1935/live/")
    if "API_KEY" not in env:
        additions.append("API_KEY = live-ai-api-key-2026")

    if additions:
        # 在 [AI] 行之后插入
        out_lines = []
        inserted = False
        for ln in lines:
            out_lines.append(ln)
            if ln.strip() == "[AI]" and not inserted:
                out_lines.extend(additions)
                inserted = True
        if not inserted:
            out_lines.append("")
            out_lines.append("[AI]")
            out_lines.extend(additions)
        new_env = "\n".join(out_lines) + "\n"
        with sftp.open(env_path, "w") as f:
            f.write(new_env.encode("utf-8"))
        print(f"[env] inserted: {additions}")
    else:
        print("[env] already configured")

    # 4. 清理 ThinkPHP 缓存
    out, err = sh(cli, "rm -rf /www/wwwroot/douyin/php/runtime/cache/* /www/wwwroot/douyin/php/runtime/route.php 2>/dev/null; echo cleaned")
    print(f"[cache] {out.strip()}")

    sftp.close()

    # 5. 验证
    print("\n=== verify ===")
    out, err = sh(cli, "curl -s -m 15 -H 'X-Api-Key: live-ai-api-key-2026' 'http://127.0.0.1/api/v1/liveportrait/config?room_id=1'")
    print("[verify local]", out[:800] or err)

    cli.close()
    print("\nDONE")
    return 0


if __name__ == "__main__":
    sys.exit(main())
