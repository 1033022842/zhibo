# Heygirl · Skytrade 支付回调对接说明

> 适用站点：Heygirl（`https://heygirl.live`，备用别名 `heygirl.chat / heygirl.cam / heygirl.fun / heygirl.one / heygirl.shop`）
> 用途：用户在 Skytrade 固定商品链接完成付款后，由 Skytrade 服务端回调本站，本站按订单号自动入账（开通会员 / 加代币）。

## 1. 回调地址

```
POST https://heygirl.live/api/v1/recharge/notifySkytrade
```

- 请求体：`application/json`（SkyTrade 实际推送就是 JSON）。
- 处理成功返回 HTTP 200 + 纯文本 `success`；返回非 2xx 表示未处理成功，请重试（接口幂等，重复回调不会重复入账）。
- 只有 `event=order.paid` 且 `paymentStatus=PAID`（或 `CONFIRMED`）才入账；
  `order.cancelled` / `paymentStatus=EXPIRED` 只把待支付订单标记为未支付并正常 ack，不入账。

> 回调必须打到 **主域 `heygirl.live`**，不要配 CDN 回源地址或 IP。

## 2. 请求字段（SkyTrade 实际推送）

```json
{
  "event": "order.paid",
  "reference": "26B742EA",
  "status": "CONFIRMED",
  "paymentStatus": "PAID",
  "currency": "usd",
  "amountTotal": "1099",
  "mit": "heygirl",
  "order_no": "SKT202611040930001234",
  "updatedAt": "2026-11-04T01:30:21.808Z",
  "items": [
    {
      "slug": "square-neck-bodycon-midi-dress",
      "name": "heygirl",
      "quantity": "1",
      "unitAmount": "1099"
    }
  ]
}
```

| 字段 | 必填 | 说明 |
|---|---|---|
| `order_no` | 是 | 本站订单号，下单时已拼在付款链接里，原样回传即可 |
| `mit` | 是 | 商户标识，Heygirl 固定 `heygirl`（与 sugus 的 `sugus` 区分，串了会直接拒绝） |
| `amountTotal` | 建议 | 实付金额，单位「分」（`1099` = `$10.99`）。本站换算成 USD 与订单金额比对 |
| `reference` | 建议 | 第三方交易号（如 `26B742EA`），写入订单备注便于对账 |
| `event` / `paymentStatus` | 是 | 见第 1 节，只有 `order.paid` + `PAID` 入账 |
| `items[0].slug` | 建议 | 商品 slug，本站会与下单时的付款链接比对，防止串单 |

兼容字段：`order_no` 也可用 `out_trade_no` / `merchant_order_id`；
`reference` 也可用 `trade_no` / `transaction_id` / `txid`；
`amountTotal` 缺失时也可用 `amount` / `money` / `total`（USD）。

## 3. 签名算法（二选一）

共享密钥 `SECRET`（与 sugus 站点同一串，**注意不要有多余空格/换行**）：

```
d3f10b8f5df559f9be4bf30ef6196254
```

### 方式 A（最简单，推荐）：JSON 字段 `sign`

在原有 JSON 里加一个字段：

```
sign = md5(order_no + SECRET)      // 32 位小写
```

示例（可直接用于联调）：

```
order_no = SKT202611040930001234
SECRET   = d3f10b8f5df559f9be4bf30ef6196254
sign     = 325ba625a41adea46b9427da4af978be
```

### 方式 B：请求头 HMAC-SHA256

```
X-SkyTrade-Signature: sha256=<hex(hmac_sha256(原始请求体, SECRET))>
```

- 对**原始 body 字节**做 HMAC-SHA256，`sha256=` 前缀可带可不带。
- 如果同时带了 header 和 `sign`，任意一个校验通过即可。

> 不带任何签名会返回 `400 invalid sign`。联调期如确实无法加签名，可临时把站点 `.env` 的
> `[SKYTRADE] ALLOW_UNSIGNED` 设为 `1`，联调完必须改回 `0`（生产默认 0）。

## 4. 请求示例

方式 A：

```bash
curl -X POST 'https://heygirl.live/api/v1/recharge/notifySkytrade' \
  -H 'Content-Type: application/json' \
  -d '{
    "event":"order.paid",
    "reference":"26B742EA",
    "paymentStatus":"PAID",
    "amountTotal":"1099",
    "mit":"heygirl",
    "order_no":"SKT202611040930001234",
    "items":[{"slug":"square-neck-bodycon-midi-dress","quantity":"1","unitAmount":"1099"}],
    "sign":"325ba625a41adea46b9427da4af978be"
  }'
```

成功响应：

```
success
```

失败响应（HTTP 4xx/5xx，纯文本）：

| 响应 | 含义 |
|---|---|
| `invalid sign` | 签名缺失或不匹配（含 `mit` 不是 `heygirl`） |
| `order not found` | 订单号不存在 |
| `order channel mismatch` | 该订单不是 Skytrade 通道 |
| `product mismatch` | 回调商品与订单付款链接不一致 |
| `amount mismatch` | 回调金额与订单金额不一致 |
| `error` | 入账异常，请重试 |

## 5. 支付链接（本站下单后跳转）

第三方收银台域名：**`https://globalsky.cc`**（原 `skytrade.cc` 已更换；`mit` 与回调地址不变）。

本站会把订单号拼到固定商品链接后面，例如：

```
代币 $10.99：https://globalsky.cc/api/buy/small-hoop-earrings?mit=heygirl&order_no=SKT202611040930001234
会员 $12.99：https://globalsky.cc/api/buy/enamel-brooch?mit=heygirl&size=s&order_no=SKT202611040930001234
会员 $47.88：https://globalsky.cc/api/buy/small-brooch?mit=heygirl&size=s&order_no=SKT202611040930001234
```

金额 → 商品对照（全部带 `mit=heygirl`）：

| 业务 | USD | 商品 slug |
|---|---|---|
| 代币 | 10.99 | `small-hoop-earrings` |
| 代币 | 25.99 | `pearl-drop-earrings` |
| 代币 | 39.99 | `round-stud-earrings` |
| 代币 | 99.99 | `huggie-hoop-earrings` |
| 代币 | 199.99 | `tassel-earrings` |
| 代币 | 299.99 | `geometric-drop-earrings` |
| 会员 1 Month | 12.99 / 19.99 / 25.99 | `enamel-brooch?size=s / m / l` |
| 会员 3 Months | 26.97 / 44.97 / 62.97 | `metal-hair-clip?size=s / m / l` |
| 会员 12 Months | 47.88 / 119.88 / 191.88 | `small-brooch?size=s / m / l` |
| Plus 1 Month | 59.99 / 69.99 / 79.99 | `bag-charm?size=s / m / l` |
| Plus 3 Months | 117 / 147 / 177（后台 116.99 / 146.99 / 176.99） | `scarf-ring?size=s / m / l` |
| Plus 12 Months | 216 / 336 / 456（后台 215.99 / 335.99 / 455.99） | `metal-hair-comb?size=s / m / l` |

`mit=heygirl` 是第三方识别本站的商户标识；`order_no` 是本站订单号。请确保结算流程保留并回传该 `order_no`，否则本站无法识别是哪一笔订单。

### 5.1 自选点数（金额不固定）—— 自定义金额商品

自选点数没有固定套餐，金额由用户自己输入，走第三方的「自定义金额」商品：

```
https://globalsky.cc/api/buy/custom-amount-jewelry?mit=heygirl&amount={USD}&order_no={order_no}
```

- `amount` 是**两位小数的 USD**（如 `27.95`），不是「分」；传 `2795` 会被当成非法金额退回商品页。
- 实测：`amount=27.95` → 303 到 Stripe 收银台，账单金额 `2795` 分 = `$27.95`。
- 回调里 `items[0].slug` 固定为 `custom-amount-jewelry`，本站用回调的 `amountTotal`（分）÷100 与订单 `pay_amount` 比对后入账。
- 固定金额（第 5 节表格里的档位）仍然走各自的商品链接，不会用自定义金额商品。

## 6. 浏览器付款完成/取消后跳回哪

SkyTrade 后台配置 success / cancel 回跳地址时，统一填本站的动态回跳入口：

```
付款成功：https://heygirl.live/api/v1/recharge/returnSkytrade?order_no={order_no}
付款取消：https://heygirl.live/api/v1/recharge/returnSkytrade?payment=cancelled&order_no={order_no}
```

本站收到后按订单类型 302：

| 订单类型 | 最终落点 |
|---|---|
| 会员订阅（vip） | `https://heygirl.live/ai/Subscriptions.html?order_no=SKT...` |
| 代币充值（coin） | `https://heygirl.live/ai/purchasePoints.html?order_no=SKT...` |

前台页面会根据 `order_no` 轮询订单状态，展示「到账成功 / 未支付」结果。

> 如果 SkyTrade 无法在回跳地址里带 `order_no`，也可以直接配置静态页：
> 会员 `https://heygirl.live/ai/Subscriptions.html`、代币 `https://heygirl.live/ai/purchasePoints.html`；
> 但页面拿不到订单号，只能等用户手动刷新后查订单记录。

## 7. 本站上线状态（内部）

1. 代码：`php/config/skytrade.php`（全部链接已带 `mit=heygirl`）、`SkytradeService`、`RechargeService`、
   `api/controller/Recharge.php`（`notifySkytrade` / `returnSkytrade`）、`api/route/recharge.php` 已部署。
2. `.env`（`/www/wwwroot/douyin/php/.env`）：

```ini
[SKYTRADE]
NOTIFY_SECRET = d3f10b8f5df559f9be4bf30ef6196254
ORDER_PREFIX = SKT
MERCHANT_ID = heygirl
ALLOW_UNSIGNED = 0
```

3. 数据库：`lp_fiat_channel` 已新增 `id=9`，`name=stripe`、`gateway=skytrade`、`currency=USD`、`usd_rate=1`、`status=1`。
4. 前端：订阅页/代币页 `skytrade` 品牌标为 Visa + Mastercard（JS 版本 `20261103stripe3`）。
5. **待办**：SkyTrade 后台把 heygirl 的回调/回跳按第 1、6 节配好，并确认商户号是 `heygirl`；
   跑通首笔真实单后，再把「后台 → 充值渠道」里仍启用的 MKPay 越南（id=6）关掉。
6. 验证方式（服务器上执行）：

```bash
curl -s 'https://heygirl.live/api/v1/recharge/fiat-channels'
# 期望返回 name=stripe、gateway=skytrade

curl -s -o /dev/null -w '%{http_code} %{redirect_url}\n' \
  'https://heygirl.live/api/v1/recharge/returnSkytrade?order_no=XXX&payment=cancelled'
# 期望 302 到 https://heygirl.live/ai/purchasePoints.html?order_no=XXX&payment=cancelled
```

> 说明：本站已实测——正确签名（假订单号）返回 `404 order not found`（验签通过）；
> 错误签名返回 `400 invalid sign`；`mit=heygirl` 通过校验，`mit=sugus` 会被拒绝。
