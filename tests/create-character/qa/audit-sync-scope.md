# 同步范围审查（只读证据，未执行同步）

Source: `.zcode/sync_clients.py`（649 行）与 `docs/多客户同步SOP.md`。以下为 createCharacter 八屏改造的发布边界结论，供 lead/前端/后端核对，不代表已发布。

## 1. 静态文件：新文件放哪才会被同步

`in_scope()`（`.zcode/sync_clients.py:160-183`）只放行：

| 路径 | 规则 |
|---|---|
| `ai-girl-malaysia.com/**/*.html` | 放行（行 169） |
| `ai-girl-malaysia.com/site-manifest.json` | 放行（行 169） |
| `ai-girl-malaysia.com/index.php` | 放行（行 173-174） |
| `ai-girl-malaysia.com/js/**` | 放行（行 176-177） |
| `ai-girl-malaysia.com/assets/**` | 放行（行 176-177） |
| `ai-girl-malaysia.com/_shared/**` | 放行（行 176-177） |
| `ai-girl-malaysia.com/media/**` | 放行（行 178-180） |
| `*/_files/**` | **默认不同步**，仅 `--include-files`（行 181-182） |

**风险点（必须遵守）**：
- 站点根 `css/` 目录不在白名单。新样式若写 `ai-girl-malaysia.com/css/createCharacter.v3.css` 不会被同步。**`js/` 与 `assets/` 都在白名单**：本次 `js/createCharacter-v8.css` 放在 `js/` 可直接同步，无需迁移；只有站点根 `css/` 不行。
- 站点根 `images/` 不在白名单（SOP 行 117 也写的是 `*.html + js/ + assets/ + _shared/ + media/ + index.php + site-manifest.json`）。预览素材必须落 `assets/` 或 `media/`；`assets/` 素材队友正在用，落 `assets/create/` 可直接同步。
- 新增任何顶层目录都要改 `in_scope()`（SOP 红线第 5 条，行 72-73），否则线上 404。
- `*_files/` 素材目录默认不推，若卡片图引用 `createCharacter_files/`，线上会 404。现在该页面的 `_files` 目录不存在，改造时不要引入。

## 2. PHP：只有 heygirl / sugus 吃同步

- `sync_php` 仅 heygirl（行 47-48）与 sugus（行 83-84）为 `True`；kelymi、kiss 未配置，默认 `False`（行 458）。
- 未开 `sync_php` 的客户：PHP 变更被跳过并打印数量，**必须按 SOP 单独核验/人工处理**（行 461-464）。
- heygirl 有 `exclude_php: ["php/config/skytrade.php"]`（行 52），且 `exclude` 掉 privacy/terms（行 62-65）。
- kiss 额外 exclude `face_swap.html/js`、privacy、terms（行 118-123）。
- PHP 白名单 `in_scope_php()`（行 202-209）：
  - 拒绝前缀：`php/vendor/`、`php/runtime/`、`php/public/storage/`、`php/storage/`、`php/database/`、`php/sql/`、`php/web/`、`php/tools/`（行 192-195）
  - 拒绝文件名：`.env`、`.env-example`、`.gitignore`、`composer.lock`、`composer.json`、`check_model.php`（行 196-197）
  - 拒绝文件名前缀：`.env.`、`_test_`、`test_`（行 198）
  - 只放行后缀：`.php`、`.json`、`.phtml`（行 199）
- 迁移脚本 `php/database/migrations/` 与 `php/sql/` 一律不推；上线必须单独执行 SQL（行 188-190）。
- 推完 PHP 会 reload php-fpm（行 556 附近），以 opcache 生效。

## 3. 同步前置条件与副作用

- 源头工作区 `ai-girl-malaysia.com` 必须干净：任何未提交改动直接 `sys.exit`（行 403-406）。
- 变更集来自 `git ls-tree HEAD` 的 blob 快照（行 131-158）；未跟踪文件不会被推。
- 客户仓库会在同步后跟进提交/推送（SOP 行 71），`repo` 指向 heygirl/kelymi/kiss 本地仓库。
- `--dry-run` 只打印，不推送（行 467-471）；但仍会做 preflight 自检。
- `--init-snapshot` 只记快照不推送，误用会让服务器永久停在旧版（SOP 行 127）。

## 4. 对本次改造的可操作结论

1. 前端新文件：`createCharacter.html`（根，OK）、`js/*.js`（OK）、`js/*.css`（OK，本次 v8 CSS 即在此）、`assets/*.css`（OK）、`assets/create/*`（OK）。
2. 后端新文件：`php/app/live/service/CharacterFields.php`（未跟踪，需先提交；`php/app/**` 在 PHP 白名单内，heygirl/sugus 会推，kelymi/kiss 不会）。
3. 素材：不要放站点根 `images/`、`css/`、`createCharacter_files/`。
4. 四站验收顺序：静态 blob 校验 → heygirl/sugus PHP reload 后接口 → kelymi/kiss 单独确认 PHP 是否人工部署。
5. 无明确 Git 提交/推送指令时，本次只交可审查 diff 与本地验收；不运行任何会隐式 push 的同步。
