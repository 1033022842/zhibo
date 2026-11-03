# Skytrade 支付回调对接说明

> 适用站点：Sugus（`https://www.sugus.ai`）
> 用途：用户在 Skytrade 固定商品链接完成付款后，由 Skytrade 服务端回调本站，本站按订单号自动入账（开通会员 / 加代币）。

## 1. 回调地址

```
POST https://www.sugus.ai/api/v1/recharge/notifySkytrade
```

- 请求体：`application/json`（当前 SkyTrade 实际推送的就是 JSON）。
- 处理成功返回 HTTP 200 + 纯文本 `success`；返回非 2xx 表示未处理成功，请重试（接口幂等，重复回调不会重复入账）。
- 只有 `event=order.paid` 且 `paymentStatus=PAID` 才入账；`order.cancelled` / `paymentStatus=EXPIRED` 只标记未支付并正常 ack，不会入账。

## 2. 请求字段（SkyTrade 实际推送）

```json
{
  "event": "order.paid",
  "reference": "26B742EA",
  "status": "CONFIRMED",
  "paymentStatus": "PAID",
  "currency": "usd",
  "amountTotal": "1099",
  "mit": "sugus",
  "order_no": "SKT202611030102546291",
  "updatedAt": "2026-11-02T17:05:21.808Z",
  "items": [
    {
      "slug": "off-the-shoulder-ribbed-sweater-dress-black",
      "name": "sugus",
      "quantity": "1",
      "unitAmount": "1099"
    }
  ]
}
```

| 字段 | 必填 | 说明 |
|---|---|---|
| `order_no` | 是 | 本站订单号，下单时已拼在付款链接里，原样回传即可 |
| `mit` | 是 | 商户标识，固定 `sugus` |
| `amountTotal` | 建议 | 实付金额，单位「分」（`1099` = `$10.99`）。本站会换算成 USD 与订单金额比对 |
| `reference` | 建议 | 第三方交易号（如 `26B742EA`），写入订单备注便于对账 |
| `event` / `paymentStatus` | 是 | 见第 1 节，只有 `order.paid` + `PAID` 入账 |
| `items[0].slug` | 建议 | 商品 slug，本站会与下单时的付款链接比对，防止串单 |

兼容字段：`order_no` 也可用 `out_trade_no` / `merchant_order_id`；`reference` 也可用 `trade_no` / `transaction_id` / `txid`；`amountTotal` 缺失时也可用 `amount` / `money` / `total`（USD）。

## 3. 签名算法（二选一）

共享密钥 `SECRET`（**注意不要有多余空格/换行**）：

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
order_no = SKT202611030102546291
SECRET   = d3f10b8f5df559f9be4bf30ef6196254
sign     = 832cffa6f0259e40c7d0d7dc10f9c152
```

### 方式 B：请求头 HMAC-SHA256

```
X-SkyTrade-Signature: sha256=<hex(hmac_sha256(原始请求体, SECRET))>
```

- 对**原始 body 字节**做 HMAC-SHA256，`sha256=` 前缀可带可不带。
- 如果同时带了 header 和 `sign`，任意一个校验通过即可。

> 不带任何签名会返回 `400 invalid sign`。联调期如确实无法加签名，可临时把站点 `.env` 的 `[SKYTRADE] ALLOW_UNSIGNED` 设为 `1`，联调完必须改回 `0`。

## 4. 请求示例

方式 A：

```bash
curl -X POST 'https://www.sugus.ai/api/v1/recharge/notifySkytrade' \
  -H 'Content-Type: application/json' \
  -d '{
    "event":"order.paid",
    "reference":"26B742EA",
    "paymentStatus":"PAID",
    "amountTotal":"1099",
    "mit":"sugus",
    "order_no":"SKT202611030102546291",
    "items":[{"slug":"off-the-shoulder-ribbed-sweater-dress-black","quantity":"1","unitAmount":"1099"}],
    "sign":"832cffa6f0259e40c7d0d7dc10f9c152"
  }'
```

成功响应：

```
success
```

失败响应（HTTP 4xx/5xx，纯文本）：

| 响应 | 含义 |
|---|---|
| `invalid sign` | 签名缺失或不匹配 |
| `order not found` | 订单号不存在 |
| `order channel mismatch` | 该订单不是 Skytrade 通道 |
| `product mismatch` | 回调商品与订单付款链接不一致 |
| `amount mismatch` | 回调金额与订单金额不一致 |
| `error` | 入账异常，请重试 |

## 5. 支付链接（本站下单后跳转）

本站会把订单号拼到固定商品链接后面，例如：

```
https://skytrade.cc/api/buy/square-neck-bodycon-midi-dress?mit=sugus&order_no=SKT202611021530001234
https://skytrade.cc/api/buy/triangle-bikini-lace-skirt-pink?mit=sugus&size=s&order_no=SKT202611021530001234
```

`mit=sugus` 是第三方识别本站的商户标识；`order_no` 是本站订单号。请确保结算流程保留并回传该 `order_no`，否则本站无法识别是哪一笔订单。

## 6. 浏览器付款完成/取消后跳回哪

SkyTrade 后台配置 success / cancel 回跳地址时，统一填本站的动态回跳入口：

```
付款成功：https://www.sugus.ai/api/v1/recharge/returnSkytrade?order_no={order_no}
付款取消：https://www.sugus.ai/api/v1/recharge/returnSkytrade?payment=cancelled&order_no={order_no}
```

本站收到后按订单类型 302：

| 订单类型 | 最终落点 |
|---|---|
| 会员订阅（vip） | `https://www.sugus.ai/ai/Subscriptions.html?order_no=SKT...` |
| 代币充值（coin） | `https://www.sugus.ai/ai/purchasePoints.html?order_no=SKT...` |

前台页面会根据 `order_no` 轮询订单状态，展示「到账成功 / 未支付」结果。

> 如果 SkyTrade 无法在回跳地址里带 `order_no`，也可以直接配置静态页：
> 会员 `https://www.sugus.ai/ai/Subscriptions.html`、代币 `https://www.sugus.ai/ai/purchasePoints.html`；
> 但页面拿不到订单号，只能等用户手动刷新后查订单记录。

---

## 7. 本站上线步骤（内部）

1. `php/config/skytrade.php`：已按金额配好全部固定商品链接（订阅按 `vip`、充值按 `coin` 分组）。
2. 在 Sugus 的 `php/.env` 增加：

```ini
[SKYTRADE]
NOTIFY_SECRET = 与第三方约定的随机字符串
ORDER_PREFIX = SKT
MERCHANT_ID = sugus
ALLOW_UNSIGNED = 0
```

3. 在 Sugus 数据库执行 `php/sql/upgrade_skytrade.sql`：关闭全部 MKPay 通道，新增 `Skytrade`（USD、汇率 1）通道。
4. 后台「充值渠道」确认该通道已启用、名称为前台展示名（默认 `stripe`）。
5. 同步代码并清 PHP opcache（宝塔面板 → PHP 8.3 → 重载）。
