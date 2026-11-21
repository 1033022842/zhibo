# Create Character v8 独立浏览器验收报告

- 执行人：deepseek-browser-qa（独立 QA，只审证据）
- 工作根：`D:/ever/douyin/douyin`
- 被测入口：`http://127.0.0.1:8017/ai/createCharacter.html`（把站点挂到同源 `/ai/`，符合 gateway safeReturn 约束）
- 浏览器：Chromium **149.0.7827.55**，`playwright-core 1.62.0`，可执行文件 `C:/Users/a1033/AppData/Local/ms-playwright/chromium-1228/chrome-win64/chrome.exe`
- 被测版本：HTML 18:49:01（v8c）、JS 18:48:10、CSS 18:48:10
- 时间：2026-11-21 18:52–18:54（Asia/Shanghai）
- 结论：**主验收矩阵 78/78 通过**；摘要编辑/取消/草稿恢复 12/12；prompt 文本路径 13/13。桌面布局、男性提示、发色换行、prompt 摘要空卡均已修复；仅剩已知 male entry 素材缺口。

## 1. 方法与环境隔离

- 本地静态服务：`node tests/create-character/qa/serve.mjs`，监听 `127.0.0.1:8017`；`/ai/*` 映射 `ai-girl-malaysia.com/`，`/api/*` 返回本地 mock，绝不落到线上。
- 浏览器层拦截：所有非 `127.0.0.1` 请求 `route.abort()`；线上站点脚本、字体、统计、OAuth 全部不可达。
- 游客身份：不写入 token；所有 `customRoleCreate` / `customRoleRegenerate` 请求计数为 **0**。
- 移动端为 Chromium 设备模拟（`isMobile`、`hasTouch`、对应 viewport），**不是真实 iOS/Safari**；safe-area、软键盘、WebKit 差异未作结论。
- 每次运行记录：命令、浏览器版本、viewport、逐屏标题/滚动/CTA/溢出/破图/占位、API 调用、截图。

## 2. 验收矩阵（全 8 屏）

命令：

```bash
node tests/create-character/qa/run-v8.mjs --tag final-5
```

证据：`tests/create-character/qa/evidence/2026-11-21T10-52-37-560Z_final-5/`
（`report.md` / `report.json` / `shots/<viewport>_s1..s8.png`）

| Viewport | 8 屏走通 | 横向页面溢出 | CTA 完整可见 | 进度 1→8 | 破图 | 重复挂载 | 创建请求 |
|---|---|---|---|---|---|---|---|
| 320×844 | 是 | 无 | 是（Start/Next 180×44，Bring 280×44，底距 16） | 是 | 0 | 无 | 0 |
| 360×844 | 是 | 无 | 是 | 是 | 0 | 无 | 0 |
| 390×844 | 是 | 无 | 是 | 是 | 0 | 无 | 0 |
| 430×844 | 是 | 无 | 是 | 是 | 0 | 无 | 0 |
| 1440×900 桌面 | 是 | 无 | 是 | 是 | 0 | 无 | 0 |

- 屏 1 标题 “Create your Dream AI Girl”；屏 2–8 标题分别为 Ethnicity / Body & figure / Hair & style / Name & age / Adjust her personality / Preferences / Your selection；320 宽 header “Create your AI girlfriend” 完整显示，无省略。
- 屏 4 移动端内容高于一屏（320：841/713；390：874/713；430：934/713），属正常滚动；1440 桌面已限宽 4 列，699/699 一屏放完，CTA 不遮挡。
- 屏 3 选项行为横向滚动条（5 张卡），`docScrollW` 始终等于 viewport，页面本身无横向溢出；报告里“溢出元素 right>viewport”是轮播子项，不是页面溢出。
- 无重复渲染：每屏仅一个 `[data-cc8-screen-body][data-screen=N]`，旧 legacy 向导未加载（默认 v8，`?legacy=1` 才加载旧版）。
- 首屏/摘要截图：同目录 `shots/320x844_s1.png`、`shots/390x844_s1.png`、`shots/1440x900_s1.png`、`shots/320x844_s8.png`、`shots/390x844_s8.png`、`shots/1440x900_s8.png`。

## 3. 深链（参考站参数）

测试 URL：

```
http://127.0.0.1:8017/ai/createCharacter.html?g=female&s=realistic&e=black&a=30&p=average&b=average&bu=athletic&hac=blue&ha=bob&n=Valerie+Wright&knk=master-slave&knk=discipline&knk=chains&step=7
```

实际结果：

- 直接落到 **screen=8 摘要**（`step=7` 映射到摘要）。
- 性别 female、风格 Realistic、族裔 black、体型 average、胸型 Medium（`b=average`）、臀型 Athletic（`bu=athletic`）、发型 Bob、发色 Blue、姓名 “Valerie Wright”、年龄 25–34（`a=30` 落到区间）。
- 摘要逐项：Bio “Valerie Wright, 25–34”；Ethnicity “She is Black” 且有图；Hairstyle “Blue with Bob”；Body/Breast/Butt 均有图；无 “Art pending”；`missing` 为空。
- 截图：`shots/deeplink_390.png`。

## 4. 游客生成与账号门

- 游客点 “Bring her to life”：5 个任务依次点亮（`tasksOn=5`），约 2.8 s 后进入 gate；gate 文案含 “This was a preview — no character was created and nothing was charged.”。
- 点 “Sign up for free”：站内真实 FyLogin 弹窗 **“Create an account”**（Username / Email / Password / Invitation code / Create account / Terms / Sign in），7 个输入框；**URL 保持** `http://127.0.0.1:8017/ai/createCharacter.html`，无外跳。
- 游客创建类 API 调用 **0**；仅站点公共配置、loginOptions、registerConfig、track 等 GET/POST 被本地 mock 接住。
- 模拟已登录（仅写 `localStorage.live_access_token=qa-local-token`，不发起线上登录）：重开入口仍在摘要 screen=8，未触发 `customRoleCreate`；创建/扣费路径未执行。
- 证据：`report.md` 的“游客生成 / 账号门”段 + `shots/guest_gate_390.png`、`shots/guest_signup_390.png`。

## 5. 摘要逐项编辑 / 取消 / 草稿恢复（补充）

命令：`node tests/create-character/qa/summary-edit-checks.mjs`
证据：`tests/create-character/qa/evidence/2026-11-21T10-27-39-973Z_summary-edit/`

12/12 通过：

- 摘要点 Ethnicity 卡打开弹层，Middle Eastern 有标签且有独立图（`catalog-v1/female/realistic/eth-middle-eastern.webp`）。
- 选择并 Save：摘要立即变 “She is Middle Eastern”，卡片图切换；弹层关闭。
- 打开 Hairstyle 改 Straight 后 Cancel：摘要仍 “Blue with Bob”，未落盘。
- 重开**普通入口**（无 query）：仍停摘要 screen=8，Middle Eastern 保留（deep link 参数重开会按设计覆盖草稿，已单独注明）。
- 跨族裔同选项：White 与 Black 的 body-average 图 URL 不同（`.../white/body-average.webp` vs `.../black/body-average.webp`）。
- 全程游客创建请求 0。
- 截图：`shots/390_edit-ethnicity-modal.png`、`shots/390_summary-middle-eastern.png`。

## 6. 本地素材成功 / 404

- 入口图：`/ai/assets/create/entry-realistic.webp` → **200 image/webp**。
- 故意请求不存在文件 `/ai/assets/create/__qa_missing__.webp` → **404**，页面不崩、不重定向。
- 全 8 屏 `brokenImgs=0`；manifest 存在时占位卡为 0（女性链路）；男性入口两张 `male|entry|*` 仍为已知素材缺口（占位，不误报破图）。
- 素材覆盖由 deepseek-assets 的 `verify_assets.py` 对账（684 keys、problems 0），QA 只验证浏览器实际请求与渲染。

## 7. 剩余问题（不阻断主流程，建议修）

1. ~~prompt 摘要空卡~~ 已修：prompt 模式只渲染 Bio、已选字段、Custom appearance/personality、Relationship/Occupation/Personality/Preferences；无 `.cc8-ph`、无空外观卡，副标题 “Your written description is saved with these choices”。证据 `tests/create-character/qa/evidence/2026-11-21T10-52-31-875Z_prompt/shots/390_prompt_summary.png`，13/13 通过。
2. ~~男性摘要 artwork 提示被 CTA 压住~~ 已修：male entry 缺口不再计入摘要，提示移到标题下方，`male_390_s8.png` 无提示、无遮挡。
3. ~~发色横向滚动~~ 已修：10 项两行换行，Blue 选中后可见（`shots/390x844_s4.png`）。
4. **男性入口两张 Art pending**：`male|entry|Realistic`、`male|entry|Anime` 为素材侧确认的最终缺口；不判为破图。
5. ~~桌面 1440 选项区排版松散~~ 已修：屏 2/3/4 限宽居中，5 列/4 列网格，提示回到内容列；`1440x900_s3.png`、`1440x900_s4.png` 一屏放完。
6. **真实 iOS/Safari、safe-area、软键盘未测**：本机无 WebKit，仅 Chromium 模拟；如需结论需真机或 Playwright WebKit。

## 8. 局限

- 仅本地 mock API；真实后端联调、真实 OAuth、实名、支付、发布同步均未触发。
- 浏览器为 Chromium 149 移动模拟，不等于 iOS Safari；`env(safe-area-inset-*)` 与键盘顶起行为未验证。
- 截图按 viewport 输出（移动端 1x 逻辑像素；桌面 1440×900 截图为 2x DPR 图像）。
- 未执行 `.zcode/sync_clients.py` 同步；同步范围审查见 `tests/create-character/qa/audit-sync-scope.md`（只读结论：js/css/assets 在同步范围；站点根 `css/`、`images/` 不在；`CharacterFields.php` 未跟踪需提交；仅 heygirl/sugus 同步 PHP）。

## 9. 复现命令

```bash
# 1) 启动本地 QA 服务（保持前台/后台）
node tests/create-character/qa/serve.mjs

# 2) 五视口全 8 屏矩阵 + 深链 + 游客 + male
node tests/create-character/qa/run-v8.mjs --tag final-5

# 3) 摘要编辑/取消/草稿恢复/跨族裔图
node tests/create-character/qa/summary-edit-checks.mjs

# 4) Write-a-prompt 路径
node tests/create-character/qa/prompt-check.mjs
```
