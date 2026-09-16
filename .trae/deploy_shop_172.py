"""把「Candy Shop 商店」功能发布到 172.81.98.55

用法：python .trae/deploy_shop_172.py [all|php|static|menu|sql|admin|verify]

说明：
  - static：上传 shop.html / js/shop.js / js/site-shell.js / shop_files 里被引用的资源
  - menu  ：在【服务器上】对已有 HTML 做菜单注入（下载→就地注入→回传），
            避免用本地文件整站覆盖，防止冲掉线上其它改动
  - sql   ：执行 php/sql/upgrade_shop.sql（幂等）
  - admin ：上传后台 Vue 页面并在服务器上 npm run build 后发布到 admin/dist
"""
import datetime
import os
import re
import sys
import time

import paramiko

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from inject_shop_menu import inject_text  # noqa: E402

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
LOG = os.path.join(LOCAL, ".trae", "_deploy_shop.log")

PHP_FILES = [
    "php/app/api/controller/Shop.php",
    "php/app/api/route/shop.php",
    "php/app/admin/controller/live/ShopItem.php",
    "php/app/admin/model/live/ShopItem.php",
    "php/sql/upgrade_shop.sql",
]

ADMIN_FILES = [
    "php/web/src/views/backend/live/shopItem/index.vue",
    "php/web/src/views/backend/live/shopItem/popupForm.vue",
]

STATIC_FILES = [
    "ai-girl-malaysia.com/shop.html",
    "ai-girl-malaysia.com/js/shop.js",
    "ai-girl-malaysia.com/js/site-shell.js",
]

# shop.html 实际引用到的 shop_files 资源（其余是存档时的噪声文件，不上传）
SHOP_ASSETS = [
    "css2",
    "application-c9a80e6102195b41b493002bf9a0ab06ceb43fb6bcbf45cf6c1a4fd0f3b29ad1.css",
    "left-arrow-198ce01386bf370e33697c53d1cf90f5e8107c896bd0a849f0d1f67acf905c85.svg",
    "wordmark-e9f0737570efa3805285fda657afd2f2d755a8660e666129fc3270951727a508.svg",
    "info-circle-gray-fc2e620668668a969ee3f5840679d472b964179638603ff3d2c9c36cdafc5655.svg",
    "comment-d79fe3cce873167b5e3c654ad2ed3dfcb8c1de42d98883bcce8e028b72a73de9.svg",
    "close-ec898236eb501d13f4d1343d4a90ed1c0afec71efde2ed1e606916d52f2c5331.svg",
    "token-f75f9cb0c7c7d6e061d16253167966aeb3fa8bc051f416cc3f6cba4c29aac39d.svg",
]

RELOAD_STEP = r"""
set -e
mkdir -p %s/runtime
chown -R www-data:www-data %s/public %s/runtime
systemctl reload php8.3-fpm
echo "php-fpm reloaded"
""" % (PHP_DIR, PHP_DIR, PHP_DIR)

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
mysql -h"$DBH" -u"$DBU" "$DBN" < sql/upgrade_shop.sql
echo "--- 迁移后校验 ---"
mysql -h"$DBH" -u"$DBU" "$DBN" -e "
  SELECT COUNT(*) AS shop_items FROM lp_shop_item;
  SELECT id, pid, title, name, component, status FROM ba_admin_rule WHERE name LIKE 'live/shopItem%%';"
"""

ADMIN_BUILD_STEP = r"""
set -e
cd %s
echo "--- npm run build ---"
npm run build 2>&1 | tail -20
echo "--- publish dist -> %s/dist ---"
rsync -a --delete dist/ %s/dist/
ls -la %s/dist | head -6
""" % (WEB_DIR, ADMIN_DIR, ADMIN_DIR, ADMIN_DIR)

VERIFY_STEP = DB_ENV + (r"""
echo "--- PHP 语法 ---"
php -l %s/app/api/controller/Shop.php
php -l %s/app/api/route/shop.php
php -l %s/app/admin/controller/live/ShopItem.php
echo "--- 静态文件 ---"
ls -la %s/shop.html %s/js/shop.js
grep -c 'title="Shop"' %s/Home.html
grep -o 'shop.js' %s/shop.html | head -1
echo "--- 商品接口 ---"
curl -s -m 20 'http://127.0.0.1:8082/api/live/shopItems' | head -c 700
echo
echo "--- 商品详情接口 ---"
curl -s -m 20 'http://127.0.0.1:8082/api/live/shopItem?id=1' | head -c 500
echo
echo "--- 两条修正后的封面 ---"
mysql -h"$DBH" -u"$DBU" "$DBN" -e "SELECT id, title, cover_url FROM lp_shop_item WHERE title IN ('Aviator Officer Uniform','Burgundy Corset Ensemble');"
echo "--- shop 页 CSS 连续 5 次请求的状态码/大小 ---"
for i in 1 2 3 4 5; do
  curl -s -m 30 -o /dev/null -w "try$i: status=%%{http_code} size=%%{size_download} time=%%{time_total}\n" \
    'http://127.0.0.1:8082/shop_files/application-c9a80e6102195b41b493002bf9a0ab06ceb43fb6bcbf45cf6c1a4fd0f3b29ad1.css'
done
echo "--- cdn.candy.ai 两条封面能否取到 ---"
for u in \
  'https://cdn.candy.ai/candy_shop/items/82dae3c6-93ed-4d26-8499-ef5f53c91580.webp' \
  'https://cdn.candy.ai/candy_shop/items/40e09b1b-86d0-4693-89b2-6373743ddd7e.webp'; do
  curl -s -m 25 -o /dev/null -w "$u -> status=%%{http_code} type=%%{content_type} size=%%{size_download}\n" "$u"
done
""" % (PHP_DIR, PHP_DIR, PHP_DIR, SITE, SITE, SITE, SITE))


def log_write(buf, text):
    buf.write(text.rstrip() + "\n")
    buf.flush()


def mkdir_remote(sftp, path):
    parts = path.strip("/").split("/")
    cur = ""
    for p in parts:
        cur += "/" + p
        try:
            sftp.stat(cur)
        except IOError:
            sftp.mkdir(cur)


def upload_bytes(sftp, buf, remote, data, backup_suffix):
    remote_dir = os.path.dirname(remote)
    try:
        sftp.stat(remote_dir)
    except IOError:
        mkdir_remote(sftp, remote_dir)
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
    local = os.path.join(LOCAL, rel.replace("/", os.sep))
    with open(local, "rb") as f:
        data = f.read()
    upload_bytes(sftp, buf, remote, data, backup_suffix)


def remote_menu_inject(sftp, buf, cli):
    """在服务器上就地注入 Shop 菜单项（不覆盖线上其它改动）"""
    log_write(buf, "\n===== 线上菜单注入 =====")
    names = sorted(sftp.listdir(SITE))
    done = skip = nomatch = 0
    for name in names:
        if not name.lower().endswith(".html"):
            continue
        remote = SITE + "/" + name
        try:
            with sftp.open(remote, "rb") as f:
                raw = f.read()
        except IOError:
            continue
        text = raw.decode("utf-8", "surrogateescape")
        if "face_swap.html" not in text:
            continue
        new_text, changed = inject_text(text)
        if not changed:
            skip += 1
            continue
        if new_text == text:
            nomatch += 1
            continue
        upload_bytes(sftp, buf, remote, new_text.encode("utf-8", "surrogateescape"),
                     ".bak_shopmenu_" + STAMP)
        done += 1
    log_write(buf, "菜单注入：注入 %d 个，已存在 %d 个" % (done, skip))


def run(cli, buf, title, cmd, timeout=1800):
    log_write(buf, "\n===== %s =====" % title)
    _, out, err = cli.exec_command(cmd, timeout=timeout)
    o = out.read().decode("utf-8", "replace").strip()
    e = err.read().decode("utf-8", "replace").strip()
    if o:
        log_write(buf, o[:8000])
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
            cli.connect(
                HOST, username=USER, password=PWD, timeout=60,
                banner_timeout=120, auth_timeout=120,
                allow_agent=False, look_for_keys=False,
            )
            return cli
        except Exception as exc:
            last = exc
            print("connect attempt %d failed: %s" % (attempt, exc))
            time.sleep(5)
    raise last


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


def do_deploy(buf, action):
    cli = connect()
    sftp = cli.open_sftp()

    if action in ("all", "static"):
        for rel in STATIC_FILES:
            upload_file(sftp, buf, rel, REMOTE + "/" + rel, ".bak_shop_" + STAMP)
        for name in SHOP_ASSETS:
            upload_file(sftp, buf, "ai-girl-malaysia.com/shop_files/" + name,
                        SITE + "/shop_files/" + name, ".bak_shop_" + STAMP)

    if action in ("all", "php"):
        for rel in PHP_FILES:
            upload_file(sftp, buf, rel, PHP_DIR + "/" + rel.split("/", 1)[1], ".bak_shop_" + STAMP)

    if action in ("all", "admin"):
        for rel in ADMIN_FILES:
            upload_file(sftp, buf, rel, WEB_DIR + "/" + rel.split("/", 2)[2], ".bak_shop_" + STAMP)

    if action in ("all", "menu"):
        remote_menu_inject(sftp, buf, cli)

    if action in ("all", "sql"):
        run(cli, buf, "数据库迁移", SQL_STEP, timeout=300)

    if action in ("all", "php"):
        run(cli, buf, "重载 php-fpm", RELOAD_STEP, timeout=120)

    if action in ("all", "admin"):
        run(cli, buf, "后台前端构建发布", ADMIN_BUILD_STEP, timeout=2400)

    if action in ("all", "verify"):
        run(cli, buf, "线上校验", VERIFY_STEP, timeout=180)

    sftp.close()
    cli.close()


if __name__ == "__main__":
    main()
