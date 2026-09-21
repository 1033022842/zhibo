"""AI 女友端充值（star-pay）发布到 172.81.98.55

用法：python .trae/deploy_starpay_172.py [probe|all|php|static|sql|env|verify|gateway|e2e|vip|tier|min|url|deposit|products|cron|token|cleanvip|shell|file]

说明：
  - php    ：上传 star-pay 相关 PHP（配置/服务/控制器/路由/后台）并重载 php-fpm
  - static ：上传 purchasePoints.html / js/purchasePoints.js / Subscriptions.html / js/subscriptions.js 及其引用的 tokens 图标
  - shell  ：上传新版外壳（js/shell-new.js + assets/fy.css + js/main.js + 所有引用了 shell-new.js 的页面）
  - file   ：只上传命令行里点名的文件（后接相对仓库根的路径，可多个）
  - sql    ：执行 php/sql/upgrade_recharge_starpay.sql、upgrade_vip_starpay.sql（幂等）
  - env    ：在服务器 php/.env 追加 [STARPAY] 段（已存在则替换）
  - verify ：线上校验（语法 / 文件 / 接口 / 表结构 / 公网路径）
  - e2e    ：充值链路联调（下单 + 回调 + 幂等）
  - vip    ：订阅链路联调（下单 + 回调 + 幂等 + 续订）
  - tier   ：会员分层联调（免费额度 / Premium / Premium Plus 层级与折扣）
  - url    ：公网路径与 nginx 配置探测
  - deposit：地址类产品试单（收款地址与 payurl）
  - products：网关产品能力探测（探测 ETH/BTC/LTC 等产品码是否被网关接受，只查询不建单）
  - cron   ：查看/补齐链上扫描定时任务（recharge:scan、recharge:scan-eth）
  - token  ：生成测试用户登录 token（可带上 uid 参数）
  - cleanvip：清理订阅联调残留
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
    "php/app/live/service/VipService.php",
    "php/app/live/service/QuotaService.php",
    "php/app/live/service/WalletService.php",
    "php/app/api/controller/Recharge.php",
    "php/app/api/controller/Vip.php",
    "php/app/api/controller/Live.php",
    "php/app/api/controller/CustomVideo.php",
    "php/app/api/controller/PrivateContent.php",
    "php/app/api/controller/Shorts.php",
    "php/app/api/controller/Shop.php",
    "php/app/api/route/recharge.php",
    "php/app/command/EthRechargeScanCron.php",
    "php/config/console.php",
    "php/sql/upgrade_eth_recharge.sql",
    "php/app/api/route/vip.php",
    "php/app/admin/controller/live/RechargeChannel.php",
    "php/sql/upgrade_recharge_starpay.sql",
    "php/sql/upgrade_vip_starpay.sql",
    "php/sql/upgrade_vip_tier.sql",
    "php/sql/upgrade_vip_plans_v2.sql",
]

STATIC_FILES = [
    "ai-girl-malaysia.com/purchasePoints.html",
    "ai-girl-malaysia.com/Home.html",
    "ai-girl-malaysia.com/index.html",
    "ai-girl-malaysia.com/assets/fy.css",
    "ai-girl-malaysia.com/js/shell-new.js",
    "ai-girl-malaysia.com/js/purchasePoints.js",
    "ai-girl-malaysia.com/Subscriptions.html",
    "ai-girl-malaysia.com/js/subscriptions.js",
    "ai-girl-malaysia.com/Image.html",
    "ai-girl-malaysia.com/js/main.js",
    "ai-girl-malaysia.com/js/chat.js",
    "ai-girl-malaysia.com/js/customVideo.js",
    "ai-girl-malaysia.com/js/private_content.js",
    "ai-girl-malaysia.com/js/series-dialog.js",
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
mysql -h"$DBH" -u"$DBU" "$DBN" < sql/upgrade_vip_starpay.sql
mysql -h"$DBH" -u"$DBU" "$DBN" < sql/upgrade_vip_tier.sql
mysql -h"$DBH" -u"$DBU" "$DBN" < sql/upgrade_vip_plans_v2.sql
mysql -h"$DBH" -u"$DBU" "$DBN" < sql/upgrade_eth_recharge.sql
echo "--- 迁移后校验 ---"
mysql -h"$DBH" -u"$DBU" "$DBN" -e "
  SELECT COUNT(*) AS packages FROM lp_recharge_package;
  SHOW COLUMNS FROM lp_recharge_order LIKE 'pay_url';
  SHOW COLUMNS FROM lp_recharge_order LIKE 'biz_type';
  SHOW COLUMNS FROM lp_recharge_order LIKE 'vip_plan_id';
  SELECT level, tokens_month, months, usd_price, LEFT(benefits, 60) AS benefits_head FROM lp_vip_plan WHERE status = 1 ORDER BY sort LIMIT 4;
  SELECT COUNT(*) AS active_plans FROM lp_vip_plan WHERE status = 1;
  SELECT \`key\`, \`value\` FROM lp_platform_config WHERE \`key\` IN ('free_chat_daily_limit','vip_unblur_locked_preview','eth_usd_rate');
  SHOW COLUMNS FROM lp_user_quota;
  SELECT id, name, type, LEFT(address, 12) AS addr_head, diamond_rate, min_amount, confirm_mode, status FROM lp_recharge_channel ORDER BY sort;"
"""

MIN_AMOUNT_STEP = DB_ENV + r"""
set -e
echo "--- 最低档价格 9.99 改为 10.00（网关 USD 最小金额=10.00）---"
mysql -h"$DBH" -u"$DBU" "$DBN" -e "
  UPDATE lp_recharge_package SET price = 10.00, updated_at = NOW() WHERE tokens = 100 AND price < 10.00;
  SELECT id, tokens, price, bonus_percent, sort, status FROM lp_recharge_package ORDER BY sort;"
"""


E2E_STEP = r'''
cat > /tmp/starpay_e2e.php <<'PHPEOF'
<?php
require '/www/wwwroot/douyin/php/vendor/autoload.php';
$app = new \think\App();
$app->initialize();

use think\facade\Config;
use think\facade\Db;

$cfg = (array) Config::get('starpay');
$mno = (string) $cfg['merchant_no'];
$key = (string) $cfg['key'];
printf("网关=%s 商户号=%s 密钥长度=%d 支付方式=%s\n", $cfg['gateway'], $mno, strlen($key),
    implode(',', array_column((array) $cfg['products'], 'code')));

$db  = Db::connect('live_mysql');
$svc = new \app\live\service\RechargeService();
echo '自选报价 quote(550)=' . json_encode($svc->quoteByTokens(550)) . "\n\n";

echo "--- 验签自检 ---\n";
var_dump($cfg['merchant_no'], $cfg['fiat_currency'], $cfg['fiat_products']);
$tbiz    = ['merchant_ref' => 'TESTONLY', 'status' => 1];
$tparams = json_encode($tbiz, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$tts     = (string) time();
$tsign   = md5((string) $cfg['merchant_no'] . $tparams . 'MD5' . $tts . (string) $cfg['key']);
$self    = new \app\live\service\StarPayService();
try {
    $self->verifyNotify([
        'merchant_no' => (string) $cfg['merchant_no'],
        'timestamp'   => $tts,
        'sign_type'   => 'MD5',
        'params'      => $tparams,
        'sign'        => $tsign,
    ]);
    echo "verifyNotify 自检 = OK（config 与 .env 一致）\n\n";
} catch (\Throwable $e) {
    echo 'verifyNotify 自检 = FAIL：' . get_class($e) . ' ' . $e->getMessage() . "\n\n";
}

$uid = (int) $db->table('lp_recharge_order')->order('id desc')->value('user_id');
if ($uid <= 0) { $uid = (int) $db->table('lp_user')->order('id asc')->value('id'); }
$before = (float) $db->table('lp_wallet_account')->where('user_id', $uid)->value('diamond_balance');
printf("测试用户=%d 充值前点数=%.2f\n", $uid, $before);

$pkgId = (int) $db->table('lp_recharge_package')->where('tokens', 550)->value('id');
$order = $svc->submitPackageOrder($uid, $pkgId, 'USDT-TRC20Deposit');
$orderNo = (string) $order['order_no'];
printf("下单: order_no=%s amount=%s diamonds=%s system_ref=%s\npay_url=%s\n\n",
    $orderNo, $order['pay_amount'], $order['diamond_amount'], $order['system_ref'], $order['pay_url']);

function notify($url, $mno, $key, $biz, $tamper = false) {
    $params = json_encode($biz, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $ts = (string) time();
    $sign = md5($mno . $params . 'MD5' . $ts . $key);
    if ($tamper) { $sign = substr($sign, 0, -1) . (substr($sign, -1) === '0' ? '1' : '0'); }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30,
        CURLOPT_POSTFIELDS => http_build_query([
            'merchant_no' => $mno, 'timestamp' => $ts, 'sign_type' => 'MD5',
            'params' => $params, 'sign' => $sign,
        ]),
    ]);
    $r = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$http, trim((string) $r)];
}

$url = 'http://127.0.0.1:8082/api/v1/recharge/notify';
$biz = [
    'merchant_ref' => $orderNo,
    'system_ref'   => (string) ($order['system_ref'] ?: 'TESTSYSREF'),
    'amount'       => number_format((float) $order['pay_amount'], 2, '.', ''),
    'fee'          => '0.00',
    'status'       => 1,
    'success_time' => time(),
];

list($http, $resp) = notify($url, $mno, $key, $biz, true);
printf("回调(篡改签名): http=%d resp=%s   期望 FAIL\n", $http, $resp);

list($http, $resp) = notify($url, $mno, $key, $biz);
$o  = $db->table('lp_recharge_order')->where('order_no', $orderNo)->find();
$b1 = (float) $db->table('lp_wallet_account')->where('user_id', $uid)->value('diamond_balance');
printf("回调(合法签名): http=%d resp=%s -> 订单状态=%d 点数=%.2f（期望 1 / %.2f）\n",
    $http, $resp, (int) $o['status'], $b1, $before + (float) $order['diamond_amount']);

list($http, $resp) = notify($url, $mno, $key, $biz);
$b2 = (float) $db->table('lp_wallet_account')->where('user_id', $uid)->value('diamond_balance');
printf("回调(重复推送): http=%d resp=%s -> 点数=%.2f（应不变，幂等）\n", $http, $resp, $b2);

$ledger = $db->table('lp_wallet_ledger')->where('biz_id', (int) $o['id'])->where('biz_type', 'recharge')->select()->toArray();
echo '流水记录数=' . count($ledger) . "\n\n";

$db->table('lp_wallet_ledger')->where('biz_id', (int) $o['id'])->where('biz_type', 'recharge')->delete();
$db->table('lp_wallet_account')->where('user_id', $uid)->update(['diamond_balance' => $before, 'updated_at' => date('Y-m-d H:i:s')]);
$db->table('lp_recharge_order')->where('order_no', $orderNo)->delete();
printf("清理测试数据: 点数=%.2f 残留订单=%d 残留流水=%d\n",
    (float) $db->table('lp_wallet_account')->where('user_id', $uid)->value('diamond_balance'),
    (int) $db->table('lp_recharge_order')->where('order_no', $orderNo)->count(),
    (int) $db->table('lp_wallet_ledger')->where('biz_id', (int) $o['id'])->count());
PHPEOF
rm -f /tmp/notify_dbg.log
php /tmp/starpay_e2e.php
echo "########## 回调调试日志 ##########"
cat /tmp/notify_dbg.log 2>/dev/null || echo "(无)"
'''


VIP_E2E_STEP = r'''
cat > /tmp/starpay_vip_e2e.php <<'PHPEOF'
<?php
require '/www/wwwroot/douyin/php/vendor/autoload.php';
$app = new \think\App();
$app->initialize();

use think\facade\Config;
use think\facade\Db;

$cfg = (array) Config::get('starpay');
$mno = (string) $cfg['merchant_no'];
$key = (string) $cfg['key'];
$db  = Db::connect('live_mysql');
$rec = new \app\live\service\RechargeService();
$vip = new \app\live\service\VipService();

$uid = (int) $db->table('lp_recharge_order')->order('id desc')->value('user_id');
if ($uid <= 0) { $uid = (int) $db->table('lp_user')->order('id asc')->value('id'); }

$plan = $db->table('lp_vip_plan')->where('status', 1)->order('months asc')->find();
$planId = (int) $plan['id'];
$userBefore = $db->table('lp_user')->where('id', $uid)->field('vip_expire_at')->find();
$balanceBefore = (float) $db->table('lp_wallet_account')->where('user_id', $uid)->value('diamond_balance');
printf("测试用户=%d 套餐=%s(%d个月 USD %s) 充值前会员到期=%s 钻石=%.2f\n\n",
    $uid, $plan['name'], (int) $plan['months'], $plan['usd_price'],
    (string) ($userBefore['vip_expire_at'] ?? '无'), $balanceBefore);

function notify($url, $mno, $key, $biz, $tamper = false) {
    $params = json_encode($biz, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $ts = (string) time();
    $sign = md5($mno . $params . 'MD5' . $ts . $key);
    if ($tamper) { $sign = substr($sign, 0, -1) . (substr($sign, -1) === '0' ? '1' : '0'); }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30,
        CURLOPT_POSTFIELDS => http_build_query([
            'merchant_no' => $mno, 'timestamp' => $ts, 'sign_type' => 'MD5',
            'params' => $params, 'sign' => $sign,
        ]),
    ]);
    $r = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$http, trim((string) $r)];
}

echo "--- 1) 订阅下单（真实网关）---\n";
$order = $rec->submitVipOrder($uid, $planId, 'USDT-TRC20Deposit');
$orderNo = (string) $order['order_no'];
printf("order_no=%s pay_amount=%s biz_type=%s vip_plan_id=%s product=%s\npay_url=%s\n",
    $orderNo, $order['pay_amount'], $order['biz_type'], $order['vip_plan_id'],
    $order['product'], $order['pay_url']);
printf("断言: 金额=%s(期望 %s) 业务类型=vip pay_url=%s\n\n",
    $order['pay_amount'], number_format((float) $plan['usd_price'], 2, '.', ''),
    $order['pay_url'] !== '' ? '有' : '无');

echo "--- 2) 回调（篡改签名，应拒）---\n";
$url = 'http://127.0.0.1:8082/api/v1/recharge/notify';
$biz = [
    'merchant_ref' => $orderNo,
    'system_ref'   => (string) ($order['system_ref'] ?: 'VIPTESTSYSREF'),
    'amount'       => number_format((float) $order['pay_amount'], 2, '.', ''),
    'fee'          => '0.00',
    'status'       => 1,
    'success_time' => time(),
];
list($http, $resp) = notify($url, $mno, $key, $biz, true);
printf("http=%d resp=%s   期望 FAIL\n\n", $http, $resp);

echo "--- 3) 回调（合法签名，应开通会员）---\n";
list($http, $resp) = notify($url, $mno, $key, $biz);
$o1 = $db->table('lp_recharge_order')->where('order_no', $orderNo)->find();
$u1 = $db->table('lp_user')->where('id', $uid)->field('vip_expire_at')->find();
$orders1 = $db->table('lp_vip_order')->where('order_no', $orderNo)->count();
$balance1 = (float) $db->table('lp_wallet_account')->where('user_id', $uid)->value('diamond_balance');
$status1 = $vip->status($uid);
printf("http=%d resp=%s -> 订单状态=%d 会员到期=%s 会员单数=%d 剩余天数=%d 钻石=%.2f（应不变）\n",
    $http, $resp, (int) $o1['status'], (string) $u1['vip_expire_at'], (int) $orders1,
    (int) $status1['remain_days'], $balance1);

echo "\n--- 4) 重复推送（幂等）---\n";
list($http, $resp) = notify($url, $mno, $key, $biz);
$u2 = $db->table('lp_user')->where('id', $uid)->field('vip_expire_at')->find();
$orders2 = $db->table('lp_vip_order')->where('order_no', $orderNo)->count();
printf("http=%d resp=%s -> 会员到期=%s 会员单数=%d（应与上一步一致）\n\n",
    $http, $resp, (string) $u2['vip_expire_at'], (int) $orders2);

echo "--- 5) 续订（到期顺延，走 USDT-ERC20 收款地址产品）---\n";
$order2 = $rec->submitVipOrder($uid, $planId, 'USDT-ERC20Deposit');
$orderNo2 = (string) $order2['order_no'];
$biz2 = $biz;
$biz2['merchant_ref'] = $orderNo2;
$biz2['system_ref']   = (string) ($order2['system_ref'] ?: 'VIPTESTSYSREF2');
list($http, $resp) = notify($url, $mno, $key, $biz2);
$u3 = $db->table('lp_user')->where('id', $uid)->field('vip_expire_at')->find();
$base = strtotime((string) $u2['vip_expire_at']);
$expect = date('Y-m-d H:i:s', strtotime('+' . (int) $plan['months'] . ' months', $base));
printf("http=%d resp=%s 续订单=%s 产品=%s pay_url=%s -> 到期=%s（期望 %s）\n\n",
    $http, $resp, $orderNo2, $order2['product'],
    (string) $order2['pay_url'] !== '' ? '有' : '无', (string) $u3['vip_expire_at'], $expect);

echo "--- 6) 清理测试数据 ---\n";
$db->table('lp_vip_order')->where('order_no', 'in', [$orderNo, $orderNo2])->delete();
$db->table('lp_user')->where('id', $uid)->update(['vip_expire_at' => $userBefore['vip_expire_at'] ?? null]);
$db->table('lp_recharge_order')->where('order_no', 'in', [$orderNo, $orderNo2])->delete();
printf("会员到期还原=%s 残留会员单=%d 残留订单=%d\n",
    (string) $db->table('lp_user')->where('id', $uid)->value('vip_expire_at'),
    (int) $db->table('lp_vip_order')->where('order_no', 'in', [$orderNo, $orderNo2])->count(),
    (int) $db->table('lp_recharge_order')->where('order_no', 'in', [$orderNo, $orderNo2])->count());
PHPEOF
php /tmp/starpay_vip_e2e.php
'''


DEPOSIT_STEP = r'''
cat > /tmp/starpay_deposit.php <<'PHPEOF'
<?php
$env  = parse_ini_file('/www/wwwroot/douyin/php/.env', true, INI_SCANNER_RAW);
$c    = $env['STARPAY'] ?? [];
$base = rtrim((string) ($c['GATEWAY'] ?? ''), '/');
$mno  = trim((string) ($c['MERCHANT_NO'] ?? ''));
$key  = trim((string) ($c['KEY'] ?? ''));

$product = $argv[1] ?? 'USDT-TRC20Deposit';
$amount  = $argv[2] ?? '10.00';

$ts     = (string) time();
$params = json_encode([
    'merchant_ref' => 'DEPCHK' . substr(md5($product . $amount), 0, 6) . time(),
    'product'      => $product,
    'amount'       => $amount,
    'notify_url'   => (string) ($c['NOTIFY_URL'] ?? ''),
    'return_url'   => (string) ($c['RETURN_URL'] ?? ''),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$sign = md5($mno . $params . 'MD5' . $ts . $key);

$ch = curl_init($base . '/api/gateway/pay');
curl_setopt_array($ch, [
    CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30,
    CURLOPT_POSTFIELDS => http_build_query([
        'merchant_no' => $mno, 'timestamp' => $ts, 'sign_type' => 'MD5',
        'params' => $params, 'sign' => $sign,
    ]),
]);
$raw  = (string) curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
printf("product=%s amount=%s http=%s\n", $product, $amount, $http);
$body = json_decode($raw, true);
$biz  = json_decode((string) ($body['params'] ?? ''), true) ?: [];
echo "message=" . (string) ($body['message'] ?? '') . "\n";
foreach ($biz as $k => $v) {
    printf("  %-16s = %s\n", $k, is_scalar($v) ? (string) $v : json_encode($v));
}
PHPEOF
php /tmp/starpay_deposit.php USDT-TRC20Deposit 10.00
php /tmp/starpay_deposit.php USDT-ERC20Deposit 10.00
php /tmp/starpay_deposit.php USDCDeposit 10.00
'''


CRON_STEP = r'''
echo "===== 现有调度 ====="
cat /etc/cron.d/douyin 2>/dev/null || echo "(无 /etc/cron.d/douyin)"
echo "--- root crontab ---"
crontab -l 2>/dev/null || echo "(空)"
echo "===== 归一化：ETH 扫链写进 /etc/cron.d/douyin（与 USDT 同风格）====="
if crontab -l 2>/dev/null | grep -q 'recharge:scan-eth'; then
    crontab -l 2>/dev/null | grep -v 'recharge:scan-eth' | crontab -
    echo "已从 root crontab 移除误写的 scan-eth"
fi
if grep -q 'recharge:scan-eth' /etc/cron.d/douyin 2>/dev/null; then
    echo "/etc/cron.d/douyin 已有 scan-eth，跳过"
else
    echo '* * * * * root cd /www/wwwroot/douyin/php && php think recharge:scan-eth >> /root/cron_recharge_eth.log 2>&1' >> /etc/cron.d/douyin
    chmod 0644 /etc/cron.d/douyin
    echo "已追加到 /etc/cron.d/douyin"
fi
echo "===== 写入后 ====="
cat /etc/cron.d/douyin 2>/dev/null || true
echo "--- root crontab ---"
crontab -l 2>/dev/null || echo "(空)"
echo "===== 手动跑一次 ETH 扫链（验证命令可用）====="
cd /www/wwwroot/douyin/php && php think recharge:scan-eth 2>&1 | tail -n 5
'''


PRODUCT_PROBE_STEP = r'''
cat > /tmp/starpay_product_probe.php <<'PHPEOF'
<?php
$env  = parse_ini_file('/www/wwwroot/douyin/php/.env', true, INI_SCANNER_RAW);
$c    = $env['STARPAY'] ?? [];
$base = rtrim((string) ($c['GATEWAY'] ?? ''), '/');
$mno  = trim((string) ($c['MERCHANT_NO'] ?? ''));
$key  = trim((string) ($c['KEY'] ?? ''));

function probe_post($base, $mno, $key, $path, $biz) {
    $ts     = (string) time();
    $params = json_encode($biz, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $sign   = md5($mno . $params . 'MD5' . $ts . $key);
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
        CURLOPT_POSTFIELDS => http_build_query([
            'merchant_no' => $mno, 'timestamp' => $ts, 'sign_type' => 'MD5',
            'params' => $params, 'sign' => $sign,
        ]),
    ]);
    $raw  = (string) curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$http, $raw];
}

$candidates = ['TRC20Buy','ERC20Buy','USDT-TRC20Deposit','USDCDeposit','ETHBuy','ETHDeposit','ETH-ERC20Buy','ETH-ERC20Deposit','ETHERC20Buy','ETHERC20Deposit','ETHH5','ETH','BTCBuy','BTCDeposit','LTCBuy','LTCDeposit'];

echo "########## amount/range 探测 ##########\n";
foreach ($candidates as $p) {
    [$http, $raw] = probe_post($base, $mno, $key, '/api/gateway/buy/coin/amount/range', ['product' => $p]);
    $body = json_decode($raw, true);
    printf("%-18s http=%s code=%s msg=%s params=%s\n", $p, $http,
        (string) ($body['code'] ?? ''), (string) ($body['message'] ?? ''),
        mb_substr((string) ($body['params'] ?? ''), 0, 200));
}

echo "\n########## 产品列表类接口探测 ##########\n";
foreach (['/api/gateway/product/list', '/api/gateway/products', '/api/gateway/product', '/api/gateway/merchant/products'] as $path) {
    [$http, $raw] = probe_post($base, $mno, $key, $path, []);
    printf("%-34s http=%s raw=%s\n", $path, $http, mb_substr((string) $raw, 0, 220));
}
PHPEOF
php /tmp/starpay_product_probe.php
'''


TOKEN_STEP = r'''
cat > /tmp/vip_token.php <<'PHPEOF'
<?php
require '/www/wwwroot/douyin/php/vendor/autoload.php';
$app = new \think\App();
$app->initialize();
use think\facade\Db;

$uid = (int) $argv[1];
$u = Db::connect('live_mysql')->table('lp_user')->where('id', $uid)->find();
if (!$u) { echo "user {$uid} not found\n"; exit(1); }
printf("user: id=%s user_no=%s nickname=%s vip_expire_at=%s\n",
    $u['id'], (string) ($u['user_no'] ?? ''), (string) ($u['nickname'] ?? ''),
    (string) ($u['vip_expire_at'] ?? '无'));
echo \app\live\service\JwtService::generateAccessToken(
    (int) $u['id'], (string) ($u['user_no'] ?? ''), (string) ($u['nickname'] ?? '')
) . "\n";
PHPEOF
php /tmp/vip_token.php "__UID__"
'''


CLEANVIP_STEP = DB_ENV + r"""
set -e
echo "--- 清理浏览器联调产生的未支付订阅单 ---"
mysql -h"$DBH" -u"$DBU" "$DBN" -e "
  SELECT id, order_no, biz_type, vip_plan_id, pay_amount, status, created_at
  FROM lp_recharge_order WHERE biz_type = 'vip' ORDER BY id DESC LIMIT 10;
  DELETE FROM lp_recharge_order
  WHERE biz_type = 'vip' AND status = 0 AND created_at > DATE_SUB(NOW(), INTERVAL 2 HOUR);
  SELECT COUNT(*) AS vip_unpaid_left FROM lp_recharge_order WHERE biz_type = 'vip' AND status = 0;"
"""


TIER_E2E_STEP = r'''
cat > /tmp/vip_tier_e2e.php <<'PHPEOF'
<?php
require '/www/wwwroot/douyin/php/vendor/autoload.php';
$app = new \think\App();
$app->initialize();

use think\facade\Db;

$db     = Db::connect('live_mysql');
$vipSvc = new \app\live\service\VipService();
$quota  = new \app\live\service\QuotaService();

$uid = (int) $db->table('lp_user')->order('id asc')->value('id');
$userBefore = $db->table('lp_user')->where('id', $uid)->field('vip_expire_at')->find();
$db->table('lp_user')->where('id', $uid)->update(['vip_expire_at' => null]);
$db->table('lp_user_quota')->where('user_id', $uid)->delete();

$premium = $db->table('lp_vip_plan')->where('level', 'premium')->where('months', 1)->find();
$plus    = $db->table('lp_vip_plan')->where('level', 'premium_plus')->where('months', 1)->find();
printf("测试用户=%d 套餐: premium(id=%d %s月 USD %s) plus(id=%d %s月 USD %s)\n\n",
    $uid, $premium['id'], $premium['months'], $premium['usd_price'], $plus['id'], $plus['months'], $plus['usd_price']);

echo "--- 1) 免费用户权益 ---\n";
$e = $vipSvc->entitlements($uid);
printf("is_vip=%s level=%s level_name=%s 聊天额度=%d 生成额度=%d 折扣=%d%% 定制视频=%s\n\n",
    $e['is_vip'] ? '1' : '0', $e['level'], $e['level_name'], $e['chat_daily_limit'],
    $e['image_daily_limit'], $e['discount_percent'], $e['custom_video'] ? '可用' : '不可用');

echo "--- 2) 免费用户聊天额度拦截 ---\n";
for ($i = 0; $i < $e['chat_daily_limit']; $i++) { $quota->incr($uid, 'chat'); }
printf("已用=%d / 上限=%d\n", $quota->used($uid, 'chat'), $e['chat_daily_limit']);
try {
    $quota->check($uid, 'chat', $e['chat_daily_limit'], 'daily free messages used up');
    echo "!! 未拦截（异常未抛出）\n";
} catch (\Throwable $ex) {
    printf("第 %d 条被拦截: %s (code=%s)\n", $e['chat_daily_limit'] + 1, $ex->getMessage(),
        method_exists($ex, 'resultCode') && $ex->resultCode ? $ex->resultCode->value : '-');
}
$db->table('lp_user_quota')->where('user_id', $uid)->delete();

echo "\n--- 3) 开通 Premium（1 个月）---\n";
$vipSvc->activate($uid, (int) $premium['id'], 'TIERTEST-P-' . time(), (float) $premium['diamond_price']);
$e = $vipSvc->entitlements($uid);
printf("level=%s(%s) 聊天额度=%d(0=不限) 生成额度=%d 折扣=%d%% 权益文案=%d条 每日领钻=%s\n",
    $e['level'], $e['level_name'], $e['chat_daily_limit'], $e['image_daily_limit'],
    $e['discount_percent'], count($e['benefits']), $e['daily_diamond']);
printf("折扣价校验: 100钻->%d（期望%d） 1000钻->%d（期望%d） 内容/商城同价\n\n",
    $vipSvc->discounted(100, $e['discount_percent']), (int) round(100 * (100 - $e['discount_percent']) / 100),
    $vipSvc->discounted(1000, $e['discount_percent']), (int) round(1000 * (100 - $e['discount_percent']) / 100));

echo "--- 4) 升级 Premium Plus（层级取最高）---\n";
$vipSvc->activate($uid, (int) $plus['id'], 'TIERTEST-X-' . time(), (float) $plus['diamond_price']);
$e2 = $vipSvc->entitlements($uid);
printf("level=%s(%s) 生成额度=%d 折扣=%d%% 每日领钻=%s\n",
    $e2['level'], $e2['level_name'], $e2['image_daily_limit'], $e2['discount_percent'], $e2['daily_diamond']);
printf("折扣价校验: 100钻->%d（期望%d） 1000钻->%d（期望%d）\n\n",
    $vipSvc->discounted(100, $e2['discount_percent']), (int) round(100 * (100 - $e2['discount_percent']) / 100),
    $vipSvc->discounted(1000, $e2['discount_percent']), (int) round(1000 * (100 - $e2['discount_percent']) / 100));

echo "--- 5) 会员聊天不限（额度为 0 时不拦截）---\n";
for ($i = 0; $i < 30; $i++) { $quota->incr($uid, 'chat'); }
try {
    $quota->check($uid, 'chat', (int) $e2['chat_daily_limit'], 'should not throw');
    printf("已发 %d 条仍可继续发送（chat_daily_limit=%d 视为不限）\n", $quota->used($uid, 'chat'), $e2['chat_daily_limit']);
} catch (\Throwable $ex) {
    echo "!! 会员被误拦截: " . $ex->getMessage() . "\n";
}

echo "\n--- 6) 清理测试数据 ---\n";
$db->table('lp_vip_order')->where('order_no', 'like', 'TIERTEST-%')->delete();
$db->table('lp_user_quota')->where('user_id', $uid)->delete();
$db->table('lp_user')->where('id', $uid)->update(['vip_expire_at' => $userBefore['vip_expire_at']]);
printf("会员到期还原=%s 残留会员单=%d 残留额度记录=%d\n",
    (string) $db->table('lp_user')->where('id', $uid)->value('vip_expire_at'),
    (int) $db->table('lp_vip_order')->where('order_no', 'like', 'TIERTEST-%')->count(),
    (int) $db->table('lp_user_quota')->where('user_id', $uid)->count());
PHPEOF
php /tmp/vip_tier_e2e.php
'''


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
         app/live/service/VipService.php app/live/service/QuotaService.php \
         app/live/service/WalletService.php \
         app/api/controller/Recharge.php app/api/controller/Vip.php \
         app/api/controller/Live.php app/api/controller/CustomVideo.php \
         app/api/controller/PrivateContent.php app/api/controller/Shorts.php \
         app/api/controller/Shop.php \
         app/api/route/recharge.php app/api/route/vip.php \
         app/admin/controller/live/RechargeChannel.php; do
  php -l "%s/$f"
done
echo "--- 静态文件 ---"
ls -la %s/purchasePoints.html %s/js/purchasePoints.js %s/Subscriptions.html %s/js/subscriptions.js
ls -la %s/tokens_files/coin_1-*.webp %s/tokens_files/coin_custom-*.webp
ls -la %s/taocan_files/jquery-3.6.0.min.js %s/taocan_files/application-3ff79f06214141f503df3b79558fc49b57ca74f1323caab428d044cf2ce69ffe.css
echo "taocan_files 文件数: $(ls %s/taocan_files | wc -l)"
echo "订阅页残留 candy.ai 引用数: $(grep -c 'candy.ai' %s/Subscriptions.html || true)"
echo "--- 套餐接口 ---"
curl -s -m 20 'http://127.0.0.1:%s/api/v1/recharge/packages' | sed 's/},{"id"/}\n{"id"/g' | tail -n 2
echo
echo "--- 会员套餐接口 ---"
curl -s -m 20 'http://127.0.0.1:%s/api/v1/vip/plans' | head -c 400
echo
echo "--- 下单接口未登录应鉴权失败 ---"
curl -s -m 20 -X POST 'http://127.0.0.1:%s/api/v1/recharge/createOrder' -d 'package_id=1' | head -c 200
echo
curl -s -m 20 -X POST 'http://127.0.0.1:%s/api/v1/vip/order' -d 'plan_id=1' | head -c 200
echo
echo "--- 表结构 ---"
mysql -h"$DBH" -u"$DBU" "$DBN" -e "
  SELECT id,tokens,price,bonus_percent,sort,status FROM lp_recharge_package ORDER BY sort;
  SELECT id,name,months,usd_price,diamond_price,daily_diamond FROM lp_vip_plan ORDER BY sort;
  SHOW COLUMNS FROM lp_recharge_order LIKE 'biz_type';"
echo "--- 公网页面可达性（H5 站点在 8082，RETURN_URL 用它）---"
for u in http://sugus.it.com:8082/purchasePoints.html http://sugus.it.com:8082/Subscriptions.html; do
  curl -s -k -m 15 -o /dev/null -w "%%{http_code} %%{size_download} $u\n" "$u"
done
""" % (PHP_DIR, SITE, SITE, SITE, SITE, SITE, SITE, SITE, SITE, SITE, SITE, API_PORT, API_PORT, API_PORT, API_PORT))

URL_STEP = r"""
echo "--- 候选公网页面地址 ---"
for u in http://sugus.it.com:8082/Subscriptions.html http://172.81.98.55:8082/Subscriptions.html \
         http://sugus.it.com:8082/purchasePoints.html \
         https://sugus.it.com/api/v1/recharge/packages https://sugus.it.com/api/v1/vip/plans; do
  curl -s -k -m 15 -o /dev/null -w "%{http_code} %{size_download} $u\n" "$u"
done
echo "--- 缓存头（html / js / css）---"
for u in http://sugus.it.com:8082/purchasePoints.html http://sugus.it.com:8082/js/purchasePoints.js http://sugus.it.com:8082/assets/fy.css; do
  echo "== $u"
  curl -s -k -m 15 -D - -o /dev/null "$u" | tr -d '\r' | grep -iE '^HTTP/|cache-control|expires|etag|last-modified'
done
echo "--- 通知地址可达性（无签名应为 FAIL，但 HTTP 200 说明路由通）---"
curl -s -k -m 15 -X POST "https://sugus.it.com/api/v1/recharge/notify" -d "merchant_no=x" -w " <- http=%{http_code}\n"
"""


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


def shell_pages():
    """所有引用了 js/shell-new.js 的页面（即挂了新版外壳的页面）"""
    site = os.path.join(LOCAL, "ai-girl-malaysia.com")
    out = []
    for name in sorted(os.listdir(site)):
        if not name.lower().endswith(".html"):
            continue
        with open(os.path.join(site, name), "rb") as f:
            if b"js/shell-new.js" in f.read():
                out.append("ai-girl-malaysia.com/" + name)
    return out


def upload_dir(sftp, buf, local_rel, remote_dir):
    """整目录上传（订阅页版式用的 taocan_files 静态资产）"""
    local_dir = os.path.join(LOCAL, local_rel.replace("/", os.sep))
    mkdir_remote(sftp, remote_dir)
    count = 0
    for name in sorted(os.listdir(local_dir)):
        path = os.path.join(local_dir, name)
        if not os.path.isfile(path):
            continue
        with open(path, "rb") as f:
            data = f.read()
        with sftp.open(remote_dir + "/" + name, "wb") as f:
            f.write(data)
        count += 1
    log_write(buf, "uploaded dir %s (%d files)" % (remote_dir, count))


def local_starpay_env():
    """从本地 php/.env 的 [STARPAY] 段读取密钥（php/.env 已被 git 忽略，不入库）"""
    cfg = {}
    try:
        with open(os.path.join(LOCAL, "php", ".env"), "r", encoding="utf-8", errors="replace") as f:
            section = ""
            for line in f:
                line = line.strip()
                if line.startswith("[") and line.endswith("]"):
                    section = line[1:-1].strip().upper()
                    continue
                if section != "STARPAY" or line.startswith("#") or "=" not in line:
                    continue
                k, v = line.split("=", 1)
                cfg[k.strip().upper()] = v.strip()
    except IOError as exc:
        print("read local .env failed:", exc)
    return cfg


def starpay_section():
    c = local_starpay_env()
    if not c.get("MERCHANT_NO") or not c.get("KEY"):
        raise SystemExit("本地 php/.env 缺少 [STARPAY] MERCHANT_NO / KEY")
    order = ["GATEWAY", "MERCHANT_NO", "KEY", "PRODUCT", "FIAT_CURRENCY", "PRODUCTS",
             "NOTIFY_URL", "RETURN_URL", "RETURN_URL_VIP", "MIN_AMOUNT"]
    return "[STARPAY]\n" + "".join("%s = %s\n" % (k, c[k]) for k in order if c.get(k))


def ensure_env(sftp, buf, cli):
    """写入服务器 php/.env 的 [STARPAY] 段（已存在则替换）"""
    log_write(buf, "\n===== 服务器 .env [STARPAY] =====")
    remote = PHP_DIR + "/.env"
    try:
        with sftp.open(remote, "rb") as f:
            raw = f.read()
    except IOError as exc:
        log_write(buf, "read .env failed: %s" % exc)
        return
    text = raw.decode("utf-8", "replace")

    lines = text.split("\n")
    kept, skipping = [], False
    for line in lines:
        stripped = line.strip()
        if stripped == "[STARPAY]":
            skipping = True
            continue
        if skipping:
            if stripped.startswith("[") and stripped.endswith("]"):
                skipping = False
            else:
                continue
        kept.append(line)

    section = starpay_section()
    body = "\n".join(kept).rstrip("\n")
    upload_bytes(sftp, buf, remote, (body + "\n\n" + section).encode("utf-8"),
                 ".bak_starpayenv_" + STAMP)
    log_write(buf, "[STARPAY] 已写入（MERCHANT_NO=%s / KEY 长度=%d / 产品=%s）"
              % (local_starpay_env().get("MERCHANT_NO", ""), len(local_starpay_env().get("KEY", "")),
                 local_starpay_env().get("PRODUCTS", "")))


GATEWAY_STEP = r'''
cat > /tmp/starpay_check2.php <<'PHPEOF'
<?php
$env  = parse_ini_file('/www/wwwroot/douyin/php/.env', true, INI_SCANNER_RAW);
$c    = $env['STARPAY'] ?? [];
$base = rtrim((string) ($c['GATEWAY'] ?? ''), '/');
$mno  = trim((string) ($c['MERCHANT_NO'] ?? ''));
$key  = trim((string) ($c['KEY'] ?? ''));

function post($base, $mno, $key, $path, $biz) {
    $ts     = (string) time();
    $params = json_encode($biz, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $sign   = md5($mno . $params . 'MD5' . $ts . $key);
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30,
        CURLOPT_POSTFIELDS => http_build_query([
            'merchant_no' => $mno, 'timestamp' => $ts, 'sign_type' => 'MD5',
            'params' => $params, 'sign' => $sign,
        ]),
    ]);
    $raw  = (string) curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$http, json_decode($raw, true), $raw];
}

echo "########## 各产品 USD 限额 ##########\n";
foreach (['TRC20Buy', 'ERC20Buy', 'USDCERC20Buy', 'TRC20H5', 'USDT-TRC20Deposit', 'USDCDeposit'] as $p) {
    [$http, $body, $raw] = post($base, $mno, $key, '/api/gateway/buy/coin/amount/range', ['product' => $p]);
    $rows = json_decode((string) ($body['params'] ?? '[]'), true);
    $usd  = null;
    foreach ((array) $rows as $row) {
        if (($row['bank_code'] ?? '') === 'USD') { $usd = $row; }
    }
    printf("%-20s http=%s rows=%d USD=%s msg=%s\n", $p, $http, is_array($rows) ? count($rows) : -1,
        json_encode($usd), (string) ($body['message'] ?? ''));
}

echo "\n########## 逐产品试单 10.00 USD ##########\n";
foreach (['TRC20Buy', 'ERC20Buy', 'USDCERC20Buy', 'TRC20H5'] as $p) {
    [$http, $body, $raw] = post($base, $mno, $key, '/api/gateway/pay', [
        'merchant_ref' => 'CHK' . $p . time(),
        'product'      => $p,
        'amount'       => '10.00',
        'notify_url'   => (string) ($c['NOTIFY_URL'] ?? ''),
        'return_url'   => (string) ($c['RETURN_URL'] ?? ''),
        'extra'        => ['fiat_currency' => 'USD'],
    ]);
    printf("%-20s http=%s msg=%s params=%s\n", $p, $http, (string) ($body['message'] ?? ''),
        mb_substr((string) ($body['params'] ?? ''), 0, 320));
}

echo "\n########## 试单 9.99 USD（当前最低档）##########";
[$http, $body, $raw] = post($base, $mno, $key, '/api/gateway/pay', [
    'merchant_ref' => 'CHK999' . time(), 'product' => 'TRC20Buy', 'amount' => '9.99',
    'extra'        => ['fiat_currency' => 'USD'],
]);
printf("http=%s msg=%s\n", $http, (string) ($body['message'] ?? ''));

echo "\n########## 地址类（充值）产品试单 ##########\n";
foreach (['USDT-TRC20Deposit', 'USDT-ERC20Deposit', 'USDCDeposit'] as $p) {
    foreach ([['amount' => '10.00'], ['amount' => '50.00']] as $case) {
        [$http, $body, $raw] = post($base, $mno, $key, '/api/gateway/pay', [
            'merchant_ref' => 'DEP' . substr(md5($p . $case['amount'] . time()), 0, 8),
            'product'      => $p,
            'amount'       => $case['amount'],
            'notify_url'   => (string) ($c['NOTIFY_URL'] ?? ''),
            'return_url'   => (string) ($c['RETURN_URL'] ?? ''),
        ]);
        printf("%-20s amount=%-6s http=%s msg=%s params=%s\n", $p, $case['amount'], $http,
            (string) ($body['message'] ?? ''), (string) ($body['params'] ?? ''));
    }
}

echo "\n########## 试单 299.99 USD（当前最高档）##########\n";
[$http, $body, $raw] = post($base, $mno, $key, '/api/gateway/pay', [
    'merchant_ref' => 'CHK299' . time(), 'product' => 'TRC20Buy', 'amount' => '299.99',
    'extra'        => ['fiat_currency' => 'USD'],
]);
printf("http=%s msg=%s params=%s\n", $http, (string) ($body['message'] ?? ''),
    mb_substr((string) ($body['params'] ?? ''), 0, 400));
PHPEOF
php /tmp/starpay_check2.php
'''



def main():
    action = sys.argv[1] if len(sys.argv) > 1 else "all"
    buf = open(LOG, "w", encoding="utf-8", buffering=1)
    try:
        cli = connect()
        sftp = cli.open_sftp()
        if action == "file":
            # 只传指定文件：python .trae/deploy_starpay_172.py file ai-girl-malaysia.com/Subscriptions.html
            for rel in sys.argv[2:]:
                upload_file(sftp, buf, rel, REMOTE + "/" + rel, ".bak_starpay_" + STAMP)
        elif action == "probe":
            run(cli, buf, "服务器环境探测", PROBE_STEP, timeout=120)
        elif action == "gateway":
            run(cli, buf, "star-pay 网关联调", GATEWAY_STEP, timeout=300)
        elif action == "e2e":
            run(cli, buf, "端到端下单+回调联调", E2E_STEP, timeout=300)
        elif action == "vip":
            run(cli, buf, "订阅支付联调（下单+回调+幂等+续订）", VIP_E2E_STEP, timeout=300)
        elif action == "tier":
            run(cli, buf, "会员分层联调（额度/折扣/层级）", TIER_E2E_STEP, timeout=300)
        elif action == "url":
            run(cli, buf, "公网路径与 nginx 配置探测", URL_STEP, timeout=180)
        elif action == "deposit":
            run(cli, buf, "地址类产品试单（确认收款地址与 payurl）", DEPOSIT_STEP, timeout=180)
        elif action == "sh":
            cmd = sys.argv[2] if len(sys.argv) > 2 else "echo hi"
            run(cli, buf, "执行命令（诊断用）", cmd + "\n", timeout=180)
        elif action == "sqlrun":
            sql = sys.argv[2] if len(sys.argv) > 2 else "SELECT 1;"
            run(cli, buf, "执行 SQL（临时，慎用）",
                DB_ENV + "cat > /tmp/_sqlrun.sql <<'SQLEOF'\n" + sql + "\nSQLEOF\n"
                + 'mysql -h"$DBH" -u"$DBU" "$DBN" < /tmp/_sqlrun.sql\n', timeout=120)
        elif action == "cron":
            run(cli, buf, "链上扫描定时任务（recharge:scan / recharge:scan-eth）", CRON_STEP, timeout=120)
        elif action == "products":
            run(cli, buf, "网关产品能力探测（是否支持 ETH 等）", PRODUCT_PROBE_STEP, timeout=180)
        elif action == "token":
            uid = sys.argv[2] if len(sys.argv) > 2 else "2"
            run(cli, buf, "生成测试用户 %s 的登录 token" % uid, TOKEN_STEP.replace("__UID__", uid), timeout=120)
        elif action == "cleanvip":
            run(cli, buf, "清理订阅联调残留", CLEANVIP_STEP, timeout=120)
        elif action == "shell":
            for rel in ["ai-girl-malaysia.com/assets/fy.css",
                        "ai-girl-malaysia.com/js/shell-new.js",
                        "ai-girl-malaysia.com/js/main.js"] + shell_pages():
                upload_file(sftp, buf, rel, REMOTE + "/" + rel, ".bak_shell_" + STAMP)
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
            if action in ("all", "assets"):
                upload_dir(sftp, buf, "ai-girl-malaysia.com/taocan_files", SITE + "/taocan_files")
            if action in ("all", "env"):
                ensure_env(sftp, buf, cli)
            if action in ("all", "sql"):
                run(cli, buf, "数据库迁移", SQL_STEP, timeout=300)
            if action in ("all", "min"):
                run(cli, buf, "最低档改价（对齐网关最小金额）", MIN_AMOUNT_STEP, timeout=120)
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
