# 后端部署五步法（php / SQL / .env）

`sync_clients.py` **只覆盖静态站**。php 代码变了，静态同步完线上依然不生效。
本文是 2026-10-19 MKPay 支付接入实战提炼的流程。

## 前提：搞清楚这次动了什么

```bash
git diff --stat HEAD -- php/            # 改了哪些文件
git status --short -- php/ | grep '^??' # 新增了哪些（服务/配置/SQL/自测脚本）
```

三类东西分别处理：
- **代码文件** → 传到 `服务器:/www/wwwroot/douyin/php/` 对应相对路径
- **配置段**（如 `[MKPAY]`）→ 追加到 `php/.env`，**每台的值可能不同**（域名、订单前缀）
- **SQL 种子** → 每台库跑一次，必须幂等（`INSERT ... WHERE NOT EXISTS`）

## 五步

### ① 备份（回滚的唯一依靠）

```bash
ssh root@<host> 'mkdir -p /root/<feature>_bak_$(date +%Y%m%d) && cd /www/wwwroot/douyin/php && \
  cp -a --parents .env app/... config/... /root/<feature>_bak_$(date +%Y%m%d)/'
```

### ② 传代码

用 paramiko/sftp 逐文件传，保持 `php/` 下的相对路径。新增目录先建。
**不要整目录覆盖**（会把服务器的运行时文件带脏）。

### ③ 写 .env（幂等追加）

```bash
grep -q '^\[MKPAY\]' php/.env || cat >> php/.env <<'EOF'

[MKPAY]
GATEWAY = ...
APPID = ...
SIGNKEY = <商户后台重置获得，平台不保存>
NOTIFY_URL = https://<本站域名>/api/v1/recharge/notifyMkPay
ORDER_PREFIX = <本客户前缀>
EOF
```

要点：
- **敏感 key 不进仓库**（`.env` 不跟踪），只写在服务器上
- **多站共用密钥时靠前缀区分**：MKPay 的 sugus=`MKS` / heygirl=`MKH`，网关要求商户订单号全局唯一
- **每站回调域名必须换成该站自己的**

### ④ 跑 SQL 种子

不要用命令行嵌 mysql 密码（引号地狱 + 进程列表泄漏），用 PHP + PDO 读 `.env`：

```php
<?php
chdir('/www/wwwroot/douyin/php');
$e = parse_ini_file('.env', true, INI_SCANNER_RAW)['DATABASE'];
$pdo = new PDO("mysql:host={$e['HOSTNAME']};dbname={$e['DATABASE']};port={$e['HOSTPORT']};charset=utf8mb4",
               $e['USERNAME'], $e['PASSWORD'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
foreach (explode(';', file_get_contents($argv[1])) as $s) {
    $s = trim(preg_replace('/^--.*$/m', '', $s));
    if ($s) { $pdo->exec($s); }
}
// 回查确认
foreach ($pdo->query("SELECT id,name,gateway,status FROM lp_fiat_channel WHERE gateway='mkpay'") as $r) {
    echo implode(' | ', $r) . "\n";
}
```

### ⑤ 重启 + 自测

```bash
systemctl restart php8.3-fpm && systemctl is-active php8.3-fpm
cd /www/wwwroot/douyin/php && php _test_<feature>.php sign     # 算法自测（不需要真实 key）
php _test_<feature>.php balance                                 # 网关连通（需要真实 key）
curl -s -o /dev/null -w '%{http_code}' -X POST http://127.0.0.1/api/v1/recharge/notifyMkPay
```

最后回本地跑 `python .zcode/verify_client.py --client <客户> --payment` 复核。

## 客户仓库跟进（脚本不自动管 php）

`sync_clients.py` 的仓库跟进只对齐 `ai-girl-malaysia.com/`。php 部分手工：

```bash
cd D:\ever\heygirl
git fetch douyin
git checkout douyin/main -- php/<本次涉及的每个文件>
git add -A php/ && git commit -m "sync(upstream): <feature>——php 侧对齐"
git push origin master
```

**只对齐本次涉及的文件**。客户仓库 php/ 若与上游存在历史漂移（heygirl 2026-10 有 38 文件），
不要顺手全量对齐——那是需要单独决策的事，先问。

## 跳板客户（sugus）

98.55 只能从 99.11 跳过去（99.11 上有 `/root/.ssh/n99_pull`）：

```bash
# 本地 → 跳板暂存 → 打包 → scp 到目标 → 远端执行脚本
cd /tmp/mkpay_dep && tar czf /tmp/pkg.tgz app config _test_x.php
scp -i /root/.ssh/n99_pull pkg.tgz root@172.81.98.55:/tmp/
ssh -i /root/.ssh/n99_pull root@172.81.98.55 "bash /tmp/deploy.sh"
```

把部署步骤写成脚本传过去执行，比反复 ssh 拼单行命令可靠（也避开高频建连限流）。

## sugus 特别提醒

`if ($is_cn) return 403` —— 大陆 IP 访问会拿到「Service not available in your region」。
验证必须从服务器本机（`curl 127.0.0.1`）或境外出口发起，否则会把 403 页误判成「线上是旧内容」。
