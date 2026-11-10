# 外部探测点：UptimeRobot 四站监控（区分「机器炸」vs「链路抖」）

> 建立：2026-11-06（起因：99.11/99.42 上午从运维机连不上被误判宕机，kiss 则是真宕机）
> 工具：UptimeRobot 免费版（50 监控 / 5 分钟间隔）｜创建脚本：`.zcode/uptimerobot_setup.py`

## 一、为什么要有外部探测

运维机（国内电信）→ 东京/荷兰的跨海链路会抖：机器明明活着，SSH/443 却超时。
只有**第三方外部节点**的探测结果才能当裁判：

| 现象 | UptimeRobot | 运维机 SSH | 结论 | 动作 |
|---|---|---|---|---|
| A | ✅ 正常 | ❌ 超时 | **链路抖**（跨海路由/本机代理问题），机器没炸 | 换代理/稍后重试，别急着救火 |
| B | ❌ 告警 | ❌ 超时 | **机器/机房真炸** | 立即查机房控制台/提工单 |
| C | ❌ 告警 | ✅ 正常 | 机器活着但站点层故障（nginx/PHP/证书） | 上服务器看服务 |
| D | ✅ 正常 | ✅ 正常 | 一切正常 | — |

## 二、监控清单（11 条）

每站 3 条：主站（关键词校验）+ 管理后台 + SSH 端口。

| 名称 | 类型 | 目标 | 正常判据 |
|---|---|---|---|
| sugus-主站 | Keyword | `http://172.81.98.55/ai/Home.html` | 含 `Sugus` |
| sugus-后台 | HTTP | `https://sugus.it.com/admin/` | 200 |
| sugus-SSH | Port | `172.81.98.55:22` | TCP 可达 |
| heygirl-主站 | Keyword | `https://heygirl.live/ai/Home.html` | 含 `heygirl` |
| heygirl-后台 | HTTP | `https://heygirl.sbs/admin/` | 200 |
| heygirl-SSH | Port | `172.81.99.11:22` | TCP 可达 |
| kelymi-主站 | Keyword | `https://kelymi.autos/ai/Home.html` | 含 `Kelymi` |
| kelymi-后台 | HTTP | `https://kelymi.autos/admin/` | 200 |
| kelymi-SSH | Port | `172.81.99.42:22` | TCP 可达 |
| kiss-主站 | Keyword | `https://kisss.ai/ai/Home.html` | 含 `Kiss` |
| kiss-SSH | Port | `111.90.150.230:20203` | TCP 可达 |

> 注意：kiss 主站 80 端口是 Certbot `default_server return 404`，必须用 https 域名（见 SOP 第五节）。

## 三、创建步骤（约 3 分钟）

1. 注册/登录 [uptimerobot.com](https://uptimerobot.com)（免费版即可，只需邮箱验证）
2. 左上角头像 → **Settings → API Keys → Main API Key**（不要用 Monitor-specific key）
3. 运维机执行（代理按需设 `HTTPS_PROXY`）：

   ```bash
   python .zcode/uptimerobot_setup.py <MAIN_API_KEY>
   ```

   幂等，重复跑只补缺的。告警通知默认发账号邮箱，可在 Settings → Alert Contacts 加微信/TG 等。

4. 建好后把 kiss 两条**立即告警当作验证成功**（2026-11-06 kiss 宕机中）。

## 四、机房与已知风险（2026-11-06 摸底）

| 站点 | IP | 机房 | 风险 |
|---|---|---|---|
| sugus | 172.81.98.55 | 东京 · JT Telecom (AS137535) | 稳定，今天全程在线 |
| heygirl | 172.81.99.11 | 东京 · JT（同上，同段） | 机器稳；运维机跨海链路偶发抖动 |
| kelymi | 172.81.99.42 | 东京 · JT（同上） | 同 heygirl |
| kiss | 111.90.150.230（SSH 20203） | 2026-11-10 已迁新机（旧机 103.217.252.52 宿主机故障、数据不可恢复） | 监控改用新 IP + 非标准 SSH 端口 |
