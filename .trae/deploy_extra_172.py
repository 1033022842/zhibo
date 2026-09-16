"""把「Shorts / Posts / Private Content」三个模块发布到 172.81.98.55

用法：python .trae/deploy_extra_172.py [all|php|static|assets|menu|sql|admin|verify]
"""
import datetime
import os
import sys
import tarfile
import time

import paramiko

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from inject_extra_menus import inject_text  # noqa: E402

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
SITE = REMOTE + "/ai-girl-malaysia.com"
STAMP = datetime.datetime.now().strftime("%Y%m%d_%H%M%S")
LOG = os.path.join(LOCAL, ".trae", "_deploy_extra.log")
TARBALL = os.path.join(LOCAL, ".trae", "_extra_assets.tgz")

PHP_FILES = [
    "php/app/api/controller/Shorts.php",
    "php/app/api/controller/Posts.php",
    "php/app/api/controller/PrivateContent.php",
    "php/app/api/controller/Shop.php",
    "php/app/api/route/shorts.php",
    "php/app/api/route/posts.php",
    "php/app/api/route/private_content.php",
    "php/app/admin/controller/live/ShortItem.php",
    "php/app/admin/controller/live/PostItem.php",
    "php/app/admin/controller/live/PrivateItem.php",
    "php/app/admin/model/live/ShortItem.php",
    "php/app/admin/model/live/PostItem.php",
    "php/app/admin/model/live/PrivateItem.php",
    "php/sql/upgrade_shorts.sql",
    "php/sql/upgrade_posts.sql",
    "php/sql/upgrade_private_content.sql",
    "php/sql/fix_menu_dup_and_seed_test.sql",
]

ADMIN_FILES = [
    "php/web/src/views/backend/live/shortItem/index.vue",
    "php/web/src/views/backend/live/shortItem/popupForm.vue",
    "php/web/src/views/backend/live/postItem/index.vue",
    "php/web/src/views/backend/live/postItem/popupForm.vue",
    "php/web/src/views/backend/live/privateItem/index.vue",
    "php/web/src/views/backend/live/privateItem/popupForm.vue",
]

STATIC_FILES = [
    "ai-girl-malaysia.com/shorts.html",
    "ai-girl-malaysia.com/posts.html",
    "ai-girl-malaysia.com/private_content.html",
    "ai-girl-malaysia.com/js/shorts.js",
    "ai-girl-malaysia.com/js/posts.js",
    "ai-girl-malaysia.com/js/private_content.js",
    "ai-girl-malaysia.com/js/site-shell.js",
]

ASSET_DIRS = ["candy-shorts_files", "posts_files", "private-content_files"]

DB_ENV = r"""
cd %s
eval "$(php -r '$c = parse_ini_file(".env", true)["DATABASE"];
  echo "DBH=".escapeshellarg($c["HOSTNAME"])."\n";
  echo "DBU=".escapeshellarg($c["USERNAME"])."\n";
  echo "DBP=".escapeshellarg($c["PASSWORD"])."\n";
  echo "DBN=".escapeshellarg($c["DATABASE"])."\n";')"
export MYSQL_PWD="$DBP"
""" % PHP_DIR

SQL_STEP = DB_ENV + r"""set -e
echo "库: $DBN @ $DBH"
for f in fix_menu_dup_and_seed_test.sql upgrade_shorts.sql upgrade_posts.sql upgrade_private_content.sql; do
  echo "--- $f ---"
  mysql -h"$DBH" -u"$DBU" "$DBN" < "sql/$f" || echo "[FAILED] $f"
done
echo "--- 迁移后校验 ---"
mysql -h"$DBH" -u"$DBU" "$DBN" -e "
  SELECT COUNT(*) AS shorts FROM lp_short_item;
  SELECT COUNT(*) AS posts  FROM lp_post_item;
  SELECT COUNT(*) AS private_items FROM lp_private_item;
  SELECT name, COUNT(*) AS c FROM ba_admin_rule GROUP BY name HAVING c > 1;
  SELECT id, pid, type, title, name, component, status FROM ba_admin_rule WHERE name LIKE 'live/shopItem%' OR name LIKE 'live/shortItem%' OR name LIKE 'live/postItem%' OR name LIKE 'live/privateItem%' ORDER BY name;"
"""

RELOAD_STEP = r"""
set -e
mkdir -p %s/runtime
chown -R www-data:www-data %s/public %s/runtime
systemctl reload php8.3-fpm
echo "php-fpm reloaded"
""" % (PHP_DIR, PHP_DIR, PHP_DIR)

ADMIN_BUILD_STEP = r"""
set -e
cd %s
npm run build 2>&1 | tail -15
rsync -a --delete dist/ %s/dist/
echo "admin dist published"
""" % (WEB_DIR, ADMIN_DIR)

VERIFY_STEP = DB_ENV + (r"""
echo "--- PHP 语法 ---"
for f in app/api/controller/Shorts.php app/api/controller/Posts.php app/api/controller/PrivateContent.php app/api/controller/Shop.php \
         app/api/route/shorts.php app/api/route/posts.php app/api/route/private_content.php \
         app/admin/controller/live/ShortItem.php app/admin/controller/live/PostItem.php app/admin/controller/live/PrivateItem.php; do
  php -l "$f"
done
echo "--- 三个接口 ---"
echo -n "shorts:  "; curl -s -m 20 'http://127.0.0.1:8082/api/live/shorts' | head -c 320; echo
echo -n "posts:   "; curl -s -m 20 'http://127.0.0.1:8082/api/live/posts' | head -c 320; echo
echo -n "private: "; curl -s -m 20 'http://127.0.0.1:8082/api/live/privateContents' | head -c 320; echo
echo -n "private(most_liked): "; curl -s -m 20 'http://127.0.0.1:8082/api/live/privateContents?tab=most_liked' | head -c 200; echo
echo "--- 静态文件 ---"
ls -la %s/shorts.html %s/posts.html %s/private_content.html %s/js/shorts.js %s/js/posts.js %s/js/private_content.js
echo "--- 素材目录 ---"
du -sh %s/candy-shorts_files %s/posts_files %s/private-content_files
echo "--- 侧边栏菜单项（应各 3 处） ---"
for p in Home.html shorts.html posts.html private_content.html; do
  echo -n "$p: shorts=$(grep -o 'href=\"./shorts.html\"' %s/$p | wc -l) posts=$(grep -o 'href=\"./posts.html\"' %s/$p | wc -l) private=$(grep -o 'href=\"./private_content.html\"' %s/$p | wc -l)"
  echo
done
""" % (SITE, SITE, SITE, SITE, SITE, SITE, SITE, SITE, SITE, SITE, SITE, SITE))


def log_write(buf, text):
    buf.write(text.rstrip() + "\n")
    buf.flush()


def mkdir_remote(sftp, path):
    cur = ""
    for part in path.strip("/").split("/"):
        cur += "/" + part
        try:
            sftp.stat(cur)
        except IOError:
            sftp.mkdir(cur)


def upload_bytes(sftp, buf, remote, data, backup_suffix=""):
    try:
        sftp.stat(os.path.dirname(remote))
    except IOError:
        mkdir_remote(sftp, os.path.dirname(remote))
    if backup_suffix:
        try:
            with sftp.open(remote, "rb") as f:
                old = f.read()
            with sftp.open(remote + backup_suffix, "wb") as f:
                f.write(old)
        except IOError:
            pass
    with sftp.open(remote, "wb") as f:
        f.write(data)
    log_write(buf, "uploaded %s (%d bytes)" % (remote, len(data)))


def upload_file(sftp, buf, rel, remote, backup_suffix=""):
    with open(os.path.join(LOCAL, rel.replace("/", os.sep)), "rb") as f:
        upload_bytes(sftp, buf, remote, f.read(), backup_suffix)


def make_tarball(buf):
    log_write(buf, "\n===== 打包素材 =====")
    with tarfile.open(TARBALL, "w:gz") as tar:
        for d in ASSET_DIRS:
            tar.add(os.path.join(LOCAL, "ai-girl-malaysia.com", d), arcname=d)
    size = os.path.getsize(TARBALL)
    log_write(buf, "tarball %s (%.1f MB)" % (TARBALL, size / 1024 / 1024))
    return size


def remote_menu_inject(sftp, buf):
    log_write(buf, "\n===== 线上菜单注入（Shorts / Posts / Private Content） =====")
    done = skip = 0
    for name in sorted(sftp.listdir(SITE)):
        if not name.lower().endswith(".html"):
            continue
        remote = SITE + "/" + name
        try:
            with sftp.open(remote, "rb") as f:
                raw = f.read()
        except IOError:
            continue
        text = raw.decode("utf-8", "surrogateescape")
        if 'href="./shop.html"' not in text:
            continue
        new_text, changed = inject_text(text)
        if not changed:
            skip += 1
            continue
        upload_bytes(sftp, buf, remote, new_text.encode("utf-8", "surrogateescape"),
                     ".bak_extra_" + STAMP)
        done += 1
    log_write(buf, "菜单注入：注入 %d 个页面，已存在 %d 个" % (done, skip))


def run(cli, buf, title, cmd, timeout=2400):
    log_write(buf, "\n===== %s =====" % title)
    _, out, err = cli.exec_command(cmd, timeout=timeout)
    o = out.read().decode("utf-8", "replace").strip()
    e = err.read().decode("utf-8", "replace").strip()
    if o:
        log_write(buf, o[:9000])
    if e:
        log_write(buf, "[stderr] " + e[:3000])
    rc = out.channel.recv_exit_status()
    log_write(buf, "[rc=%d]" % rc)
    return rc, o


def connect():
    cli = paramiko.SSHClient()
    cli.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    last = None
    for attempt in range(1, 6):
        try:
            cli.connect(HOST, username=USER, password=PWD, timeout=60,
                        banner_timeout=120, auth_timeout=120,
                        allow_agent=False, look_for_keys=False)
            return cli
        except Exception as exc:
            last = exc
            print("connect attempt %d failed: %s" % (attempt, exc))
            time.sleep(5)
    raise last


def do_deploy(buf, action):
    cli = connect()
    sftp = cli.open_sftp()

    if action in ("all", "static"):
        for rel in STATIC_FILES:
            upload_file(sftp, buf, rel, REMOTE + "/" + rel, ".bak_extra_" + STAMP)

    if action in ("all", "php"):
        for rel in PHP_FILES:
            upload_file(sftp, buf, rel, PHP_DIR + "/" + rel.split("/", 1)[1], ".bak_extra_" + STAMP)

    if action in ("all", "admin"):
        for rel in ADMIN_FILES:
            upload_file(sftp, buf, rel, WEB_DIR + "/" + rel.split("/", 2)[2], ".bak_extra_" + STAMP)

    if action in ("all", "assets"):
        make_tarball(buf)
        upload_file(sftp, buf, ".trae/_extra_assets.tgz", REMOTE + "/_extra_assets.tgz")
        run(cli, buf, "解包素材", "cd %s && tar -xzf ../_extra_assets.tgz && rm -f ../_extra_assets.tgz && ls -d candy-shorts_files posts_files private-content_files" % SITE,
            timeout=900)

    if action in ("all", "menu"):
        remote_menu_inject(sftp, buf)

    if action in ("all", "sql"):
        run(cli, buf, "数据库迁移", SQL_STEP, timeout=300)

    if action in ("all", "php"):
        run(cli, buf, "重载 php-fpm", RELOAD_STEP, timeout=120)

    if action in ("all", "admin"):
        run(cli, buf, "后台前端构建发布", ADMIN_BUILD_STEP, timeout=2400)

    if action in ("all", "verify"):
        run(cli, buf, "线上校验", VERIFY_STEP, timeout=300)

    sftp.close()
    cli.close()


def main():
    action = sys.argv[1] if len(sys.argv) > 1 else "all"
    buf = open(LOG, "w", encoding="utf-8", buffering=1)
    try:
        do_deploy(buf, action)
    except Exception:
        import traceback
        log_write(buf, "!!!! FAILED !!!!\n" + traceback.format_exc())
        buf.close()
        raise
    buf.close()
    print("DEPLOY DONE ->", LOG)


if __name__ == "__main__":
    main()
