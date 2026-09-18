"""AI 女友端充值（star-pay）发布到 172.81.98.55

用法：python .trae/deploy_starpay_172.py [probe|all|php|static|sql|env|verify]

说明：
  - php    ：上传 star-pay 相关 PHP（配置/服务/控制器/路由/后台）并重载 php-fpm
  - static ：上传 purchasePoints.html / js/purchasePoints.js 及其引用的 tokens 图标
  - sql    ：执行 php/sql/upgrade_recharge_starpay.sql（幂等）
  - env    ：在服务器 php/.env 追加 [STARPAY] 段（已存在则跳过）
  - verify ：线上校验（语法 / 文件 / 接口 / 表结构）
"""
import datetime
import os
import sys
import time

import paramiko

try:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
except Exception:
    pass

HOST = "172.81.98.55"
USER = "root"
PWD_CANDIDATES = ["172.81.98.55", "0wJfy`50tJQzG/0X"]

LOCAL = r"d:\phpstudy_pro\WWW\douyin"
REMOTE = "/www/wwwroot/douyin"
PHP_DIR = REMOTE + "/php"
SITE = REMOTE + "/ai-girl-malaysia.com"
API_PORT = "8082"
STAMP = datetime.datetime.now().strftime("%Y%m%d_%H%M%S")
LOG = os.path.join(LOCAL, ".trae", "_deploy_starpay.log")

PHP_FILES = [
    "php/config/starpay.php",
    "php/app/live/service/StarPayService.php",
    "php/app/live/service/RechargeService.php",
    "php/app/api/controller/Recharge.php",
    "php/app/api/route/recharge.php",
    "php/app/admin/controller/live/RechargeChannel.php",
    "php/sql/upgrade_recharge_starpay.sql",
]

STATIC_FILES = [
    "ai-girl-malaysia.com/purchasePoints.html",
    "ai-girl-malaysia.com/js/purchasePoints.js",
]

TOKENS_ASSETS = [
    # 6 档金币图标 + 自定义图标
    "coin_1-20c5ab8c96a9268b33d18c0b6a2f00cc9aae9cf93484db35890df062588ce9ef.webp",
    "coin_2-8de047415dbc9fdda2a5b6419e19c5c82e0a5d2e4e277bd38cc5691b54e0cb5f.webp",
    "coin_3-93c4a360cb91a06412464426cf9e389daaf3a2c18943fe9842da534bd4075524.webp",
    "coin_4-7384f3ae326ebaab75eef8c23f7859db9a992732a230a086058930aa43d90ac9.webp",
    "coin_5-9a5e24f5a0754719837b8013162ac63db92c9791c24ca3f793bbe1e3b0585f89.webp",
    "coin_6-e52dead2f4c02b98fcea5e2c710efbdd94d0bd0339a5c824bcd230dea25545cf.webp",
    "coin_custom-81a484ff06abb2ce853b520dc59090edd3ec015802ca3240d30405c381994958.webp",
    # Token Benefits 四个图标
    "spicy-d6c3b758dd5cf56b5a992f80228babedf9993ef2ac82c395bf7d5376ca41be57.webp",
    "live_action-ebfc5c78549b23117b89027c26cc2cc4262bf508cfeb8611e4b86aa7de1a1ca8.webp",
    "lock-3ebbdd567fcfe81907f46667424186157a573201f65957d5420c7d7190f8255a.webp",
    "wand-1a28ddd7ea7cdbb195e5a188783005645709b6f4aaff89fee5c7d2291e1d5824.webp",
    # 弹窗关闭按钮
    "cross-ec898236eb501d13f4d1343d4a90ed1c0afec71efde2ed1e606916d52f2c5331.svg",
]

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
mysql -h"$DBH" -u"$DBU" "$DBN" < sql/upgrade_recharge_starpay.sql
echo "--- 迁移后校验 ---"
mysql -h"$DBH" -u"$DBU" "$DBN" -e "
  SELECT COUNT(*) AS packages FROM lp_recharge_package;
  SHOW COLUMNS FROM lp_recharge_order LIKE 'pay_url';
  SHOW COLUMNS FROM lp_recharge_order LIKE 'system_ref';
  SHOW COLUMNS FROM lp_recharge_order LIKE 'package_id';"
"""

RELOAD_STEP = r"""
set -e
mkdir -p %s/runtime
chown -R www-data:www-data %s/runtime
if systemctl list-units --type=service | grep -q php8.3-fpm; then systemctl reload php8.3-fpm; echo "php8.3-fpm reloaded";
elif [ -f /etc/init.d/php-fpm-83 ]; then /etc/init.d/php-fpm-83 reload; echo "php-fpm-83 reloaded";
else service php-fpm reload; echo "php-fpm reloaded"; fi
""" % (PHP_DIR, PHP_DIR)

VERIFY_STEP = DB_ENV + (r"""
echo "--- PHP 语法 ---"
for f in config/starpay.php app/live/service/StarPayService.php app/live/service/RechargeService.php \
         app/api/controller/Recharge.php app/api/route/recharge.php \
         app/admin/controller/live/RechargeChannel.php; do
  php -l "%s/$f"
done
echo "--- 静态文件 ---"
ls -la %s/purchasePoints.html %s/js/purchasePoints.js
ls -la %s/tokens_files/coin_1-*.webp %s/tokens_files/coin_custom-*.webp
echo "--- 套餐接口 ---"
curl -s -m 20 'http://127.0.0.1:%s/api/v1/recharge/packages' | head -c 900
echo
echo "--- 下单接口未登录应鉴权失败 ---"
curl -s -m 20 -X POST 'http://127.0.0.1:%s/api/v1/recharge/createOrder' -d 'package_id=1' | head -c 300
echo
echo "--- 表结构 ---"
mysql -h"$DBH" -u"$DBU" "$DBN" -e "SELECT id,tokens,price,bonus_percent,sort,status FROM lp_recharge_package ORDER BY sort;"
""" % (PHP_DIR, SITE, SITE, SITE, SITE, API_PORT, API_PORT))

PROBE_STEP = r"""
echo "--- 自选点数报价抽样（对照 candy.ai 价格表核对） ---"
for t in 100 150 400 450 500 550 700 850 1000 1150 2000 2400 3000 3750 5000; do
  printf "%%s -> " "$t"
  curl -s -m 10 "http://127.0.0.1:8082/api/v1/recharge/quote?tokens=$t"
  echo
done
echo "--- 页面区块与脚本检查 ---"
echo -n "tk-custom-bubble="; grep -c "tk-custom-bubble" %s/purchasePoints.html
echo -n "tk-upsell="; grep -c "tk-upsell" %s/purchasePoints.html
echo -n "js-quoteTokens="; grep -c "quoteTokens" %s/js/purchasePoints.js
echo "--- 页面与脚本 HTTP 状态 ---"
curl -s -m 10 -o /dev/null -w "%%{http_code} %%{size_download} purchasePoints.html\n" "http://127.0.0.1:8082/purchasePoints.html"
curl -s -m 10 -o /dev/null -w "%%{http_code} %%{size_download} purchasePoints.js\n" "http://127.0.0.1:8082/js/purchasePoints.js"
echo "--- 套餐/支付方式接口 ---"
curl -s -m 20 'http://127.0.0.1:8082/api/v1/recharge/packages' | head -c 200
""" % (SITE, SITE, SITE)


def log_write(buf, text):
    buf.write(text.rstrip() + "\n")
    buf.flush()


def run(cli, buf, title, cmd, timeout=1800):
    log_write(buf, "\n===== %s =====" % title)
    _, out, err = cli.exec_command(cmd, timeout=timeout)
    o = out.read().decode("utf-8", "replace").strip()
    e = err.read().decode("utf-8", "replace").strip()
    if o:
        log_write(buf, o[:9000])
    if e:
        log_write(buf, "[stderr] " + e[:4000])
    rc = out.channel.recv_exit_status()
    log_write(buf, "[rc=%d]" % rc)
    return rc, o


def connect():
    cli = paramiko.SSHClient()
    cli.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    last = None
    for pwd in PWD_CANDIDATES:
        for attempt in range(1, 4):
            try:
                cli.connect(
                    HOST, username=USER, password=pwd, timeout=60,
                    banner_timeout=120, auth_timeout=120,
                    allow_agent=False, look_for_keys=False,
                )
                print("connected with password candidate #%d" % (PWD_CANDIDATES.index(pwd) + 1))
                return cli
            except Exception as exc:
                last = exc
                print("attempt %d failed: %s" % (attempt, exc))
                time.sleep(4)
    raise last


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


def ensure_env(sftp, buf, cli):
    """在服务器 php/.env 追加 [STARPAY] 段（已存在则跳过）"""
    log_write(buf, "\n===== 服务器 .env [STARPAY] =====")
    remote = PHP_DIR + "/.env"
    try:
        with sftp.open(remote, "rb") as f:
            raw = f.read()
    except IOError as exc:
        log_write(buf, "read .env failed: %s" % exc)
        return
    text = raw.decode("utf-8", "replace")
    if "[STARPAY]" in text:
        log_write(buf, "[STARPAY] 已存在，跳过")
        return
    block = (
        "\n[STARPAY]\n"
        "GATEWAY = https://api.star-pay.vip\n"
        "MERCHANT_NO =\n"
        "KEY =\n"
        "PRODUCT = TRC20Buy\n"
        "PRODUCTS = TRC20Buy,ERC20Buy,USDCERC20Buy,TRC20H5\n"
        "NOTIFY_URL = https://sugus.it.com/api/v1/recharge/notify\n"
        "RETURN_URL = https://sugus.it.com/ai/purchasePoints.html\n"
    )
    upload_bytes(sftp, buf, remote, (text + block).encode("utf-8"), ".bak_starpayenv_" + STAMP)


def main():
    action = sys.argv[1] if len(sys.argv) > 1 else "all"
    buf = open(LOG, "w", encoding="utf-8", buffering=1)
    try:
        cli = connect()
        sftp = cli.open_sftp()
        if action == "probe":
            run(cli, buf, "服务器环境探测", PROBE_STEP, timeout=120)
        else:
            if action in ("all", "php"):
                for rel in PHP_FILES:
                    upload_file(sftp, buf, rel, PHP_DIR + "/" + rel.split("/", 1)[1], ".bak_starpay_" + STAMP)
            if action in ("all", "static"):
                for rel in STATIC_FILES:
                    upload_file(sftp, buf, rel, REMOTE + "/" + rel, ".bak_starpay_" + STAMP)
                for name in TOKENS_ASSETS:
                    upload_file(sftp, buf, "ai-girl-malaysia.com/tokens_files/" + name,
                                SITE + "/tokens_files/" + name, "")
            if action in ("all", "env"):
                ensure_env(sftp, buf, cli)
            if action in ("all", "sql"):
                run(cli, buf, "数据库迁移", SQL_STEP, timeout=300)
            if action in ("all", "php"):
                run(cli, buf, "重载 php-fpm", RELOAD_STEP, timeout=120)
                run(cli, buf, "目录权限", "chown -R www-data:www-data %s/config %s/app" % (PHP_DIR, PHP_DIR), timeout=180)
            if action in ("all", "verify"):
                run(cli, buf, "线上校验", VERIFY_STEP, timeout=240)
        sftp.close()
        cli.close()
    except Exception:
        import traceback
        log_write(buf, "!!!! FAILED !!!!\n" + traceback.format_exc())
        buf.close()
        raise
    buf.close()
    print("DONE ->", LOG)


if __name__ == "__main__":
    main()
