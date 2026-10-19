# 接入 Bunny CDN（媒体素材分发）

> 目标：浏览器不再直连存储服务器 IP，素材全部经 Bunny 边缘分发；
> 源站只允许 Bunny 回源，拿到 CDN 的全球加速 + 带宽成本优势 + 源站隐藏。
>
> **前提已就绪**（本次已部署）：
> - 存储服务器 nginx 已信任 Bunny 边缘 IP（`/etc/nginx/bunny_realip.conf`，586 条，每月 1 号自动刷新）
> - `/sec/`、`/pub/` 已输出 `Cache-Control: public`（可被 CDN 缓存）
> - 已提供源站锁/解锁脚本（`/root/deploy/lock_media_origin.sh` / `unlock_media_origin.sh`）
> - **签名只算「路径 + 过期时间」，与域名无关** → 换 CDN 域名不需要重新签、不需要迁数据

---

## 一、控制台建 Pull Zone（约 5 分钟）

登录 https://dash.bunny.net → **CDN** → **Add Pull Zone**

| 字段 | 填什么 | 说明 |
|---|---|---|
| Pull Zone Name | `sugus-media` | 决定默认域名 `sugus-media.b-cdn.net` |
| **Origin Type** | `Origin URL` | |
| **Origin URL** | `https://155-103-194-69.sslip.io` | ★ 只填到根，**不要带 /pub 或 /sec 路径** |
| **Origin Host Header** | 留空 | 源站是 `server_name _` 兜底，任何 Host 都命中 |
| Storage Zone | 不选 | 这是 Pull Zone，不是 Storage Zone |
| **Pricing Zone** | `Europe and North America` 或 `Global` | 看用户分布；Phoenix 源站在美西，欧美区回源最快 |
| Type / 套餐 | `Standard` | |

建好后进 **Pull Zone → 设置**：

> Bunny 控制台改版较频繁，下面的字段名以当前 UI 为准；对不上时按"说明"里的
> **意图**找同类开关（例如 query string 相关一定在 Caching 区）。

### 1. Caching（缓存）—— 关键

| 设置项 | 值 | 理由 |
|---|---|---|
| **Cache Expiration Time**（缓存过期） | `0` / 留空 = 遵循源站 `Cache-Control` | 源站已给对：图片 7 天、`/sec/` 1 小时、403/410 不带缓存头 |
| **Query String 处理** | **必须让 query 参与缓存键**（"Cache every variation" / "Include query string in cache key"） | ★★ 最关键一条，见下方说明 |
| **Vary Header** | 关闭（源站已 `proxy_hide_header Vary`） | 避免缓存碎片 |
| **Strip Response Cookies** | 开 | 素材响应无 Cookie，开了能提高命中 |
| **Ignore Set-Cookie** | 开 | 同上 |
| **Stale While Revalidate** | 开（若有） | 边缘过期时先返回旧内容再异步刷新 |

> **为什么 query 必须进缓存键**：签名在 query 里（`?e=&s=`）。
> 若 Bunny 忽略 query 做缓存键，攻击者伪造签名也能命中缓存拿到内容 —— 签名层就废了。
>
> ⚠️ 这一条一定要在控制台点开确认，或用第四节 ④ 的"篡改签名应 403"实测。
> 如果篡改签名返回 200，说明 query 被忽略了，立刻改设置。

### 2. Performance / 加速

| 设置项 | 值 |
|---|---|
| **Origin Shield** | 开，区域选美西（Los Angeles / Seattle，离源站 Phoenix 最近） |
| **Smart Cache** | 开（默认） |
| **Compression** | 开（图片/文本；mp4 本身已压缩影响不大） |
| **HTTP/2 到源站** | 开 |
| **Request Coalescing** | 开（同一素材并发回源合并成一次） |

### 3. 安全

| 设置项 | 值 | 说明 |
|---|---|---|
| **Hotlink Protection** | **关** | 我们已经用签名防盗链，再叠一层会把带 query 的合法请求判为外链 |
| **Block Root Referrer** | 关 | |
| **Allowed Referrers** | 留空 | |
| **Force SSL** | 开 | |
| **Logging** | 开启（保留 3~7 天） | 排查 403/410 用 |

---

## 二、绑定自己的域名（强烈推荐）

默认域名 `xxx.b-cdn.net` 能用，但暴露了"这是 Bunny"；更重要是下面这套
`sslip.io` 域名把源站 IP 写在了 URL 里，等于告诉爬虫源站地址。

**做法**：Bunny 控制台 → Pull Zone → **Hostnames** → Add Custom Hostname

```
media.sugus.ai
```

1. 控制台会给出一个 CNAME 目标（形如 `sugus-media.b-cdn.net`）
2. 域名服务商加一条 CNAME：`media` → `sugus-media.b-cdn.net`（关掉 Cloudflare 橙云，必须 DNS only）
3. 在 Bunny 里点 **Add SSL Certificate** → Free Let's Encrypt（自动签发 + 续期）

> 如果暂时没有域名，直接用 `https://sugus-media.b-cdn.net` 也可以，
> 但建议后续一定换掉 —— 这是隐藏源站 IP 的关键一步。

---

## 三、切换项目（一条命令）

签名与域名无关，改 `.env` 即可，**不需要迁素材、不需要重新签、不影响进行中的播放**：

```bash
cd /www/wwwroot/douyin/php

# 1. 备份
cp .env .env.bak.bunny.$(date +%Y%m%d_%H%M%S)

# 2. 换对外地址（只改这一行；S3_ENDPOINT 保持指向源站，上传仍直连）
sed -i 's#^ENDPOINT = .*#ENDPOINT = https://media.sugus.ai#' .env

# 3. 清配置缓存
rm -rf runtime/*.php && systemctl reload php8.3-fpm

# 4. 验证接口已出 CDN 域名
curl -s 'http://127.0.0.1/api/v1/home/banners' | grep -o 'https://[^"]*' | head -2
```

> `S3_ENDPOINT` **不要改**：上传走的是源站写网关（`X-Media-Key`），
> 走 CDN 会把写请求也代理一遍，既没必要也无收益。

---

## 四、验证清单（按顺序跑，全过再锁源站）

```bash
# ① CDN 能回源 + 公开图正常
curl -sI "https://media.sugus.ai/pub/sugus/covers/cover_Celeste.jpg" | head -3
#    期望 HTTP/2 200，且响应头有 cdn-cache: HIT/MISS（Bunny 特有的 x-cache 类头）

# ② 私有资源无签名仍 403
curl -s -o /dev/null -w '%{http_code}\n' \
  "https://media.sugus.ai/sec/sugus/chat_media/home-1/1.jpg"            # 403

# ③ 带签名的 URL 能取到
u=$(curl -s 'http://127.0.0.1/api/live/chatGifts?role_key=home-1' \
    | grep -o 'https://[^"]*rose.jpg[^"]*' | head -1)
curl -s -o /dev/null -w '%{http_code}\n' "$u"                            # 200

# ④ 篡改签名仍 403（确认 CDN 没缓存 403 响应）
curl -s -o /dev/null -w '%{http_code}\n' "${u%??????}AAAAAA"            # 403

# ⑤ 视频 Range 正常（播放器拖进度条）
curl -sk -r 0-1023 -o /dev/null -D - "$u" | head -5                     # 206 + content-range

# ⑥ 二次请求命中缓存（第二次应明显更快，且 cdn-cache: HIT）
curl -s -o /dev/null -w 'first=%{time_total}s\n' \
  "https://media.sugus.ai/pub/sugus/covers/cover_Celeste.jpg"
curl -s -o /dev/null -w 'second=%{time_total}s\n' \
  "https://media.sugus.ai/pub/sugus/covers/cover_Celeste.jpg"
```

---

## 五、锁源站（全部验证通过后再做）

锁定后只有 **Bunny 边缘 IP + 项目机 `172.81.98.55` + 源站本机** 能访问，
其它任何机器直接打 IP 都会被 nginx 403。

> 脚本已经写好并在源站演练过一次（589→590 条 allow 规则，`nginx -t` 通过）。
> **当前是解锁状态**（`allow all`），因为 CDN 还没上线 —— 提前锁会让真实访客全部 403。

```bash
ssh root@155.103.194.69 /root/deploy/lock_media_origin.sh
```

脚本流程（带失败回滚）：

1. 读 `bunny_realip.conf` 里的 586 个边缘 IP → 生成 `allow` 规则
2. 追加 `172.81.98.55`、`127.0.0.1`、`::1`、源站自己的公网 IP
3. 备份旧 snippet → 替换 → `nginx -t`；校验失败自动回滚
4. `systemctl reload nginx`

**验证锁定生效**：

```bash
# ① 源站本机（走公网 IP 或回环）应 200
ssh root@155.103.194.69 \
  "curl -sk -o /dev/null -w '%{http_code}\n' https://155-103-194-69.sslip.io/pub/sugus/healthz.txt"
# 200

# ② 项目机 172 在白名单，也应 200（worker / 上传网关都靠它）
ssh root@172.81.98.55 \
  "curl -sk -o /dev/null -w '%{http_code}\n' https://155-103-194-69.sslip.io/pub/sugus/healthz.txt"
# 200

# ③ 任意第三方机器直连源站应 403（在你的本机跑）
curl -sk -o /dev/null -w '%{http_code}\n' https://155-103-194-69.sslip.io/pub/sugus/healthz.txt
# 403

# ④ CDN 访问仍 200
curl -s -o /dev/null -w '%{http_code}\n' https://media.sugus.ai/pub/sugus/healthz.txt
# 200
```

> ⚠️ **锁定顺序不能反**：先锁源站再切 ENDPOINT，项目机在锁定瞬间就拉不到素材。
> 正确顺序：建 CDN → 切 ENDPOINT → 验证 ①②③④ → 锁源站。

**应急解锁**（CDN 故障 / 边缘 IP 变更导致大面积 403）：

```bash
ssh root@155.103.194.69 /root/deploy/unlock_media_origin.sh   # 立刻恢复 allow all
```

> 解锁后签名仍然生效，`/sec/` 依旧需要合法 `?e=&s=`，不会裸奔。

---

## 六、防火墙（可选，但推荐）

目前存储服务器 `ufw` 未启用、iptables 全放行。锁定 nginx 层后应用层已经挡住直连，
如果想让 443 也只对 Bunny 开放：

```bash
# 只放行 Bunny 边缘（587 个 IP 太多，用 ipset 更好；简单做法是只开 443 全放行，
# 靠 nginx allow 兜底 —— 当前就是这么做的，够用）
ufw allow 22/tcp
ufw allow 443/tcp
ufw --force enable
```

> 由于边缘 IP 会变（每月刷新），**不建议**在 ufw 里逐条放行 IP，否则边缘变更时会大面积 403。
> 用 nginx `allow` + 每月自动刷新脚本即可。

---

## 七、日常运维

| 场景 | 操作 |
|---|---|
| 边缘 IP 变更导致 403 | 存储服务器执行 `/root/deploy/update_bunny_realip.sh && /root/deploy/lock_media_origin.sh`。每月 1 号 04:23 已自动跑 realip 刷新；**锁定清单建议在刷新后跟着重跑一次**（可把 lock 也加进 cron，但要先确认 CDN 稳定） |
| 换 CDN 域名 | 改 `php/.env` 的 `ENDPOINT` → 清 runtime → reload php-fpm（签名不变） |
| 预算/带宽吃紧 | Bunny 控制台看 Traffic 报表；`/pub/` 命中率应 >95%，`/sec/` 因 1 小时签名桶，命中率取决于用户重复访问 |
| 想限制单 IP 拉取 | Bunny 控制台 → Security → Rate Limiting，建议 200 req/10s（别太紧，视频拖动会连发 Range 请求） |
| 回流盗链排查 | `/sec/` 的签名 6 小时有效（`SIGN_TTL=21600`），发现泄露就把 `SIGN_TTL` 调小 + 轮换 `SIGN_SECRET`（三处同步：存储 nginx、PHP `.env`、worker env） |

---

## 八、成本预估

| 项 | 单价（参考，以控制台为准） |
|---|---|
| Standard CDN（欧美） | ≈ $0.01/GB |
| Origin Shield | 额外 ≈ $0.005/GB（回源流量） |
| 存储服务器出口 | 15TB/月已含在 £56/月 里 |

按每月 10TB 分发估算：CDN ≈ $100 + shield ≈ $50，比直接用存储服务器的
15TB 出口额度更弹性（超量不用担心被限速）。

> 真正省钱的地方：**静态封面/头图命中率极高**（`/pub/` 缓存 7 天），
> 这部分流量几乎不产生回源；`/sec/` 视频是主要成本项，靠 1 小时签名桶提高复用。
