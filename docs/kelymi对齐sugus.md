# kelymi 对齐 sugus（2026-10-21 实战记录）

> 起因：角色素材要同步到 4 个站点时发现 kelymi 素材面板是空的。排查后确认
> kelymi 的 `php/` 停在 `bae4855`（10-18 基线），落后上游 59 个文件，
> 不止缺 media，还缺 GenCredit、后台安全、MKPay、观看历史等整段演进。
>
> 本文是这一次「把落后客户站整体追平」的完整记录，可作为以后新客户站或
> 落后站的升级模板。

---

## 一、先看差距（不要凭猜）

三个维度分别比对，才不会被「看起来一样」骗：

```bash
# 1) PHP 文件级差异（内容 + 缺失）
git -C D:/ever/douyin/douyin diff --name-status bae4855..HEAD -- php/

# 2) 服务器 vs HEAD 的实际 md5（服务器可能被手改过）
#    用 git cat-file 循环算 blob md5，再和服务器 find|md5sum 对撞

# 3) DB 结构 + 配置项
#    表：show tables；字段：information_schema.columns；配置：lp_platform_config
```

本次结果：

| 层面 | 缺失 |
|---|---|
| PHP | 缺 22 个新文件，30 个文件内容不同 |
| 表 | `lp_user_gen_credit`、`lp_admin_ip_whitelist`、`lp_user_watch_history` |
| 字段 | `ba_admin.totp_*`、`lp_vip_plan.image_daily_refill` |
| 配置项 | `ai_register_email_verify`、`admin_ip_whitelist_enabled`、`popup_guide_*` |
| .env | 无 `[MEDIA]`、无 `[MKPAY*]` |
| nginx | `/storage/` 直开、无媒体域名 |

---

## 二、关键判断：代码可以先上，因为功能默认关闭

`Media::enabled()` 需要 `media.enabled && endpoint && sign_secret` 三者齐全；
`.env` 没有 `[MEDIA]` 时全部为 false → 所有 media 逻辑走原来的本地分支。

**所以「先推代码、后配 .env」是安全的**，站点行为零变化。这条适用于所有
带开关的新功能：先确认默认关，再推代码。

---

## 三、执行顺序（任何一步失败都能原地回退）

```
0 备份 → 1 代码 → 2 SQL → 3 .env(全 0) → 4 迁素材 → 5 开 ENABLED
      → 6 CDN 签名 6 项验证 → 7 封本地 /storage → 8 验收 → 9 仓库跟进
```

### 0. 备份

```bash
BAK=/root/kelymi_up_$(date +%Y%m%d_%H%M)
mkdir -p $BAK/etc_nginx
cd /www/wwwroot/douyin/php
cp -a .env app config route public/index.php $BAK/
cp -a /etc/nginx/sites-available/zhibo.conf $BAK/etc_nginx/
find . -type f \( -name '*.php' -o -name '*.json' \) | grep -v '/vendor/\|/runtime/' \
  | sort | xargs md5sum > $BAK/php_md5_before.txt
mysqldump -uroot -pPASS --single-transaction --routines --events DB > $BAK/db.sql
```

必写 `rollback.sh`：关 MEDIA → 恢复 php/config/route/.env → 恢复 nginx → reload。
本地仓库同时 `git tag pre-upgrade-<date>`。

### 1. 代码：逐文件推，不要整目录 checkout

**不要** `git checkout douyin/main -- php/`：会覆盖站点特化文件
（`config/buildadmin.php` 的 CORS 白名单、`config/starpay.php` / `flowpay.php`
的商户号占位符）。本次排除清单：

```
php/config/buildadmin.php
php/config/starpay.php
php/config/flowpay.php
php/web/**  php/public/static/**  php/public/install/**
```

推送用 `git show HEAD:<path>` 取 blob 再 `sftp.putfo`，避免 CRLF 和编码问题。
推完对每个 `.php` 跑 `php -l`，零错误才 reload。

### 2. SQL：用 mysql CLI，不要用 PHP+PDO 切分器

本批脚本含 `DELIMITER $$ ... CREATE PROCEDURE`，PDO 按 `;` 切分会失败。
直接用：

```bash
MYSQL_PWD=PASS mysql -uUSER DB < php/sql/upgrade_xxx.sql
```

本次按序执行（都是幂等的）：

```
upgrade_admin_security.sql      # 建 lp_admin_ip_whitelist、ba_admin.totp_*
upgrade_gen_credit.sql          # 建 lp_user_gen_credit、image_daily_refill
upgrade_popup_settings.sql      # popup_* 配置 + 菜单
upgrade_vip_order_admin.sql     # 会员订阅记录菜单
upgrade_register_email_verify.sql
upgrade_watch_history.sql       # 建 lp_user_watch_history
upgrade_chat_scene_media.sql    # media_kind → varchar(32)
upgrade_live_user_soft_delete.sql
upgrade_private_item_order.sql
upgrade_user_last_active.sql
upgrade_user_reg_channel_name.sql
upgrade_user_reg_link.sql
upgrade_promo_link.sql
```

再统一 VIP 套餐额度（本次业务规则：会员 5 次/天回满）：

```sql
UPDATE lp_vip_plan SET image_daily_limit = 5, image_daily_refill = 5;
```

### 3. .env（先全 0）

```ini
[MEDIA]
ENABLED = 0
STORAGE_ENABLED = 0
ENDPOINT = https://media.<客户域名>
PROJECT = <客户前缀>
SIGN_SECRET = <与存储服务器 secure_link 一致>
S3_ENDPOINT = https://155-103-194-69.sslip.io/s3
BUCKET = sugus-media
ACCESS_KEY / SECRET_KEY / GATEWAY_KEY = <同 sugus>
```

`PROJECT` 必须是新前缀，不要沿用 sugus 默认值（`config/media.php` 的默认是
`sugus`，漏配会写进 sugus 的目录）。

### 4. 迁素材

```bash
php think media:migrate                       # 预演，核对文件数与体积
php think media:migrate --run --dir=<子目录>   # 逐目录跑，4 路并行
```

- **排除** `private` / `private_shorts` / `certification`（付费私密内容与证件照
  不进共享桶）
- **不删本地**（`--delete` 一律不加），本地是回滚保险
- 本次 kelymi：17 目录 → `sec/kelymi` 1333 对象 3.9GiB + `pub/kelymi` 193 对象 227MiB
- 并行脚本要点：`while [ "$(jobs -rp | wc -l)" -ge 4 ]; do sleep 2; done` 配合
  `one() { php think ...; }` —— shell 函数名不能带括号参数，这是踩过的坑

### 5. 开 ENABLED

**先确认 CDN 域名证书已签发**，否则开了立刻全站裂图（图片指向一个 TLS 失败的域名）。

```bash
openssl s_client -connect media.<域名>:443 -servername media.<域名> 2>&1 \
  | grep -E 'subject=|Verify return code'      # 必须是 CN=media.<域名> + code 0
```

确认后再 `ENABLED = 1` / `STORAGE_ENABLED = 1`，reload。

### 6. CDN 签名 6 项验证

| 用例 | 期望 |
|---|---|
| 正确签名 | 200 |
| 伪造签名 `s=AAAA` | 403 |
| 裸路径（去掉 query） | 403 |
| 过期（`e` 在过去但签名正确） | **410** |
| Range `bytes=0-2048` | 206 + `content-range` |
| 同 URL 第二次 | `cdn-cache: HIT` |

再补一条「query 是否进缓存键」：过期 URL 与伪造 URL 都应是 **MISS**
（若 HIT，说明 Bunny 没把 query 计入缓存键，签名可绕过）。

### 7. 封本地 /storage

```bash
sed -i -E 's#location \^~ /storage/ \{ alias /www/wwwroot/douyin/php/public/storage/[^}]*\}#location ^~ /storage/ { return 403; }#' $CONF
nginx -t && systemctl reload nginx
```

kelymi 有 3 处（80 跳转 server、443 主站、127.0.0.1 内部 server）。

### 8. 验收

```bash
# 页面全 200
for p in Home Chat Image Girls shorts Gallery Login; do curl -o /dev/null -w "$p=%{http_code}\n" ...; done
# 首屏接口裸 /storage 计数必须为 0
# 礼物面板 11 个 + 图标全 200 + mp4 Range 206
# chatMedia（动漫角色）images/videos/persona 全 200
# 额度：Free 2/+1、VIP 5/回满
python .zcode/verify_client.py --client <客户>
```

### 9. 仓库跟进

```bash
cd D:\ever\<客户>
git fetch douyin
git checkout douyin/main -- <本次对齐的 74 个文件>
git add -A php/ && git commit -m "sync(upstream): php 侧对齐 ..."
git push origin master
```

站点特化文件不要 checkout。

---

## 四、这次踩的坑

| 坑 | 现象 | 解决 |
|---|---|---|
| DNS 两个字段填反 | 权威 NS 出现 `kelymi-media.b-cdn.net.kelymi.autos → media` | 子域填 `media`、目的地填 `kelymi-media.b-cdn.net` |
| Bunny 未加 Hostname | `subject: CN=*.b-cdn.net`，`altname does not match` | Pull Zone → Hostnames 添加 `media.<域>`，触发起证书 |
| PDO 切分器不认 DELIMITER | `Syntax error ... DELIMITER $$` | 改用 `mysql < file.sql` |
| shell 函数定义带括号 | `syntax error near unexpected token '('` | `one() { ...; }` 不能写成 `run x() { ...; }` |
| sed 未锚定 `ENABLED = 0` | 把注释里的同串也替换了 | 用 `^ENABLED = ` 锚定行首 |
| 本地 DNS 被沙箱拦 | 本机 curl 全部 000 | 一律在服务器上验证解析与 TLS |
| 站点落后整版本 | 只同步素材 → 面板空 / 覆盖 Live.php → 500 | **素材同步前先探目标站代码基线** |

---

## 五、回滚

| 场景 | 动作 |
|---|---|
| 代码 500 / 白屏 | 跑 `/root/kelymi_up_*/rollback.sh` |
| 素材异常 | `.env` 改 `ENABLED = 0` 即回到本地存储（本地文件全程保留） |
| CDN 未就绪 | 不执行第 7 步，`/storage/` 保持开放 |
| 数据库 | `mysql DB < /root/kelymi_up_*/db.sql` |
