# kiss 服务器重建计划（2026-11-09，旧机数据不可恢复）

> 背景：原 kiss 服务器 `103.217.252.52`（荷兰 Amarutu / HostUS）自 2026-11-03 起彻底失联，
> 2026-11-09 确认宿主机故障、**磁盘数据无法恢复**。本文是按此前提设计的一次性重建流程。
>
> 目标：在新服务器上重建 kiss 全栈（静态站 + PHP 后端 + 直播推流 + CDN + 支付），
> 并保证四站同步体系（`sync_clients.py` / `apply_brand.sh`）继续可用。

## 新服务器信息（2026-11-10 已确认）

| 项 | 值 |
|---|---|
| **主 IP** | `111.90.150.230` |
| **SSH 端口** | `20203`（**非标准**，脚本里须显式指定，默认 22 连不上） |
| **用户名** | `root` |
| **密码** | `***REMOVED-SSH-PW***` |
| 系统 | Ubuntu 22.04（内核 5.15） |
| 站点根 | `/www/wwwroot/douyin`（已存在） |

```bash
ssh -p 20203 root@111.90.150.230
```

> **凭据同步位置**（改机器时这些地方都要一起改）：
> `.zcode/sync_clients.py`（kiss 配置含 `"port": 20203`）、`.zcode/add_userinfo_ratelimit.py`、
> `.zcode/tune_userinfo_burst.py`、`.zcode/fix_userinfo_loop.py`、`.zcode/fix_task_media_urls.py`、
> `.zcode/php_security_patch.py`、`.zcode/gpumig/deploy_php.py`、`.zcode/_cnblock/apply_clients.py`、
> `.zcode/uptimerobot_setup.py`

---

## 一、损失清单与可复用资产

### 1.1 彻底丢失（必须重建）

| 项 | 说明 | 影响 |
|---|---|---|
| **MySQL 数据库 `live_platform`** | 用户、订单、余额、角色绑定、房间配置、播单模板 | 用户需重新注册；历史订单/余额归零 |
| **`php/.env`** | 全部密钥：DB 密码、支付商户号、LLM key、JWT secret | 需重新填写（部分可从文档/SOP 找回） |
| **`/data/media` 直播素材** | 626 个视频 / 21 个角色目录 / 2.4 GB | 从 sugus 同步 |
| **nginx / systemd / 脚本配置** | `kiss.conf`、`kiss-cdn-origin.conf`、`cn-geo`、`live-worker@.service` 等 | 照 SOP 重写 |

### 1.2 完好可复用（不丢）

| 项 | 位置 | 说明 |
|---|---|---|
| **kiss 网站代码** | gitee `kisss.git`（5762 文件） | 含站点、php、brand kit、apply_brand.sh |
| **品牌 kit** | 仓库 `brand/kiss/` + `tools/apply_brand.sh` | 含 `cdn_assets.env`（`CDN_ASSET_BASE=https://kisss-live.b-cdn.net/ai`） |
| **素材（图片/礼物/短剧/私密）** | `media.kisss.ai` → `kisss-media.b-cdn.net` | **独立存储服务器，未受影响**，实测 200 |
| **CDN zone: kisss-live** | Bunny pullzone `6840502` | 仅 Origin 需改到新 IP；现返回 504（源站已死） |
| **CDN zone: kisss-media** | Bunny pullzone `6834857` | 不动 |
| **sugus 数据库** | 98.55 `zhibo` 库 | 108 表 / 40.4 MB / 350 用户 / 191 角色 / 14 房间 / 1243 素材 |
| **sugus 直播素材** | 99.11 `/data/media` | 2.4 GB / 626 视频 / 21 角色目录 |
| **配置模板** | heygirl / kelymi 服务器 | nginx、systemd、realip、CN 封锁 全套 |
| **MKPay 商户号** | `docs/MKPay对接说明.md` | `GATEWAY=https://br.mkpayeasy.com`、`APPID=697146150936` |
| **HissPay 产品/APP_ID** | `docs/多客户同步SOP.md:390` | `APP_ID=PzRlHNO`、`PRODUCT=USD-YKMZXQKYCA`、`PAY_TYPE=CARD`、48 档金额 |
| **MEDIA SIGN_SECRET** | `live-worker@.service` 记录 | `c241ca0743ae79aa688abc9bf58a39ee465e37f299c1f81438c384a80e22d65e` |
| **JWT secret** | `php/config/jwt.php` 默认值 | `live-platform-jwt-secret-key-2026` |

---

## 二、重建前必须准备好的信息（阻塞项）

> **没有这些无法开工。** 建议在买服务器前先确认，避免买了机器干等。

| # | 需要什么 | 从哪来 | 阻塞阶段 |
|---|---|---|---|
| 1 | 新服务器 **IP + root 密码** | 你购买后提供 | 全部 |
| 2 | **HissPay `APP_SECRET`** | HissPay 商户后台 | 阶段 5（支付） |
| 3 | **MKPay `SIGNKEY`** | MKPay 商户后台（登录名 `sugusai`） | 阶段 5 |
| 4 | StarPay `MERCHANT_NO` + `KEY` | 若 kiss 用 StarPay，从商户后台取 | 阶段 5 |
| 5 | FlowPay `MCHID` + `KEY` | 若 kiss 用 FlowPay | 阶段 5 |
| 6 | **LLM API key**（xAI `grok-4.5`） | 与 sugus 同一把 key | 阶段 5（AI 聊天） |
| 7 | 数据库策略确认：**用 sugus 数据克隆** 还是 **全新空库** | 需你决策 | 阶段 3 |

> **关于第 7 项**：我建议**克隆 sugus 库**（350 用户 / 191 角色 / 1243 素材资产），
> 然后按 kiss 需要调整角色和房间。理由：全新空库意味着 0 用户、0 角色，站点等于重开。
> 但要注意——**sugus 的用户数据会带到 kiss**，如果两家客户不该共享用户数据，那就必须空库。

---

## 三、阶段划分与预计耗时

| 阶段 | 内容 | 预计耗时 | 可否并行 |
|---|---|---|---|
| **0** | 买机 + 收密钥 | 你操作 | — |
| **1** | 系统装机（nginx/php/mysql/redis/composer/docker） | 45 min | — |
| **2** | 代码部署（git clone + vendor + 前端资源） | 30 min | 与 3 并行 |
| **3** | 数据库重建（sugus 导出 → kiss 导入 → 改配置） | 40 min | 与 2 并行 |
| **4** | 直播素材同步（sugus → kiss，2.4G） | 20-60 min（看跨境速度） | 与 3 并行 |
| **5** | `.env` 重建（所有段） | 30 min | 依赖密钥 |
| **6** | nginx + systemd + S3/媒体配置 | 60 min | — |
| **7** | CDN 切换（改 Origin → 验证） | 20 min | 依赖 6 |
| **8** | 支付重新接（网关后台 + 回调测试） | 40 min | 依赖 5 |
| **9** | 全站验证（4 类检查） | 40 min | — |

**合计约 5-6 小时**（不含你准备密钥的时间）。若素材跨境传输慢，可能延长 1-2 小时。

---

## 四、阶段 0：前置准备（你来做）

### 4.1 买服务器

推荐配置（实测依据）：

```
CPU:  4 核起步，6-8 核更稳（10 个 ffmpeg 预转并发）
内存: 8 GB 最小
磁盘: 100 GB+ SSD/NVMe（现 kiss 用 35G，留余量）
带宽: ≥30 Mbps 持续，最好 100 Mbps（CDN 回源需求）
系统: Ubuntu 22.04 LTS
```

### 4.2 域名与 DNS

- `kisss.ai` 的 A 记录 → 新 IP（**等数据就绪再切，别提前切**）
- `media.kisss.ai` 不动（CNAME 到 `kisss-media.b-cdn.net`）

### 4.3 给我这些

```
1. 新服务器 IP:
2. root 密码:
3. 是否复用 sugus 的数据库数据（是/否）:
4. 支付网关密钥（HissPay APP_SECRET / MKPay SIGNKEY / 其他）:
5. LLM API key:
```

---

## 五、阶段 1：系统装机

```bash
# 1) 基础包
apt update && apt install -y nginx mysql-server redis-server \
  php8.3-cli php8.3-fpm php8.3-mysql php8.3-curl php8.3-mbstring \
  php8.3-xml php8.3-zip php8.3-gd php8.3-bcmath php8.3-gmp php8.3-redis \
  git unzip curl ffmpeg python3 python3-pip docker.io

# 2) composer
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# 3) 时区
timedatectl set-timezone Asia/Shanghai

# 4) 关键：/tmp 权限（SOP 踩坑：755 root:root 会让 PHP-FPM/MySQL 写不了临时文件）
chmod 1777 /tmp
```

> ⚠️ **`php8.3-gmp` 必装**：缺它会导致 composer 解析 `kornrunner/secp256k1` 失败
> （SOP 记录：kiss 10-19 装机踩过）。

---

## 六、阶段 2：代码部署

```bash
# 1) 克隆 kiss 客户仓库（含 php / 站点 / brand kit）
mkdir -p /www/wwwroot && cd /www/wwwroot
git clone git@gitee.com:autumn_birds_in_the_moon_sea/kisss.git douyin
cd douyin

# 2) PHP 依赖
cd php && composer install --no-dev --optimize-autoloader && cd ..

# 3) Webman（WebSocket 服务）
cd apps/ws-webman && composer install --no-dev && cd ../..

# 4) 后台管理端（admin/dist）
#    从 heygirl 或 kelymi 打包迁移（同源，约 5.3 MB）
#    rsync -a --ignore-existing root@172.81.99.11:/www/wwwroot/douyin/admin/ /www/wwwroot/douyin/admin/

# 5) 前端 Vue（vue/dist，直播首页）
#    rsync -a root@172.81.99.11:/www/wwwroot/douyin/vue/dist/ /www/wwwroot/douyin/vue/dist/
```

> **注意**：`php/vendor`、`vue/dist`、`admin/dist` 不在 git 里（`.gitignore`），
> 必须补。`composer install` 可能因网络慢，建议加国内镜像或走代理。

---

## 七、阶段 3：数据库重建

### 7.0 【最终确认方案】sugus 内容基线 + 清空用户数据

> **这是已确认采用的方案。** 保留 sugus 的角色人设、素材、房间、播单、短剧、礼物、
> VIP 套餐、充值档位等**内容配置**，清空全部**用户数据**（用户/钱包/订单/聊天/观看记录）。
>
> 执行脚本：**`docs/sql/kiss_建库清空用户.sql`**（含分类明细与安全校验）

```bash
# 1) sugus 导全量（结构 + 内容 + 用户数据）
ssh -i /root/.ssh/n99_pull root@172.81.98.55   'mysqldump --single-transaction --routines --triggers --default-character-set=utf8mb4 live_platform'   > /tmp/live_platform_sugus.sql

# 2) 传到新机
scp /tmp/live_platform_sugus.sql root@<新IP>:/tmp/

# 3) 建库并导入
mysql -uroot -p -e "CREATE DATABASE IF NOT EXISTS live_platform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -uroot -p live_platform < /tmp/live_platform_sugus.sql

# 4) 跑清空脚本（保留内容 / 清空用户）
scp docs/sql/kiss_建库清空用户.sql root@<新IP>:/tmp/
mysql -uroot -p live_platform < /tmp/kiss_建库清空用户.sql

# 5) 看脚本末尾的两组校验输出：
#    「清空后应为 0」全部 0、「保留项应 >0」全部 >0 才算成功
```

**清空 vs 保留的边界（摘要，详见 SQL 脚本附录）：**

| 类别 | 代表表 | 处理 |
|---|---|---|
| **保留·内容** | `lp_persona`(191 角色)、`lp_media_asset`(1264 素材)、`lp_room`(13)、`lp_room_binding`(12)、`lp_playlist_template`(29)+item(381)、`lp_short_*`、`lp_gift*`、`lp_home_*` | 不动 |
| **保留·配置（改值）** | `lp_platform_config`(60)、`lp_vip_plan`(24)、`lp_recharge_package`(6)、`lp_fiat_channel`(5)、`lp_recharge_channel`(7) | 保留行，**值改成 kiss 的** |
| **清空·用户** | `lp_user`(350)、`lp_user_auth`(615)、`lp_wallet_*`、`lp_recharge_order`(266)、`lp_ai_chat_message`(2112)、`lp_room_online_minute`(154636) 等 ~24 万行 | TRUNCATE |

**三个必须手动改的「钱相关」配置（清空脚本只清空不重建）：**

| 表 | 为什么要改 | 不改的后果 |
|---|---|---|
| `lp_promo_pixel` | sugus 的 FB/TikTok 像素 ID | kiss 的访客数据发到 **sugus 的广告账户** |
| `lp_recharge_channel` | sugus 的 USDT 收款地址 | 用户打款进 **sugus 的钱包**，钱要不回来 |
| `lp_fiat_channel` | sugus 的网关商户配置 | 支付回调打到 sugus，kiss 订单永不确认 |

> `lp_promo_pixel` 脚本里已 TRUNCATE；后两个脚本里注释掉了删除语句，
> 建议**保留行、只改值**，避免前台付款方式列表变空。

### 7.1 备选方案 A：克隆 sugus 全量（含用户，一般不采用）

```bash
# 在 sugus（98.55）导出
ssh -i /root/.ssh/n99_pull root@172.81.98.55 \
  'mysqldump --single-transaction --routines --triggers live_platform > /tmp/live_platform.sql'

# 拉到本地再推到新机（香港/日本之间走 CDN 或直连）
scp root@172.81.98.55:/tmp/live_platform.sql /tmp/
scp /tmp/live_platform.sql root@<新IP>:/tmp/

# 新机导入
mysql -uroot -p -e "CREATE DATABASE live_platform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -uroot -p live_platform < /tmp/live_platform.sql
```

### 7.2 方案 B：全新空库（若不能共享用户数据）

```bash
# 用 sugus 的结构（不要数据）
mysqldump --no-data --routines --triggers live_platform > /tmp/schema.sql

# 加上仓库里的种子数据
cat php/sql/upgrade_*.sql >> /tmp/seed.sql   # 按顺序
# 具体顺序见 docs/kelymi对齐sugus.md

# 导入
mysql -uroot -p live_platform < /tmp/schema.sql
mysql -uroot -p live_platform < /tmp/seed.sql
```

### 7.3 创建 DB 账号（不要用 root）

```sql
CREATE USER 'live_platform'@'127.0.0.1' IDENTIFIED BY '<强密码>';
GRANT ALL PRIVILEGES ON live_platform.* TO 'live_platform'@'127.0.0.1';
FLUSH PRIVILEGES;
```

### 7.4 补齐 kiss 特有配置行

若走方案 B（空库），必须补：

```sql
-- 后台配置项（缺失会导致 500）
INSERT INTO lp_platform_config (`key`, value) VALUES
  ('site_home_page', ''),
  ('ai_register_email_verify', '1'),
  ('image_daily_limit', '5'),
  ('image_daily_refill', '5');

-- HissPay 法币通道（SOP 记录 id=14）
INSERT INTO lp_fiat_channel (id, gateway, currency, decimals, status, gateway_ref)
VALUES (14, 'hisspay', 'USD', 2, 1, 'USD-YKMZXQKYCA');
```

> **法币通道行也必须重建**，否则前台看不到 HissPay 支付方式。

---

## 八、阶段 4：直播素材同步

```bash
# 从 sugus（99.11）拉到本地再推到新机；或三机互通直传
# 素材在 99.11 的 /data/media（2.4G / 626 视频 / 21 角色目录）

# 方案 1：99.11 → 新机 直传（若网络可达）
ssh root@172.81.99.11 \
  'tar -cf - /data/media | ssh root@<新IP> "tar -xf - -C /"'

# 方案 2：经本地中转（慢，但稳）
rsync -avz --progress root@172.81.99.11:/data/media/ /tmp/media/
rsync -avz --progress /tmp/media/ root@<新IP>:/data/media/
```

同时准备：

```bash
mkdir -p /data/hls /data/runtime/ts_cache /data/srs
# 从 heygirl 或 kelymi 拷 SRS 配置
scp root@172.81.99.11:/data/srs/srs.conf /data/srs/
```

---

## 九、阶段 5：`.env` 重建

这是最需要小心的一步。完整结构（对照 sugus + kelymi 的实际 .env）：

```ini
APP_DEBUG = false
[APP]
DEFAULT_TIMEZONE = Asia/Shanghai
[LANG]
default_lang = zh-cn
[DATABASE]
TYPE = mysql
HOSTNAME = 127.0.0.1
DATABASE = live_platform
USERNAME = live_platform
PASSWORD = <阶段 7.3 设置的密码>
HOSTPORT = 3306
PREFIX = ba_
CHARSET = utf8mb4
DEBUG = false
[REDIS]
HOST = 127.0.0.1
PORT = 6379
PASSWORD =
SELECT = 0
[LLM]
API_KEY = <你的 xAI key>
BASE_URL = https://api.x.ai/v1
MODEL = grok-4.5
TEMPERATURE = 0.8
MAX_TOKENS = 2048
TIMEOUT = 60
[AI]
HLS_CDN_DOMAIN = https://kisss-live.b-cdn.net
WHEP_ENABLED = 0
MEDIAMTX_WHEP_BASE = http://127.0.0.1:8889
[MEDIA]
ENABLED = 1
STORAGE_ENABLED = 1
ENDPOINT = https://media.kisss.ai
PROJECT = kiss
SIGN_SECRET = c241ca0743ae79aa688abc9bf58a39ee465e37f299c1f81438c384a80e22d65e
SIGN_TTL = 691200
SIGN_BUCKET_SEC = 604800
BUCKET = sugus-media
REGION = us-east-1
ACCESS_KEY = minioadmin
SECRET_KEY = <向存储服务器要>
GATEWAY_KEY = <向存储服务器要>
[HISSPAY]
APP_ID = PzRlHNO
APP_SECRET = <你的>
NOTIFY_URL = https://kisss.ai/api/v1/recharge/notifyHissPay
RETURN_URL = https://kisss.ai/ai/purchasePoints.html
RETURN_URL_VIP = https://kisss.ai/ai/Subscriptions.html
CURRENCY = USD
PRODUCT = USD-YKMZXQKYCA
PAY_TYPE = CARD
AMOUNTS = <48 档，见旧 SOP 记录>
ORDER_PREFIX = HSP
[MKPAY]
GATEWAY = https://br.mkpayeasy.com
APPID = 697146150936
SIGNKEY = <你的>
NOTIFY_URL = https://kisss.ai/api/v1/recharge/notifyMkPay
ORDER_PREFIX = MK
[STARPAY] / [FLOWPAY]
(若 kiss 用，按 sugus 结构填)
```

> **`php/config/jwt.php` 的 secret**：代码里有默认值
> `live-platform-jwt-secret-key-2026`，**必须与 `apps/ws-webman/config/jwt.php` 一致**，
> 否则 WebSocket 鉴权失败（SOP 记录：两边各自 decode，只共享密钥）。

---

## 十、阶段 6：nginx + systemd

### 6.1 必须重建的 nginx 文件（照 heygirl/kelymi 改）

| 文件 | 来源 | 改动 |
|---|---|---|
| `/etc/nginx/sites-available/kiss.conf` | heygirl `zhibo.conf` | 域名改 `kisss.ai`，站点路径 |
| `/etc/nginx/sites-available/kiss-cdn-origin.conf` | kiss 旧版（SOP 有记录） | 回源口 `:8080` |
| `/etc/nginx/conf.d/00-cn-geo.conf` | heygirl 同名 | 整站 CN 封锁 |
| `/etc/nginx/snippets/cn-block-site.conf` | heygirl 同名 | — |
| `/etc/nginx/conf.d/00-bunny-realip.conf` | heygirl 同名 | — |
| `/etc/nginx/conf.d/zz-perf.conf` | heygirl 同名 | **但 HLS location 必须加 `open_file_cache off`** |

### 6.2 关键配置点（每一条都是踩过的坑）

```nginx
# ① HLS 必须关 open_file_cache（否则 m3u8 冻 60 秒）
location ~ ^/hls/(?<hls_path>.+\.m3u8)$ { open_file_cache off; alias /data/hls/$hls_path; ... }
location ~ ^/hls/(?<hls_path>.+\.ts)$   { open_file_cache off; alias /data/hls/$hls_path; ... }

# ② $ai_cache 分级缓存 map（否则 CDN 缓存不住静态资源）
map $uri $ai_cache {
    default                              'public, max-age=60, stale-while-revalidate=300';
    ~*\.(js|css)$                        'public, max-age=2592000, immutable';
    ~*\.(png|jpg|jpeg|webp|gif|svg|ico)$ 'public, max-age=604800';
    ~*\.(woff2?|ttf|eot|mp3)$            'public, max-age=2592000';
}

# ③ PHP location 必须带 PATH_INFO（否则 /api/* 全落到默认响应）
location ~ \.php(/|$) {
    fastcgi_split_path_info ^(.+\.php)(/.*)$;
    fastcgi_param PATH_INFO $fastcgi_path_info;
}

# ④ 80 端口是 Certbot default_server，HLS 回源要用 :8080
```

### 6.3 systemd 单元

```bash
# 从 heygirl 拷（内容几乎一致，只改描述和路径）
scp root@172.81.99.11:/etc/systemd/system/live-worker@.service /etc/systemd/system/
scp root@172.81.99.11:/etc/systemd/system/zhibo-ws.service /etc/systemd/system/kiss-ws.service

# 改 kiss-ws.service 的 Description + WorkingDirectory
systemctl daemon-reload

# 启用 10 个 worker（房间号与 sugus 对齐）
for r in 1 17 18 19 20 22 23 24 25 26; do systemctl enable live-worker@$r; done
```

> **`live-worker@.service` 的关键环境变量**：
> `PUSH_COPY=1` / `TS_SCALE=720:1280` / `TS_FPS=30` / `TS_CQ=24` /
> `TS_GOP=60`（GOP 优化，2026-10-21 加的）/ `MEDIA_ENDPOINT` / `MEDIA_SIGN_SECRET` /
> `MEDIA_PROJECT=kiss` / `MEDIA_STORAGE_ROOT`。
> **`worker.py` 用仓库最新版**（含 GOP + veryfast 优化）。

### 6.4 SSL 证书

```bash
apt install certbot python3-certbot-nginx
certbot --nginx -d kisss.ai -d www.kisss.ai
```

---

## 十一、阶段 7：CDN 切换

```bash
# 1) 确认新机回源口正常
curl -s -o /dev/null -w '%{http_code}\n' -H "Host: kisss.ai" http://127.0.0.1:8080/ai/Home.html
# 期望 200

# 2) Bunny 控制台：kisss-live (pullzone 6840502)
#    Origin URL: http://<新IP>:8080
#    （原来是 http://103.217.252.52:8080，只改 IP）

# 3) 验证 CDN 恢复
curl -sk -o /dev/null -w '%{http_code}\n' https://kisss.ai/ai/Home.html
curl -sk -o /dev/null -w '%{http_code}\n' https://kisss-live.b-cdn.net/ai/js/main.js
```

**kisss-media zone（6834857）不用动** —— 素材一直在正常服务。

---

## 十二、阶段 8：支付重新接

| 步骤 | 操作 |
|---|---|
| 1 | 各网关商户后台**更新回调白名单**（如果配了源站 IP）→ 改成新 IP |
| 2 | `.env` 填好各行密钥 |
| 3 | 确认 `lp_fiat_channel` 里 HissPay 行存在且 `status=1` |
| 4 | 实测下单：`HissPayService::createOrder` 返回 `payUrl` |
| 5 | 测回调：`curl -X POST https://kisss.ai/api/v1/recharge/notifyHissPay`（无效签名应返回 400 `FAIL`） |
| 6 | 前台确认：`/api/v1/recharge/fiat-channels` 的 `amounts` 含 48 档 |

> ⚠️ **最容易漏的是第 1 步**。HissPay / MKPay / StarPay 都可能配了源站 IP 白名单，
> 不更新的话用户付了钱、回调收不到、订单永远不确认。**必须逐个后台查**。

---

## 十三、阶段 9：验证清单

### 13.1 静态站（用 `verify_client.py`）

```bash
cd D:/ever/douyin/douyin
# 先把 sync_clients.py 里 kiss 的 host 改成新 IP
python .zcode/verify_client.py --client kiss
# 期望：全部 PASS
```

### 13.2 后端三证据（SOP 第九节）

```bash
# ① 代码标记
grep -c "siteConfig" php/app/api/controller/Live.php        # 应 >0
grep -c "31536000" php/app/live/service/JwtService.php      # JWT 1年

# ② 运行时接口
curl -s https://kisss.ai/api/v1/site/config
curl -s https://kisss.ai/api/live/siteConfig
curl -s https://kisss.ai/api/v1/rooms/23

# ③ 数据库值
mysql -e "select value from lp_platform_config where \`key\`='site_home_page'"
```

### 13.3 直播链路

```bash
# 1) 10 个房间都在推流
for d in /data/hls/room/*/; do ls -t $d*.ts 2>/dev/null | head -1; done

# 2) 分片 2 秒、m3u8 实时推进
curl -s https://kisss-live.b-cdn.net/hls/room/23/index.m3u8 | grep -E "TARGETDURATION|MEDIA-SEQUENCE"
sleep 5
curl -s https://kisss-live.b-cdn.net/hls/room/23/index.m3u8 | grep "MEDIA-SEQUENCE"
# 两次 sequence 必须不同

# 3) 码率
# 期望 0.5-2.5 Mbps（veryfast + GOP 优化后）
```

### 13.4 品牌与安全

```bash
curl -sk https://kisss.ai/ai/Home.html | grep -oE '<title>[^<]{0,40}'   # 应为 Kisss
curl -sk -o /dev/null -w '%{http_code}\n' https://kisss.ai/admin/       # 应 200（CN 豁免）
curl -sk -o /dev/null -w '%{http_code}\n' http://<新IP>:8080/admin/     # 应 404
```

### 13.5 四类业务流（SOP 强制）

- 注册新用户 → 能收到验证邮件
- 登录 → 拿到 token，刷新页面不掉登录
- 发一条聊天消息 → AI 有回复（验证 LLM 配置）
- 下一个 HissPay 订单 → 返回 payUrl

---

## 十四、阶段 10：收尾

```bash
# 1) 更新 sync_clients.py 里 kiss 的 host + 密码
#    D:\ever\douyin\douyin\.zcode\sync_clients.py  → CLIENTS["kiss"]

# 2) 重建快照基线（服务器已是最新时）
python .zcode/sync_clients.py --client kiss --init-snapshot

# 3) DNS 切到新 IP（确认一切正常后）

# 4) 删除 Bunny 里指向旧 IP 的任何配置残留

# 5) 更新 SOP 的速查表（新 IP、新密码）
```

---

## 十五、风险与注意事项

| 风险 | 说明 | 应对 |
|---|---|---|
| **用户数据归零** | 若走空库方案，350 个 sugus 用户不会自动到 kiss | 提前和客户确认是否接受 |
| **支付回调漏配 IP** | 用户付款但订单不确认 | 阶段 8 第 1 步逐个后台查 |
| **JWT secret 两处不一致** | WebSocket 鉴权失败，在线状态/直播互动失效 | `php/config/jwt.php` 与 `apps/ws-webman/config/jwt.php` 必须相同 |
| **`/tmp` 权限** | PHP 上传 500、MySQL UPDATE errno 13 | `chmod 1777 /tmp` |
| **`open_file_cache`** | HLS 冻 60 秒 | 所有 `/hls/` location 加 `open_file_cache off` |
| **`sites-enabled` 是实体文件** | 改 `sites-available` 不生效 | 先 `ls -l`，是 `-rw-` 就直接改它 |
| **vendor / dist 不在 git** | 站点 500 | 必须跑 composer install + 传 admin/vue dist |
| **成人内容政策（若换 Shinjiru）** | AUP 未明确 | 下单前要书面确认 |

---

## 十六、我能自动化的 vs 需要你做的

| 我能做 | 需要你做 |
|---|---|
| 阶段 1-6 全部（装机/代码/DB/素材/配置） | 买服务器、给 IP+密码 |
| 阶段 7 CDN 切换（给 Bunny API key 的话） | 或你在控制台改 Origin |
| 阶段 9 全部验证 | — |
| 阶段 10 仓库/SOP 更新 | — |
| — | 阶段 0 的 7 项密钥/决策 |
| — | 支付网关后台的 IP 白名单（需登录各商户后台） |

---

*文档建立于 2026-11-09。重建完成后请把实际耗时、踩到的新坑补回本文和 `多客户同步SOP.md`。*
