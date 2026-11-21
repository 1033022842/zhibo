# Create Character v8 前端实现与验收记录

工作根：`D:/ever/douyin/douyin`，站点：`ai-girl-malaysia.com`。
本记录只覆盖前端与本地 mock，未触发线上创建、扣费、提交、推送或部署。

## 改动文件

- `ai-girl-malaysia.com/createCharacter.html`
  - head 加载 `./js/createCharacter-v8.css?v=20261121v8d`
  - 删除旧 `js/createCharacter.js` 静态标签
  - 新增 `#cc8Root`
  - 末尾唯一 bootstrap：默认加载 `js/create-character-v8.js?v=20261121v8d`；`?legacy=1` 只加载旧 `js/createCharacter.js`
- `ai-girl-malaysia.com/js/create-character-v8.js`（新增，约 1900 行）
- `ai-girl-malaysia.com/js/createCharacter-v8.css`（新增，约 990 行；按 SOP 放白名单 `js/`，未改发布脚本）
- `tests/create-character/frontend/`（测试、mock 服务、截图、coverage 数据）

未改 `assets/`、其它站点、后端文件或用户既有工作区改动。

## 本地运行

```bash
cd tests/create-character/frontend
node serve.mjs              # http://127.0.0.1:4180/ai/createCharacter.html
node test.mjs               # 功能/契约 mock
node matrix.mjs             # 摘要编辑、prompt、草稿、迁移、响应式
node flow.mjs               # 从入口点击走完 8 屏
node coverage.mjs           # 素材覆盖矩阵，写 coverage.json
```

浏览器：`playwright-core`（`D:/ever/douyin/_clone_probe/drv/node_modules`）+
`C:/Users/a1033/AppData/Local/ms-playwright/chromium-1228/chrome-win64/chrome.exe`。
所有 `/api/**` 由 Playwright route mock；游客场景断言 `customRole*` 请求数为 0。

## 本轮结果

| 命令 | 结果 |
|---|---|
| `node test.mjs` | 40/40 |
| `node matrix.mjs` | 39/39 |
| `node flow.mjs` | 8/8，FLOW PASS |
| `node coverage.mjs` | female 12 组 0 placeholder、0 broken；male 2 组 0 placeholder；`coverage.json` 有效 |
| `node --check js/create-character-v8.js` | 通过 |

关键断言：

- 默认入口只启动 v8；`?legacy=1` 渲染旧向导且 `window.CC8` 不存在。
- 8 屏标题 y=78–94 稳定；390/430/1280 无横向溢出；320 标题不截断、六族裔全图。
- 游客点 Bring her to life 只走本地预览，创建请求 0；完成后 Sign up / Log in 调用
  `FyLogin.open({mode, redirect})`，redirect 为同源 `/ai/createCharacter.html`。
- 已登录双点 CTA 只发 1 次 `customRoleCreate`；成功后按
  `data.status/data.image_url` 轮询，图片先过媒体白名单。
- 价格确认：取消 0 请求；确认各 1 次；regen body 仅 `{"persona_id":N}`；
  save 后显示 `Chat.html?id=my-N`。
- 刷新恢复：先 GET status 验证归属才显示缓存图；A0002 清缓存并关闭生成层。
- 摘要每卡可编辑，保存只改该字段，取消保持原值，焦点回原卡。
- 男女草稿独立；旧 `cc_profile_v2/cc_step_v2` 迁移到 8 屏新下标。
- 男 payload 不含 `breast/butt_size/Bikini/女声`；男摘要与第 3 屏均无 Butt 卡。
- 自由描述只进 `appearance_description/personality_description`，不塞旧字段；
  Surprise me 保留已输入内容，Use presets 保留输入但不启用描述。
- prompt 模式的摘要隐藏未选的外观/发型/族裔卡，只显示已填描述与已选字段，
  不再出现多张 “Art pending + Surprise me” 空卡。

## 证据截图

目录：`tests/create-character/frontend/shots/`

- 入口/全流程：`s0-entry.png`、`flow-1.png` … `flow-8.png`
- 摘要：`summary-edited.png`、`summary-430.png`、`summary-1280.png`
- 桌面：`desk-2.png`、`desk-3.png`、`desk-4.png`
- 游客：`s8-guest-gate.png`
- 生成：`s8-done.png`、`s8-confirm.png`、`s8-restored.png`
- 男性：`entry-male.png`、`s8-male-summary.png`、`s8-male-failed.png`
- 320：`s2-320.png`
- 自由描述：`prompt.png`、`prompt-summary.png`
- 素材覆盖：`cov-*.png`

## 未验收 / 已知缺口

1. **male entry 两张卡仍是 Art pending**：素材侧确认 male entry 为最终缺口，不用 female 图顶替；入口明确显示占位。
2. **female Realistic 的 body/breast/butt**：5 个非黑族裔共用 `shared_reference_assets` 同图（manifest 声明），不是顶替；跨族裔视觉差异未逐一验收。
3. **真实后端未联调**：只跑本地 mock，未调用线上 create/status/regenerate/save，未产生扣费；价格、余额、A0100/A0101/C0100 形态按后端 m00516/m01024 契约 mock。
4. **male voice**：没有男声 slug，不写 `voice`；对话 TTS 由聊天页自带参数决定。
5. **独立 QA 最终复跑（v8c）**：主矩阵 78/78、prompt 13/13、摘要编辑 12/12，全部通过；QA 结论“这一版可定稿”。剩余仅 male entry 素材缺口。
6. **参考站路径说明**：视觉对照本地历史截图 `jv_05/jv_10/sugus_s0_entry/v3_01`；游客注册引导参考 `jm_9/jsd_7` 的另一访客变体，不声称与参考站同一运行路径。

## Lead 终验（DeepSeek 复核，2026-11-21 19:0x）

| 检查 | 命令 | 结果 |
|---|---|---|
| 后端 | `php tests/create-character/backend/run.php` | PASS=164 FAIL=0 |
| 素材 | `python tests/create-character/assets/tools/verify_assets.py` | problems 0、orphan 0、duplicate 0 |
| 前端单测 | `node tests/create-character/frontend/test.mjs` | 40/40 |
| 尺寸矩阵 | `node tests/create-character/frontend/matrix.mjs` | 39/39 |
| 全流程 | `node tests/create-character/frontend/flow.mjs` | 8/8 |
| 素材覆盖 | `node tests/create-character/frontend/coverage.mjs` | 0 placeholder / 0 broken |
| 独立交互复核 | `node tests/create-character/qa/lead-check.mjs` | 320/390/1440 × 入口+8 屏，27 检查点 FAIL=0；游客创建请求 0 |
| 首图不变 | `sha256` vs `git show HEAD:` | entry-realistic / entry-anime 均一致 |

本轮验收顺手修的三处（不影响产品行为）：
1. `tests/create-character/frontend/test.mjs:190` 恢复态 mock 的 `path: '../../../ai-girl...'` 改为绝对路径（原来从仓库根运行必 ENOENT 崩溃）。
2. `ai-girl-malaysia.com/js/create-character-v8.js:31` manifest 查询版本 `?v=20261121v8` → `?v=20261121v8c`，避免 CDN 缓存命中旧 manifest（旧版 style 段小写、缺 chest/族裔别名）。
3. 删除误写入站点目录的 `ai-girl-malaysia.com/shots/desk-*.png` 测试截图。

QA 的 `run-matrix.mjs` 在 v8c 上只会采到首屏：它先找 enabled 的 Next，而首屏 CTA 在未选风格时按设计 disabled，脚本不会先点风格卡；这是脚本适配陈旧，不是产品回归，已用 `lead-check.mjs` 按真实交互补证。
