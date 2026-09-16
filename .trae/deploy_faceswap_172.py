"""把「上传图片换脸（固定视频）」功能部署到 172.81.98.55

用法：python .trae/deploy_faceswap_172.py [all|php|static|admin|sql|verify]

说明：172 官方部署是从 gitee 拉取构建（/root/deploy/deploy_web.sh），
本脚本用于把本次未提交的改动直接同步到线上（SFTP + 远端构建）：
  1) php   → /www/wwwroot/douyin/php
  2) static→ /www/wwwroot/douyin/ai-girl-malaysia.com
  3) sql   → 数据库 zhibo 执行 php/sql/upgrade_face_swap.sql（幂等检测）
  4) admin → 上传后台 Vue 页面并在服务器上 npm run build 后发布到 admin/dist
"""
import datetime
import os
import sys

import paramiko

try:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
except Exception:
    pass

HOST = "172.81.98.55"
USER = "root"
PWD = "0wJfy`50tJQzG/0X"

LOCAL = r"d:\phpstudy_pro\WWW\douyin"
REMOTE = "/www/wwwroot/douyin"
PHP_DIR = REMOTE + "/php"
WEB_DIR = PHP_DIR + "/web"
ADMIN_DIR = REMOTE + "/admin"
STAMP = datetime.datetime.now().strftime("%Y%m%d_%H%M%S")
LOG = os.path.join(LOCAL, ".trae", "_deploy_faceswap.log")

# ---------------- 待上传文件 ----------------

PHP_FILES = [
    "php/app/api/controller/FaceSwap.php",
    "php/app/api/controller/AiTask.php",
    "php/app/ai/service/AiTaskService.php",
    "php/app/api/route/ai.php",
    "php/config/ai.php",
    "php/route/live.php",
    "php/app/admin/controller/live/FaceSwapTemplate.php",
    "php/app/admin/model/live/FaceSwapTemplate.php",
    "php/sql/upgrade_face_swap.sql",
]

ADMIN_FILES = [
    "php/web/src/views/backend/live/faceSwapTemplate/index.vue",
    "php/web/src/views/backend/live/faceSwapTemplate/popupForm.vue",
]

STATIC_EXTRA = [
    "ai-girl-malaysia.com/face_swap.html",
    "ai-girl-malaysia.com/js/face_swap.js",
    "ai-girl-malaysia.com/js/site-shell.js",
]


def static_pages():
    """所有已注入 Face Swap 菜单项的页面（face_swap.html 由 STATIC_EXTRA 单独提供）"""
    root = os.path.join(LOCAL, "ai-girl-malaysia.com")
    pages = []
    for name in sorted(os.listdir(root)):
        if not name.lower().endswith(".html") or name == "face_swap.html":
            continue
        path = os.path.join(root, name)
        if not os.path.isfile(path):
            continue
        with open(path, "r", encoding="utf-8", errors="surrogateescape") as f:
            if "face_swap.html" in f.read():
                pages.append("ai-girl-malaysia.com/" + name)
    return pages


# ---------------- 远端命令 ----------------

LOAD_ENV = r"""
set -e
cd %s
eval "$(php -r '$c = parse_ini_file(".env", true)["DATABASE"];
  echo "DBH=".escapeshellarg($c["HOSTNAME"])."\n";
  echo "DBU=".escapeshellarg($c["USERNAME"])."\n";
  echo "DBP=".escapeshellarg($c["PASSWORD"])."\n";
  echo "DBN=".escapeshellarg($c["DATABASE"])."\n";
  echo "DBPORT=".escapeshellarg($c["HOSTPORT"] ?? "3306")."\n";')"
export MYSQL_PWD="$DBP"
echo "库: $DBN @ $DBH:$DBPORT 用户: $DBU"
""" % PHP_DIR

SQL_STEP = LOAD_ENV + r"""
echo "--- 迁移前检测 ---"
mysql -h"$DBH" -P"$DBPORT" -u"$DBU" -N -e "
  SELECT CONCAT('table:', COUNT(*)) FROM information_schema.tables
    WHERE table_schema='$DBN' AND table_name='lp_face_swap_template';
  SELECT CONCAT('col:', COUNT(*)) FROM information_schema.columns
    WHERE table_schema='$DBN' AND table_name='lp_ai_task' AND column_name IN ('user_id','face_image_url','template_video_url');
"
if mysql -h"$DBH" -P"$DBPORT" -u"$DBU" -N -e "
     SELECT COUNT(*) FROM information_schema.tables
     WHERE table_schema='$DBN' AND table_name='lp_face_swap_template';" | grep -q '^1$' \
   && [ "$(mysql -h"$DBH" -P"$DBPORT" -u"$DBU" -N -e "
     SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema='$DBN' AND table_name='lp_ai_task'
       AND column_name IN ('user_id','face_image_url','template_video_url');")" = "3" ]; then
  echo "迁移已应用，跳过"
else
  echo "--- 执行 upgrade_face_swap.sql ---"
  mysql -h"$DBH" -P"$DBPORT" -u"$DBU" "$DBN" < sql/upgrade_face_swap.sql
fi
echo "--- 迁移后校验 ---"
mysql -h"$DBH" -P"$DBPORT" -u"$DBU" "$DBN" -N -e "
  SHOW COLUMNS FROM lp_ai_task WHERE Field IN ('user_id','face_image_url','template_video_url');
  SELECT id,pid,type,title,name,status FROM ba_admin_rule WHERE name LIKE 'live/faceSwapTemplate%';
  SELECT CONCAT('templates=', COUNT(*)) FROM lp_face_swap_template;
"
"""

RELOAD_STEP = r"""
set -e
mkdir -p %s/runtime
chown -R www-data:www-data %s/public %s/runtime
rm -f %s/public/debug.log
systemctl reload php8.3-fpm
echo "php-fpm reloaded"
""" % (PHP_DIR, PHP_DIR, PHP_DIR, PHP_DIR)

DISCOVER_STEP = r"""
echo "--- nginx server_name / root ---"
nginx -T 2>/dev/null | grep -E 'server_name|root ' | grep -v '^\s*#' | head -40
echo "--- listening ports ---"
ss -lntp | grep -E ':(80|443|8082|8888)\s' | head -10
"""

ADMIN_BUILD_STEP = r"""
set -e
cd %s
echo "--- npm run build ---"
npm run build 2>&1 | tail -25
echo "--- publish dist -> %s/dist ---"
rsync -a --delete dist/ %s/dist/
ls -la %s/dist | head -8
""" % (WEB_DIR, ADMIN_DIR, ADMIN_DIR, ADMIN_DIR)


def log_write(buf, text):
    buf.write(text.rstrip() + "\n")


def upload(sftp, buf, pairs):
    """pairs: [(本地相对路径, 远端绝对路径), ...]"""
    for rel, remote in pairs:
        local = os.path.join(LOCAL, rel.replace("/", os.sep))
        remote_dir = os.path.dirname(remote)
        try:
            sftp.stat(remote_dir)
        except IOError:
            mkdir_remote(sftp, remote_dir)

        try:
            with sftp.open(remote, "rb") as f:
                old = f.read()
            with sftp.open(remote + ".bak_faceswap_" + STAMP, "wb") as f:
                f.write(old)
        except IOError:
            pass  # 新文件，无需备份

        with open(local, "rb") as f:
            data = f.read()
        with sftp.open(remote, "wb") as f:
            f.write(data)
        log_write(buf, f"uploaded {rel} -> {remote} ({len(data)} bytes)")


def mkdir_remote(sftp, path):
    parts = path.strip("/").split("/")
    cur = ""
    for p in parts:
        cur += "/" + p
        try:
            sftp.stat(cur)
        except IOError:
            sftp.mkdir(cur)


def run(cli, buf, title, cmd, timeout=600):
    log_write(buf, f"\n===== {title} =====")
    _, out, err = cli.exec_command(cmd, timeout=timeout)
    o = out.read().decode("utf-8", "replace").strip()
    e = err.read().decode("utf-8", "replace").strip()
    if o:
        log_write(buf, o[:6000])
    if e:
        log_write(buf, "[stderr] " + e[:3000])
    rc = out.channel.recv_exit_status()
    log_write(buf, f"[rc={rc}]")
    return rc, o


def main():
    action = sys.argv[1] if len(sys.argv) > 1 else "all"
    buf = open(LOG, "w", encoding="utf-8")

    cli = paramiko.SSHClient()
    cli.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    cli.connect(HOST, username=USER, password=PWD, timeout=25)
    sftp = cli.open_sftp()

    run(cli, buf, "环境探测", DISCOVER_STEP, timeout=120)

    if action in ("all", "php", "sql"):
        upload(sftp, buf, [(rel, PHP_DIR + "/" + rel.split("/", 1)[1]) for rel in PHP_FILES])

    if action in ("all", "static"):
        pages = static_pages()
        log_write(buf, f"\n静态站待更新页面 {len(pages)} 个")
        upload(sftp, buf, [(rel, REMOTE + "/" + rel) for rel in STATIC_EXTRA + pages])

    if action in ("all", "admin"):
        upload(sftp, buf, [(rel, WEB_DIR + "/" + rel.split("/", 2)[2]) for rel in ADMIN_FILES])

    if action in ("all", "php", "sql"):
        run(cli, buf, "数据库迁移", SQL_STEP, timeout=300)

    if action in ("all", "php"):
        run(cli, buf, "重载 php-fpm", RELOAD_STEP, timeout=120)

    if action in ("all", "admin"):
        run(cli, buf, "后台前端构建发布", ADMIN_BUILD_STEP, timeout=1800)

    sftp.close()
    cli.close()
    buf.close()
    print("DEPLOY DONE ->", LOG)


if __name__ == "__main__":
    main()
