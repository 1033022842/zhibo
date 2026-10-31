# Sign in with Apple 接入方案（kiss / kisss.ai）

> 目标：在网页登录页新增「Sign in with Apple」，iOS 用户在 Safari 中点击后走苹果系统授权面板（Face ID / 密码），一键完成登录。
> 适用：**纯网页项目**（当前没有 App）。
> 站点：`kisss.ai`；后续可复制到 sugus / heygirl / kelymi（每站需各自的 Services ID 与回调）。

---

## 零、先决条件（必须先解决，否则后面一步都做不了）

| # | 条件 | 现状 | 负责人 |
|---|---|---|---|
| 1 | Apple Developer Program 会员（年费 99 美元）且**后台能正常登录** | 客户当前登录被导到「Need assistance with accessing your developer account?」申诉页，**未解决** | 客户 |
| 2 | 账号角色为 Account Holder 或 Admin（只有这两种能建 Services ID / Key） | 待确认 | 客户 |
| 3 | 拥有 `kisss.ai` 域名的 DNS 或站点根目录写权限（苹果要验证域名归属） | 我方有服务器权限，可代放验证文件 | 我方 |
| 4 | 关闭「隐藏我的邮箱」的限制不存在（中继邮箱要能正常入库） | 代码需兼容 `@privaterelay.appleid.com` | 我方 |

> ⚠️ 第 1 条不解决，整个方案无法启动。建议客户先按苹果申诉表单恢复开发者账号访问权限。

---

## 一、客户需要在苹果后台做的操作（照抄即可）

入口：<https://developer.apple.com/account> → **Certificates, Identifiers & Profiles**

### 1.1 创建 Services ID（这是网页登录用的「客户端 ID」，不是 App ID）

1. 左侧 **Identifiers** → 右上角 `+` → 选 **Services IDs** → Continue
2. Description：`Kisss Web Login`
3. Identifier：`ai.kisss.web`（自定义，全局唯一，建议用反域名格式；**记下来，这就是我们的 client_id**）
4. Continue → Register

### 1.2 给 Services ID 开启 Sign in with Apple 并配置域名

1. 在 Identifiers 列表点开刚建的 `ai.kisss.web`
2. 勾选 **Sign In with Apple** → 右侧 **Configure**
3. Primary App ID：选一个已存在的 App ID（没有就随便建一个空的 App ID，仅作占位）
4. **Domains and Subdomains**：`kisss.ai`
5. **Return URLs**：`https://kisss.ai/api/live/socialCallback/apple`
   - 必须是 https、不能带 query 参数、不能是 IP 或 localhost
6. Save → Continue → Save

### 1.3 下载域名验证文件（关键，漏了会报 `invalid_client` / 域名未验证）

1. 仍在 Configure 弹窗里，点 **Download** 下载 `apple-developer-domain-association.txt`
2. 把文件交给我们就行（我们负责放到 `https://kisss.ai/.well-known/apple-developer-domain-association.txt` 并验证可访问）

### 1.4 创建私钥（.p8）

1. 左侧 **Keys** → `+`
2. Key Name：`Kisss Web Sign in with Apple`
3. 勾选 **Sign in with Apple** → Configure → 选 Primary App ID → Save → Continue → Register
4. **Download** 得到 `AuthKey_XXXXXXXXXX.p8` —— **只能下载一次**，丢了只能重建
5. 记录页面上的 **Key ID**（10 位，如 `ABCDE12345`）

### 1.5 需要交给我们的信息（回填这张表）

| 项 | 值 | 从哪拿 |
|---|---|---|
| Team ID | | 后台右上角账号 → Membership details（10 位） |
| Services ID（client_id） | | 1.1 自己填的 |
| Key ID | | 1.4 页面显示 |
| 私钥 `.p8` 文件 | | 1.4 下载的 AuthKey_xxx.p8 |
| 域名验证文件 | | 1.3 下载的 txt |

> 私钥属于高敏凭据，建议通过加密渠道传递；我方只放在服务器 `/etc/kisss-auth/apple/AuthKey_xxx.p8`（权限 600），**不进 git、不进仓库**。

---

## 二、我方服务端改造清单（按文件）

> 现状：登录网关已支持 google / discord / twitter 三个 OAuth 提供商，代码在
> `php/app/live/service/SocialLoginService.php`、`php/app/api/controller/LoginGateway.php`、
> `php/app/api/route/social_login.php`、`php/config/social.php`、`php/app/live/service/UserService.php`。

| # | 文件 | 改动 |
|---|---|---|
| 1 | `php/config/social.php` | 新增 `apple` 段：`client_id`(Services ID)、`team_id`、`key_id`、`private_key`(文件路径)、`redirect_uri` |
| 2 | `php/app/live/service/SocialLoginService.php` | `PROVIDERS` 增加 `apple`：authorize / token / scope / response_mode；`ready()` 支持 apple |
| 3 | 同上（新增方法） | `appleClientSecret()`：用 `.p8` 现场签发 ES256 的 client_secret JWT（有效期 ≤ 6 个月，建议每次请求生成） |
| 4 | 同上 | `fetchIdentity()` 增加 apple 分支：解析并校验 `id_token`（验签 + `iss` + `aud` + `exp`），取 `sub / email / email_verified / is_private_email`；**昵称只有首次授权才返回** |
| 5 | `php/app/api/route/social_login.php` | 为 apple 增加 **POST** 回调路由（Apple 用 `response_mode=form_post`，回调是 POST 不是 GET） |
| 6 | `php/app/api/controller/LoginGateway.php` | `socialCallback()` 支持 POST 入参；`options()` 增加 `apple` 选项 |
| 7 | `php/app/live/service/UserService.php` | `loginWithIdentity()` 的白名单加入 `'apple'` |
| 8 | `ai-girl-malaysia.com/js/login-gateway.js` | `LABELS` / `ICONS` 增加 Apple 图标；按钮文案与样式按苹果规范 |
| 9 | 版本号 | `main.js` 与 43 个 HTML 的 `?v=` 升版，避免缓存 |

### 2.1 与现有三个提供商的四个关键差异（最容易踩坑）

| 差异 | 说明 | 处理方式 |
|---|---|---|
| **回调是 POST** | Apple 用 `response_mode=form_post`，浏览器会以 **POST 表单**打到 `redirect_uri` | 新增 POST 路由；控制器同时兼容 GET/POST |
| **回调不带 Cookie** | 跨站 POST 时 `SameSite=Lax` 的 `live_oauth_browser` cookie 不会发送，现有「state 绑 cookie」逻辑会直接失败 | state 里带 **HMAC 签名的 payload**（含 browser 指纹），回调时用签名验真，而不是读 cookie；ticket 仍绑 browser，`socialExchange` 同源请求时 cookie 正常 |
| **client_secret 是 JWT** | 不是固定字符串，要用 `.p8` 以 ES256 现签 | `openssl_sign` 得到的是 DER 签名，必须转成 JOSE 的 `R‖S` 原始格式（或直接用 `firebase/php-jwt`）；payload：`iss`=Team ID、`sub`=Services ID、`aud`=`https://appleid.apple.com`、`exp ≤ 15777000` |
| **只首次返回姓名/邮箱** | 第二次授权 Apple 不再返回 `name`，`email` 也可能为空 | 首次登录时把 `name` 合并进昵称、`email` 落库；后续按 `sub` 命中账号即可，不要依赖 name/email 非空 |

### 2.2 客户端展示规范（苹果审核要求，网页同样遵循）

- 只能用官方素材：按钮文案固定 **Sign in with Apple**，图标用官方 SVG，黑底白字 / 白底黑字 / 白底描边三选一，不可改颜色、比例、圆角过大；
- 不得写成「Apple 登录」「Sign in」「Continue with Apple」等变体；
- 按钮位置与 Google 并列即可，不要做成最小化图标按钮。

### 2.3 建议的请求流程（与现有三家一致，仅回调方式不同）

```
用户点击按钮
  → POST /api/live/socialStart/apple     （返回 authorize_url，前端 location.assign）
  → https://appleid.apple.com/auth/authorize?client_id=<Services ID>&redirect_uri=...&response_type=code&scope=name%20email&response_mode=form_post&state=<签名>#
  → Apple 以 POST 表单回 https://kisss.ai/api/live/socialCallback/apple（含 code、state）
  → 服务端换 token（client_secret 现签）→ 校验 id_token → 生成一次性 ticket
  → 302 到 /ai/Login.html#oauth_ticket=<ticket>
  → 前端 POST /api/live/socialExchange 换取本站 access/refresh token（复用现有逻辑）
```

---

## 三、上线顺序与验证清单

| 步骤 | 内容 | 验收标准 |
|---|---|---|
| 1 | 客户交回 Team ID / Services ID / Key ID / `.p8` / 域名验证文件 | 五项齐全 |
| 2 | 我方放域名验证文件 | `curl -s https://kisss.ai/.well-known/apple-developer-domain-association.txt` 返回苹果给的原文 |
| 3 | 部署服务端改造 + `.env` 配置 | `POST /api/live/socialStart/apple` 返回 200 且含 `appleid.apple.com` |
| 4 | 部署前端按钮 | 登录页出现符合规范的 Apple 按钮 |
| 5 | Safari 桌面实测 | 点击后弹出苹果授权面板，授权后回到站点并登录 |
| 6 | iOS Safari 实测 | 同上，支持 Face ID |
| 7 | 二次登录实测 | 不再返回姓名/邮箱仍能正常登录（走 `sub` 命中） |
| 8 | 中继邮箱实测 | 选择「隐藏我的邮箱」时邮箱形如 `xxx@privaterelay.appleid.com`，账号能正常创建 |
| 9 | 异常回归 | 取消授权、state 过期、回调重复提交都要回到登录页并提示，不能 500 |

---

## 四、风险与注意事项

1. **开发者账号当前不可用**：客户后台被导到申诉页，必须先恢复，否则拿不到 Services ID 和 Key。
2. **Apple 回调不接受 `http`**，也不支持带端口的回调地址；kiss 现在是 https 443，满足。
3. **`client_secret` 过期**：最长 6 个月。建议代码每次请求现签，避免到期后登录突然全挂。
4. **中继邮箱**：`@privaterelay.appleid.com` 只能由苹果转发，我方不能主动发信；密码找回等邮件链路不受影响（用户会收到苹果转发），但我们不能把它当作可主动投递的邮箱使用。
5. **账号合并策略**：Apple 返回的邮箱可能与用户已注册邮箱相同，不要自动合并（现有代码已明确「不根据社交邮箱自动合并已有账号」），保持一致。
6. **多站复用**：Services ID 与回调与域名绑定，sugus / heygirl / kelymi 必须各自创建 Services ID，不能共用 kiss 的。

---

## 五、工作量拆分（供排期参考）

| 阶段 | 内容 | 依赖 |
|---|---|---|
| A | 客户恢复开发者账号 + 建 Services ID/Key/域名验证 | 客户 |
| B | 服务端：config + apple provider + client_secret 签名 + id_token 校验 + POST 回调路由 | A 的 Services ID/Key |
| C | 前端：按钮图标与规范样式 + 版本号 | B |
| D | 部署 kiss 并联调（Safari 桌面 / iOS） | A、B、C |
| E | 复制到 sugus / heygirl / kelymi | D 验证通过后 |

> 无 App 的情况下，这是网页端唯一能做到「系统级一键（Face ID）」的方案；成本主要在苹果账号资质和证书，不在代码量。
