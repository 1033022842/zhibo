# MKPay（巴西）代收充值对接说明

> 对接日期：2026-10-18 ｜ 范围：**仅代收**（用户付款充值）；代付（提现）未接

## 1. 商户信息（测试阶段）

| 项 | 值 |
|---|---|
| 网关地址 | `https://br.mkpayeasy.com` |
| 商户后台 | `https://br.mkpayeasy.com/admin-OSdkkq/`（登录名 `sugusai`，**首次登录务必改密码**） |
| appId | `697146150936` |
| signKey | 商户后台「开发者管理」重置获取（平台不保存），拿到后填 `.env [MKPAY] SIGNKEY` |
| API 文档 | https://s.apifox.cn/5caac221-771e-4b75-929d-962481063367 |
| 回调出口 IP | `54.207.113.227`（网关侧发起，我方仅需回调地址公网可达） |
| 金额单位 | **分**（BRL 的百分之一，接口传字符串） |

⚠️ **测试商户限制**：代收下单可能失败；测试完成后需在商户后台**申请上线**，经平台管理员审批后方可在生产环境收款。

## 2. 本站配置（.env [MKPAY] 段）

```ini
[MKPAY]
GATEWAY = https://br.mkpayeasy.com
APPID = 697146150936
SIGNKEY = 商户后台重置后填这里
NOTIFY_URL = https://本站域名/api/v1/recharge/notifyMkPay
ORDER_PREFIX = MKS   ; hey girl 站为 MKH
```

**订单号前缀**：hey girl 与 sugus 两站**共用同一个 appId**，网关要求商户订单号「收+付全程唯一」，因此 MKPay 订单用独立前缀区分（本站 `MKS`、hey girl `MKH`），其他网关订单（RC 前缀）不进 MKPay、不受影响。

## 3. 接入架构

复用 `lp_fiat_channel` 法币通道（与 star-pay 马来西亚 MYR 通道同一套）：

```
用户点「巴西 · Pix」按钮（前台由 packages 接口动态渲染，前端零改动）
  → submitFiatOrder()：按后台汇率 USD→BRL 折算并锁定，落库 lp_recharge_order
      pay_channel = 'mkpay'，order_no 前缀 MKS/MKH
  → callFiatGateway() mkpay 分支 → MkPayService::createCollection()
      POST /open-api/pay/payment {amount:"分", notifyUrl, merchantOrderId}
  → 返回 payUrl 收银台（用户在页内自选 Pix / GGPay / ApplePay / CashPay…）
  → 到账双保险：
      ① 异步回调 POST /api/v1/recharge/notifyMkPay（验签 → confirmByGateway 幂等入账 → 回 success）
      ② reconcileGateway 每分钟查单补单（GET /open-api/pay/query，文档明确返回不验签）
```

GGPay / ApplePay / CashPay 无独立通道参数（文档代收接口无 gcode 类字段），由 MKPay 收银台页内自选；商户后续给出通道区分参数时，可在后台多建几条 `gateway=mkpay` 的通道拆按钮。

## 4. 签名算法（回调验签）

```
1. 除 sign 外所有非空参数，按 key ASCII 升序拼成 key1=v1&key2=v2&
   （每组后面都带 &，末尾保留）
2. 追加 key=MD5(signKey)        ← signKey 本身先 md5（32 位小写）
3. 整串再 MD5（小写）= sign
```

回调处理规则（网关要求）：
- 处理成功 / 重复回调 / 无需处理 → 返回纯文本 `success`（否则网关会持续重推）
- 验签失败、入账异常 → 返回 `error` 触发重推
- 状态 `01` 成功入账；`04` 退款只记日志和订单备注人工核对（不自动扣用户代币）；`00/06/90/99` 处理中不处理
- 回调报文里**没有** appId/mchid，身份与完整性全靠验签；订单归属由 merchantOrderId 查本站订单确认（两站 DB 独立 + 前缀区分，收到别站订单会查无此单 → 记日志回 success）

## 5. 状态码（代收）

| status | 含义 | 本站动作 |
|---|---|---|
| 90 | 待扣款 | 等待 |
| 99 | 待提交 | 等待 |
| 00 | 支付中 | 等待 |
| 06 | 待确认（用户已付） | 等待 |
| **01** | **成功** | **入账** |
| 02 | 失败 | 对账时记「未支付」 |
| 03 | 超时 | 对账时记「未支付」 |
| 04 | 退款 | 已入账单记备注人工核对 |

## 6. 后台运营

1. 执行 `sql/upgrade_mkpay.sql`（幂等）→ 生成「巴西 · Pix (MKPay)」通道，BRL / R$，并开启**自动汇率**
2. 通道「对接方式」选 **MKPay（巴西 BRL…）**；网关标识留空
3. 订单列表可按「MKPay（巴西网关）」筛选；法币单金额显示 BRL

### 汇率自动更新（fiat:rate 定时任务）

- 通道行 `auto_rate=1` 时，每小时按 USD 中间价刷新 `usd_rate`（主源 open.er-api.com，备源 frankfurter.app）
- 双保险边界：币种绝对区间（BRL 3~8）+ 偏离当前值 ±30% 以内才落库，否则保持原值并告警日志
- 中间价默认不加点；要覆盖波动/手续费改 `app/command/FiatRateCron.php` 的 `MARKUP_PERCENT`
- 后台「法币通道」编辑里可随时关掉自动汇率改手动
- 服务器 crontab（/etc/cron.d/douyin）加一行，每小时第 17 分执行：
  ```
  17 * * * * root cd /www/wwwroot/douyin/php && php think fiat:rate >> /root/cron_fiat_rate.log 2>&1
  ```

## 7. 上线前清单

- [ ] 商户后台改密码、重置 signKey → 填两站 `.env`
- [ ] `.env` NOTIFY_URL 填各站正式域名（公网可达）
- [ ] 后台法币通道汇率改为实时值
- [ ] `php _test_mkpay.php` 冒烟：验签自测通过；queryBalance 通；测试单能拿到 payUrl（测试阶段允许失败，看网关返回的具体 msg）
- [ ] 测试完成后商户后台**申请上线**，等平台管理员审批
- [ ] 审批通过后，小额真实支付验证回调入账 + 订单状态流转

## 8. 相关代码索引

| 文件 | 职责 |
|---|---|
| `php/config/mkpay.php` | 网关配置（env 映射） |
| `php/app/live/service/MkPayService.php` | 网关服务：鉴权 / 下单 / 查单 / 余额 / 验签 |
| `php/app/live/service/RechargeService.php` | `submitFiatOrder` / `callFiatGateway`（mkpay 分支）/ `reconcileGateway`（补单） |
| `php/app/api/controller/Recharge.php` | `notifyMkPay()` 异步回调入口 |
| `php/app/api/route/recharge.php` | `POST v1/recharge/notifyMkPay` |
| `php/app/admin/controller/live/FiatChannel.php` | 后台通道表单（mkpay 选项） |
| `php/sql/upgrade_mkpay.sql` | 通道种子（BRL 汇率占位 5.40，上线前改） |
| `php/_test_mkpay.php` | 自测脚本（验签自测 / 余额冒烟 / 测试下单） |
