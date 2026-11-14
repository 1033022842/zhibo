# 多客户同步 SOP（douyin 上游 → 4 个客户项目）

> **给使用者的 30 秒版本**：功能只改 douyin 仓库 → 提交 → 跑一条同步命令，脚本自动把变更推到客户服务器、打上客户品牌、线上验证、客户仓库跟进提交。**永远不要直接 rsync/手改客户服务器。**
>
> 工具：`.zcode/sync_clients.py` ｜ 建立于 2026-10-18（方法提炼自 heygirl 两轮同步实战）
> 架构方案全文：heygirl 仓库 `docs/多客户品牌隔离与上游同步方案.md`
> 服务器密码等凭据：都在 `sync_clients.py` 的 `CLIENTS` 配置里，本文不重复

---


## 部署前必须先同步 Gitee（强制）

任何部署到服务器的操作，**之前**必须按顺序执行：

```bash
cd /root/deploy/zhibo
git fetch origin
git log main..origin/main --oneline   # 有输出 = 同事有新改动，先 rebase 合并
git pull --rebase origin main
git push origin main
```

- 本地领先 origin：说明自己/上次改动还没推送 → 推送后再部署。
- origin 领先本地：说明同事有新改动 → `git pull --rebase` 合并并解决冲突后再推送。
- **不先推送就部署** = 下一次同步/checkout 会用仓库当前状态覆盖线上，
  会把同事的改动直接抹掉（2026-11-07 已发生一次：More 菜单门控、Create 菜单、
  入口清理、注册发放补丁被旧文件覆盖）。
- 部署完成后也必须再 `git push`，保持 Gitee 与线上一致。

## 一、这套体系是什么

```
                    ┌──────────────────────────────────────────────┐
                    │  douyin 仓库（D:\ever\douyin\douyin）          │
                    │  = 唯一功能上游，所有功能改动只在这里做          │
                    └───────────────┬──────────────────────────────┘
                                    │  python .zcode/sync_clients.py
                                    │  （git blob 快照对比 → 只推真正变更的文件）
        ┌───────────────┬───────────┴───────────┬───────────────┐
        ▼               ▼                       ▼               ▼
heygirl 服务器    sugus 服务器            kelymi 服务器     kiss 服务器
172.81.99.11      172.81.98.55            172.81.99.42      111.90.150.230
heygirl.live      （上游自己就是 Sugus）    kelymi.autos      kisss.ai
        │               │                       │               │
        │ 推送后跑       │ 无需打品牌             │ 推送后跑       │ 推送后跑
        ▼ apply_brand   │                       ▼ apply_brand   ▼ apply_brand kiss
/opt/brand-kit      │               /opt/brand-kit     /opt/brand-kit(kiss)
        │               │                       │               │
        ▼               ▼                       ▼               ▼
heygirl 客户仓库   zhibo 仓库（即上游）    kelymi 客户仓库    kiss 客户仓库
gitee heygirl.git  gitee zhibo.git         gitee kelymi.git   gitee kisss.git
（脚本自动跟进）                            （脚本自动跟进）    （脚本自动跟进）
```

**核心思想**：客户服务器的业务文件 = 上游中性基线；客户品牌**不在业务文件里**，
收敛在服务器 brand kit（`/opt/brand-kit`），每次分发后由 `apply_brand.sh` 幂等打上。
因此上游演进零品牌冲突，同步 = 内容对齐（`git checkout douyin/main -- <目录>`），不是 git merge
（客户仓库独立 init，与上游无共同历史）。

**唯一的例外是 kiss**：客户要求去掉「换脸」功能，这条差异也收敛在 kiss 的 brand kit 里
（`apply_brand.sh` 末尾删页面/清入口），业务文件仍是纯上游基线，见第六节 B''。

## 二、总原则（红线，违反必出事故）

1. **douyin 仓库 = 唯一功能上游**：功能改动只在这里做、提交 git。不在这里改 = 下次同步被冲掉
2. **客户服务器永不从别处 rsync 整目录**：10-15 / 10-17 两次冲掉 heygirl 品牌的事故根源，
   `pull_from_172.sh` 已停用（素材 `/data/media` 除外）
3. **不手改客户服务器上的业务文件**：改了 = 下次同步被冲掉 + 没人知道改过什么。
   客户侧的差异要么提上游，要么进该客户的 brand kit
4. **同步后客户仓库必须跟进提交**（脚本已自动做）：防「只改服务器不进仓库」的漂移
5. **新增顶层静态目录要同步扩 `in_scope()`**：10-18 的 `_shared/` 漏推导致 52 个内页
   JS/CSS 整页 404（见踩坑表）
6. **`/opt/brand-kit` 与仓库 `.zcode/brand_kit_<客户>/` 同源**：改哪边都要把另一边同步，
   否则下次从零装机就丢了这条客户差异
7. **客户差异化页面只改 `client_overrides/<变体>/`**（见第四节末）：改上游同名文件只会影响
   没配 `overrides` 的客户；配了覆盖的客户永远以覆盖目录为准
8. **文档/脚本只有一个正式版，不留 `文档/` 副本**：同步脚本以 `.zcode/sync_clients.py` 为准，
   流程文档以 `docs/多客户同步SOP.md` 为准；本地草稿一律直接改这两处再提交，
   不要再往 `文档/` 放拷贝（2026-10-26 清理：两份副本分别落后 17 行 / 240 行，容易照着旧版操作）

## 三、环境准备（一次性，新同事上手清单）

```bash
# ① Python 3 + paramiko（同步工具的 SSH 通道）
pip install paramiko

# ② git 可用，且 SSH key 已加到 gitee（客户仓库推送用）
ssh -T git@gitee.com        # 应返回 Hi autumn_birds_in_the_moon_sea

# ③ 本地四份仓库（客户仓库是同步脚本的跟进目标，不能缺）
#    D:\ever\douyin\douyin    上游（主战场）
#    D:\ever\heygirl          heygirl 客户仓库（origin=heygirl.git, douyin=zhibo.git）
#    D:\ever\kelymi           kelymi 客户仓库（同款双 remote，master 分支）
#    D:\ever\kiss             kiss 客户仓库（同款双 remote，master 分支）
```

> Windows `core.autocrlf=true` 导致推到服务器的文本文件带 CRLF——浏览器无碍，
> 但**别用 md5 对比本地/服务器判断版本**，用 git blob hash（见验证清单）。

## 四、日常同步（最常用）

### 标准流程

```bash
cd D:\ever\douyin\douyin
git add -A && git commit -m "feat: xxx" && git push    # ① 源头先提交（脚本会拦截未提交改动）

python .zcode/sync_clients.py --client heygirl --dry-run   # ② 预览变更清单（强烈建议）
python .zcode/sync_clients.py --client heygirl             # ③ 执行同步
python .zcode/sync_clients.py                              #    全部客户（heygirl / sugus / kelymi / kiss）
```

每次同步脚本自动完成：**推变更文件 → 服务器 apply_brand 打品牌 → 线上验证 → 客户仓库内容对齐 + 提交推送**。

增量原理：脚本维护 git blob 快照（`.zcode/.sync_state_<客户>.json`），下次只推 hash 变了的文件；
部署范围 = `*.html + js/ + assets/ + _shared/ + media/ + index.php + site-manifest.json`，
`*_files/` 素材目录默认不同步。

### 变体参数

| 命令 | 用途 |
|---|---|
| `--client <name> --dry-run` | 只看会推哪些文件，不动任何东西 |
| `--client <name> --full` | 忽略快照全量对比（首次或怀疑漂移时） |
| `--client <name> --include-files` | 连 `*_files/` 素材一起推（默认排除，素材几十万文件） |
| `--client <name> --init-snapshot` | 只记快照不推送——**仅在确认服务器已与 HEAD 一致时用**（误用=服务器永远停在旧版，见踩坑表） |
| `--client <name> --seed-snapshot-from <rev>` | 服务器已部署 `<rev>`（不是 HEAD）时用它做基线：首次只推 `rev..HEAD` 的变更。**新客户首同步正确姿势**：先 `git log --oneline` 在服务器上确认站点来自哪个提交 |

### 同步后验证清单（别只看脚本输出 OK）

**首选：一条命令跑完全部硬验证**

```bash
python .zcode/verify_client.py --client heygirl          # 换客户名；--client all 跑四台
python .zcode/verify_client.py --client sugus --payment  # 后端有改动时加 --payment
```

它做内容级检查，退出码非 0 就是没同步成功：`assets/ + _shared/` 全量 blob 与上游逐字节比对、
`_shared` 内页引用且文件在、标题/logo 品牌、上游品牌泄漏、嵌套垃圾目录、
（`--payment`）MKPAY 配置段/网关连通/回调路由。sugus 会自动走跳板，避开 CN 区域封锁误判。

> **注意**：`verify_client.py` 只覆盖静态站内容（assets / _shared / 品牌 / 嵌套目录）。
> **它检查不到 PHP 行为、数据库配置值、前端资源版本号**。凡改动涉及后端或库，
> 必须再走 **第九节「跨站功能改动核对」** 的三条证据。10-21 的 heygirl 漏推就是
> `verify_client` 全绿但功能没同步的实例。

脚本的关键词验证可能**假通过**（读的是旧文件/CDN 缓存，10-18 真实发生过）。
需要手工复核时按下面几条：

```bash
# ① blob hash 硬校验：挑 1-2 个本次变更、且不打品牌的文件（assets/_shared 下）
curl -sk https://heygirl.live/ai/_shared/jquery-3.6.0.min.js | git hash-object --stdin
git -C D:\ever\douyin\douyin rev-parse HEAD:ai-girl-malaysia.com/_shared/jquery-3.6.0.min.js
#    两个 hash 必须一致（CRLF 会导致不一致：加 | tr -d '\r' 再比，内容等价即可）

# ② 新增页面的 JS/CSS 真实可达（不是 200 就行，要对上本次改动）
curl -sk -o /dev/null -w '%{http_code}\n' https://heygirl.live/ai/<新文件>

# ③ 品牌没被冲掉：标题 + logo + 客服邮箱
curl -sk 'https://heygirl.live/ai/Home.html?v=1' | grep -oE '<title>[^<]{0,40}'

# ④ Sugus 残留抽查（只应剩 JS 标识符 SugusDialog / try_sugus_banner、cdn.sugus.ai、sugus.ai-*.svg 文件名）
ssh root@<客户服务器> "grep -riE 'sugus' /www/wwwroot/douyin/ai-girl-malaysia.com/*.html | grep -vE 'cdn\.sugus\.ai|sugus\.ai-[0-9a-f]|SugusDialog|SugusTrack|try_sugus_banner|SUGUS_BRAND' | head"
```

> **kiss 必须用域名验证**：`http://111.90.150.230/...` 是 Certbot 写的 80 端口
> `default_server { return 404; }`，IP 直连永远 404，会被误判成「没同步」。
> 一律用 `https://kisss.ai/ai/Home.html`（证书 2027-01-18 到期，Certbot 自动续）。

### 回滚

| 层 | 位置 | 方法 |
|---|---|---|
| 服务器文件 | `/tmp/brand_bak_<客户>_<日期>/`（apply_brand 每次自动备份） | `cp -a` 回站点目录 |
| kelymi 全量 | `/root/kelymi_pre_sync_20261018/`（首次同步前备份，html+js+manifest） | 同上 |
| kiss 全量 | `/www/wwwroot/douyin` 本身是 gitee `zhibo.git` 的克隆（服务器上 `git checkout origin/main -- <路径>` 可随时取回上游版本） | 单文件归位；整站回滚用 git + 重跑 apply_brand |
| 客户仓库 | git | `git revert <sync 提交>` 后照常同步即回滚服务器 |
| 上游 | git | 常规 git 操作，回滚后重新同步 |

### 客户差异化页面（client_overrides，2026-10-25 新增）

某个页面在某些客户上要**完全不同的实现**时，不改上游同名文件、也不手改服务器，
而是把客户版放进 `client_overrides/<变体>/`（目录结构 = 站点根镜像），
再在 `CLIENTS` 里给客户加 `"overrides": "client_overrides/<变体>"`。

```
client_overrides/
  README.md                    # 变体 → 客户 → 覆盖文件 的对照表（改这里必读）
  image-sugus/                 # 变体名按客户或版本命名
    Image.html                 # → 站点 /Image.html
    js/customVideo.js          # → 站点 /js/customVideo.js
  image-heygirl/
    Image.html
    js/customVideo.js
```

| 变体 | 客户 | 覆盖文件 | 说明 |
|---|---|---|---|
| `image-sugus` | sugus | `Image.html`、`js/customVideo.js`（`media/gs/` 由服务器按 `media-src/` 清单自拉） | 新版图片页（瀑布流卡片视频自动播放 + 生成器式弹窗） |
| `image-heygirl` | heygirl | `Image.html`、`js/customVideo.js` | 同上 |

> **两家共用一个客户，功能必须一致**：sugus 与 heygirl 属同一客户，`Image.html` /
> `js/customVideo.js` 两份覆盖文件除脚本版本号外应保持**内容一致**。改一处必须同步另一处，
> 否则同一客户在两个站看到的功能不一样。kelymi / kiss 是另外的客户，**不要**同步过去。

### AI 工作室卡片功能（aiShort 通道）—— 部署范围：仅 sugus + heygirl

这套功能**只给 sugus 和 heygirl**，kelymi / kiss 不部署。它比纯前端页面重，
涉及四层，缺一层就跑不起来（页面能开、点生成没反应）：

| 层 | sugus（已上线） | heygirl | 说明 |
|---|---|---|---|
| ① 前端页面 | `client_overrides/image-sugus/` | `client_overrides/image-heygirl/` | `Image.html` + `js/customVideo.js` |
| ② 静态素材 | `media/gs/`（565MB，420 mp4 / 423 jpg / 573 webp） | 需同步 | 视频 + 封面 + 人像缩略图（`persona_*.webp` 卡片角标、`prev_persona_*.webp` 弹窗大图）+ `clips.json` |
| ③ 数据库 | `lp_short_preset` 36 行 + `lp_ai_task` | **无表，需建** | 提示词正文、LoRA 链、采样参数；卡片 code 如 `a13-01` |
| ④ 后端 API | `app/api/controller/AiShort.php` | **缺文件，需部署** | `/api/aiShort/{presets,detail,submit,myList,pending,accept,upload,complete,fail,progress}` |
| ⑤ GPU worker | `custom-short-worker@sugus.service` | **缺实例，需部署** | 同一台 GPU 加一个 `@heygirl` 实例，读 `/opt/cv/short/heygirl/env` |

**素材不进 git、也不走 sync_clients 增量推送**（565MB 会拖死每次同步）。正确做法二选一：
- 服务器之间直传：`rsync -a --ignore-existing sugus:/…/media/gs/ heygirl:/…/media/gs/`（同机房，最快）
- 或从 sugus 公开 CDN 拉：`curl` 逐个下载（慢，仅在跨机房无直连时用）

**新客户接入清单（照此顺序，缺一不可）**：

1. **前端**：把 `client_overrides/image-sugus/` 的 `Image.html` + `js/customVideo.js` 复制到该客户的变体目录，
   只保留该客户自己的脚本版本号（`shell-new.js?v=`、`creator-guide.js?v=` 等不要跟着改）
2. **素材**：把 `media/gs/` 整个目录（含 `clips.json`）同步到客户站点根
3. **数据库**：依次跑 `upgrade_ai_short.sql`（建 `lp_short_preset` + 种子）、
   `upgrade_short_full_params.sql`（加 task_type/sampler/scheduler/steps/unet_name）、
   `upgrade_short_cards21.sql`（加 pipeline/resolution）、
   `_cards21_insert.sql`、`_cards_d11_insert.sql`（灌 36 张卡片）
4. **后端**：部署 `app/api/controller/AiShort.php`；确认 `lp_ai_task` 已有 `idx_type_status_id` 索引
5. **GPU worker**：
   ```bash
   ssh gpu
   cp -a /opt/cv/short/sugus /opt/cv/short/<站点>       # 复制工作目录（含 short_base.json、env）
   vi /opt/cv/short/<站点>/env                          # 改 SHORT_TAG / SHORT_API_BASE / SHORT_KEY
   systemctl enable --now custom-short-worker@<站点>    # 模板 custom-short-worker@.service
   ```
   worker 的 `SHORT_TAG` 必须与站点客户名一致（用于产物文件名防串台）
6. **验证**（四项全过才算完成）：
   - `curl https://<站点>/api/aiShort/presets` 返回 36 张卡片
   - `curl -I https://<站点>/ai/media/gs/` 下任一 mp4/jpg/webp 返回 200
   - 浏览器打开 `/ai/Image.html`：卡片网格有分页条、左上角有人像角标、首屏只加载封面
   - 点一张卡片：弹窗播放对应人设的样片、左侧预览是清晰大图、按钮显示「Upload image first」置灰

**已知坑**：
- `/pending` 下发的 `image_url` 是站内相对路径，worker 侧需按 API origin 补齐（已在 `_abs_media_url()` 处理）
- `/tmp` 若是 `755 root:root`，PHP-FPM 和 MySQL 都写不了临时文件 → 上传 500、`UPDATE` 报 errno 13。
  修法：`chmod 1777 /tmp`，或给两者单独配 tmpdir（见故障排查表）
- `clips.json` 里同一卡片的样片**共用 `id`**，前端必须按 `slug` 定位，否则点第 2/3 条永远播第 1 条
- 分页条数要取「列数 × 行数」的整数倍，否则末排会出现单张孤卡

**覆盖必须成对**：新版 `Image.html` 引用 `./js/customVideo.js`，只覆盖 HTML 会让客户页
加载上游旧脚本，DOM 与逻辑对不上（页面空白、控制台报错）。凡是被覆盖页面依赖的
本地脚本，都要在覆盖目录里放一份同路径副本。

脚本自动完成四件事：① 被覆盖路径从增量推送中剔除（不推上游版）→ ② 增量推完后补传覆盖文件
（跳板/直连都支持）→ ③ 直连客户再跑 `apply_brand.sh`（品牌替换作用于覆盖页）→
④ 客户仓库 `checkout` 后把覆盖文件写回 `ai-girl-malaysia.com/` 再提交（服务器 = 仓库）。

注意：
- 覆盖文件**不在 blob 快照里**，每次同步都会重传（同尺寸跳过），请保持体积小
- 唯一例外：`image-sugus/media-src/`（清单 + 抓取脚本）不发布到站点；205MB 的 `media/gs/`
  由服务器执行 `python3 fetch_media.py` 从公开 CDN 拉，不要放进覆盖目录（每次同步会重传 205MB）
- 覆盖目录以 `.` 开头的文件（`.gitkeep`）不会发布
- 新增/改名/换客户时，同步更新 `client_overrides/README.md`、本文档、`.zcode/skills/client-sync/SKILL.md`
- 上游 `Image.html` 头部已有提示注释，指向本目录；同事打开文件即可看到

> **2026-10-26 落地教训**：新版图片页最早是直接改上游 `Image.html` + `js/customVideo.js`
> 提交进 main 的（`605e1af`），等于把 kelymi / kiss 也要改的页面一起换掉了。
> 正确做法是上游保持中性基线，新版只放覆盖目录 —— 已按此回退并搬迁。

## 五、故障排查

| 现象 | 原因 | 处理 |
|---|---|---|
| `!! douyin 工作区 ai-girl-malaysia.com 有未提交改动` | 设计如此（源头必须干净） | 先 commit + push 再同步 |
| `!! 未配置服务器（占位），跳过` | CLIENTS 里 host 是 None | 补齐配置 |
| paramiko 连接 `EOFError`（banner 正常、网站正常） | 短时间高频建连触发服务器会话级限流 | 等几分钟自动恢复；诊断脚本合并命令减少建连 |
| paramiko `Incompatible ssh peer (no acceptable host key)` | 服务器只有 rsa/dsa host key，paramiko 2.x 只报 `ssh-rsa`（SHA-1），OpenSSH 8.8+ 默认拒绝 | 服务器上 `ssh-keygen -A` 生成 ed25519/ecdsa 后 `systemctl restart ssh`；本地也建议升级 paramiko≥3 |
| 线上验证 OK 但改动没生效 | 脚本关键词验证读到了旧文件/缓存 | 按第四节验证清单做 blob hash 硬校验 |
| 推送成功但页面 404 JS/CSS | 新增顶层目录没进 `in_scope()` | 扩 `in_scope()` 后删 `.sync_state_<客户>.json` 强制全量 |
| 怀疑服务器被改过/漂移 | 手改或事故 | `--full` 全量重推 + apply_brand 重打，一切归位 |
| 客户仓库推送失败 | gitee SSH key / 权限 | `ssh -T git@gitee.com` 检查；仓库地址见速查表 |
| `curl http://<kiss IP>/ai/...` 永远 404 | kiss 的 80 端口是 Certbot 的 `default_server { return 404; }` | 用 `https://kisss.ai/ai/...` 验证；80 上带 `Host: kisss.ai` 只会 301 |
| 改了上游 `Image.html`，sugus / heygirl 没变化 | 预期：这两家配了 `overrides`，只读 `client_overrides/image-*/` | 要改它们就改对应覆盖目录；要确认谁在用 `grep -n overrides .zcode/sync_clients.py` |
| 卡片页能开但点「Generate」没反应 | heygirl 这类新接入客户缺 ③④⑤ 任一层：`lp_short_preset` 表不存在 / `AiShort.php` 没部署 / GPU 没有对应 `custom-short-worker@<站点>` 实例 | 按第四节「AI 工作室卡片功能」接入清单 3→4→5 逐层补齐；先 `curl /api/aiShort/presets` 确认后端通 |
| 点第 2/3 条卡片，弹窗永远播第 1 条 | `clips.json` 同卡片样片共用 `id`，前端按 `id` 查找只能命中第一条 | 前端改为按 `slug` 定位（`data-gs-card=slug`、`data-gs-id=code`） |
| 分页最后一排只有单张卡片 | 每页固定条数 ÷ 列数 有余数 | 分页条数取「列数 × 行数」整数倍（参考站 5 列 × 6 行 = 30） |
| 弹窗左侧人像预览很糊 | 用了卡片左上角的 120×156 角标缩略图放大 | 单独生成 480×640 的 `prev_persona_*.webp`，弹窗用大图 |
| 同一客户两个站功能不一致 | 只改了 sugus 的覆盖目录，没同步 heygirl | 两份覆盖文件除脚本版本号外应一致：`diff client_overrides/image-{sugus,heygirl}/js/customVideo.js` 应为空 |
| sugus / heygirl 的 Image 页空白或报错 | 只覆盖了 `Image.html`，`js/customVideo.js` 还是上游旧版，DOM 与脚本不匹配 | 覆盖目录里补齐 `js/customVideo.js`（两个文件成对覆盖），重跑同步 |
| 覆盖页被还原成上游版 | 有人在客户仓库手敲了 `git checkout douyin/main -- ...` | 重跑一次同步（脚本会把覆盖文件写回）；提交后再核对 blob hash |
| 通话在 App 内置浏览器里完全没声音 | 微信/Instagram/Facebook 内置浏览器、iOS Chrome（WKWebView）没有 Web Speech API（`speechSynthesis`） | 前端已自动改走服务端 TTS `/api/live/tts`（xAI `/v1/tts` 代理，需登录、600 字符上限、1 小时缓存）；php 侧需部署 `app/common/service/TtsService.php` 与 `app/api/controller/Live.php`。该接口只影响无 TTS 的浏览器，正常 Safari/Chrome 仍走本地合成，不产生额外费用 |
| 通话里说话没反应（能听到 AI） | 同类 WebView 也缺 `SpeechRecognition`；有些 WebView 暴露了 API 但没有可用引擎 | 前端已自动改走 WebAudio 录音（PCM→16k WAV，静音 0.9s 自动结束，最长 12s）+ `/api/live/stt`（xAI `/v1/stt`，需登录）；php 需部署 `app/common/service/SttService.php`；麦克风被拒时会提示改文字输入 |
| 会员订阅成功、会员已生效，但页面承诺的赠送 Token 没到账 | ① 站点 `VipService.php` 是旧版，没有 `vip_grant`；② 旧逻辑只在 `daily_diamond=0` 时发，老套餐 `daily_diamond>0` 被跳过 | 按第六节 B''''' 升级 `php/app/live/service/VipService.php` 并 reload php-fpm；历史单用 `WalletService::credit(...,'vip_grant',...)` 补发，备注带订单号便于对账 |

| 所有上传接口 500（nginx error.log 里有 `File upload error - unable to create a temporary file`） | `/tmp` 权限被改成 `755 root:root`，php-fpm 的 `www-data` 无法在其中创建上传临时文件。上传默认落 `/tmp`，而 session 在 `/var/lib/php/sessions`，所以站点其他功能看起来正常，只有上传全挂 | `chmod 1777 /tmp`；自测 `su -s /bin/sh www-data -c 'touch /tmp/x'` 应成功。四台服务器都查一遍（2026-10-29 sugus 只此一台异常） |

## 六、新客户一次性初始化（kelymi 已于 2026-10-18 照此完成，可作范本）

### A. 客户仓库（gitee 私有仓库）

1. `git clone D:\ever\douyin\douyin D:\ever\<客户>` 本地克隆为基线（完整历史）
2. 配双 remote：`origin` = 客户 gitee 仓库，`douyin` = `git@gitee.com:autumn_birds_in_the_moon_sea/zhibo.git`；
   分支改名 `main → master`（同步脚本 push 的是 `origin master`）
3. 建 `brand/<客户>/`（manifest.env + assets：favicon/touch/wordmark(+2x)/logo/share_card/brand.js/manifest）
4. 建 `tools/apply_brand.sh`（从 `.zcode/brand_kit_kelymi/tools/` 拷改）——
   **注意上游 `.gitignore` 全局忽略 `tools/`，必须 `git add -f tools/apply_brand.sh`**
5. 加 `.gitattributes`（`*.sh text eol=lf`，防 CRLF 弄坏 bash 脚本）
6. 中性校验：`git diff douyin/main -- ai-girl-malaysia.com` 应为空——
   html 里有 Sugus 文案属**预期**（上游本身就是 Sugus 生产，品牌只在服务器打）；
   要零残留的是**客户自己的品牌词**（客户仓库 html 不应出现客户品牌名）

> kelymi 实录：本地 `D:\ever\kelymi`，init 提交 `88af874`。
> brand kit 源码统一放在 douyin 仓库 `.zcode/brand_kit_kelymi/`（与服务器 `/opt/brand-kit` 同源，改哪边都要同步另一边）。

### B. 客户服务器

1. 站点目录部署（首次全量，后续走 sync_clients）
2. `mkdir -p /opt/brand-kit`：上传 `tools/apply_brand.sh` + `brand/<客户>/`
3. 跑一遍 `bash /opt/brand-kit/tools/apply_brand.sh <客户>`，浏览器验证品牌
4. 在 `sync_clients.py` 的 `CLIENTS` 里补齐该客户配置（host/凭据/site/brand/repo 路径）

### B'. kelymi 初始化实战经验（供第四个客户参考）

- **替换规则从实测 diff 提炼**，不靠拍脑袋：找服务器上次人工同步对应的上游提交
  （`git log --until=<同步时间>`），`git show <c>:ai-girl-malaysia.com/Home.html` 与服务器文件
  difflib 逐行对齐，32 处变更即完整替换表（注意剔除版本号噪声）
- **`js/brand.js` 整文件由 kit 覆盖**（不要 sed）：它是运行时域名切换器，
  DOMAINS 数组 / DEFAULT_BRAND / window 全局 / 正则四处联动，逐条 sed 极易漏；
  代价是上游改 brand.js 逻辑时 kit 版会静默过时——manifest.env 里有提醒
- **localStorage key 也是品牌面**：规则一条搞定 `s/'sugus\./'kelymi./g`
  （引号内前缀，不会误伤裸文本；覆盖 attr / chat_price / promo.* / site.cfg.v2 / appdl.dismissed）
- **sed 匹配字面正则文本要转义中括号**：上游 JS 里的媒体域名白名单是字面量 `sugus\.[a-z.]+`，
  模式必须写 `sugus\\\.\[a-z.\]\+`，否则 `[` 匹配不上、规则**静默不生效**
- **manifest 图标尺寸如实声明**：kelymi favicon 实际 500x500（人工版错标 260 且引用不存在的 v3），
  无独立 192 资产就不写 192 条目
- **服务器资产就地取材**：客户的 png 若已在站点目录里，服务器上 `cp` 进 kit 即可，不必回传本地

### B'''. kelymi 铭森支付（2026-10-31）

- 网关：铭森支付 `http://sjhsll.com`；下单 `POST /Pay_Index.html`，查单 `POST /Pay_Trade_query.html`；通道编码 1=微信 2=支付宝
- 上游代码：`php/config/sjhsll.php`、`php/app/live/service/SjhsllPayService.php`、
  `RechargeService`（下单 / 查单补单）、`Recharge`（`notifySjhsll` / `returnSjhsll`）、
  `api/route/recharge.php`（两条免登录路由）、`live/FiatChannel.php`（后台选项）、`sql/upgrade_sjhsll.sql`
- **kelymi 服务器是手工最小补丁**：它的后端比上游旧（缺 Cregis / AIPowerPay / WisonPay / 邀请佣金），
  不能整文件上传上游版本；本次只往原文件里加了 sjhsll 分支。
  下次整文件 `git checkout` / 全量对齐前，必须保留这些分支，或先把 kelymi 后端追平上游（SOP 9.5）
- `php/.env` 新增 `[SJHSLL]`（商户号 / KEY / 回调 / 回跳）；`lp_fiat_channel` 补了 `auto_rate` 列
  （kelymi 原表缺，后台保存和 `fiat:rate` 都要用）
- 已建两条 CNY 通道（id 5 微信 / 6 支付宝）但 **`status=0` 先下架**：站内套餐仍是 USD 价，
  CNY 通道汇率按「人民币精确收款」设为 1；先把套餐价改成 ¥9.9 / ¥29.9 等人民币数值，再启用，
  否则 `$15` 会被当成 `¥15` 收
- 实测：微信通道（1）下单返回收银台地址且可打开；**支付宝通道（2）能建单但网关不返回收银台地址**，
  需商户在铭森后台确认通道 2 是否开通，开通前不要启用支付宝通道
- 自测脚本：`php _test_sjhsll.php sign|pay [通道] [金额]|query <单号>|notify <单号>`
- 部署前备份：`/root/sjhsll_bak_20261031_174159`
- 2026-11-01 按《Kelymi会员与Token定价方案》落人民币价：VIP ¥9.9/100 Token、SVIP ¥29.9/1,000 Token、
  Token 6 档（¥3~¥128）；SQL `php/sql/kelymi_pricing_cny_202611.sql`，旧 Premium/Premium Plus 档位已下架
- `VipService::activate` 增加 `vip_grant`：套餐 `daily_diamond=0` 时按 `tokens_month × months` 一次性发 Token
  （仅外部支付订单发放；站内 Token 直购不发，避免「付 299 Token 开 SVIP 再返 1000」套利；旧套餐有每日领取，不受影响）
- `apply_brand.sh` 新增 `subscriptions.js?v=$STAMP` 刷新：改订阅页 JS 后必须跑一次，否则 CDN 继续发旧文件
- `apply_brand.sh` 新增 1.5 节：把 HTML 里的 `Premium Plus` / `Premium` 文案改为 `SVIP` / `VIP`
  （只改页面文案，`data-*` / class / `js/*.js` 逻辑不动）；`privacy/terms` 跳过
- 2026-11-01 前台人民币显示：`brand.js` 设置 `window.SITE_CURRENCY='¥'`，
  `subscriptions / purchasePoints / crypto-pay / pending-order` 的 `money()` 统一读该变量（未设置时仍为 `$`）；
  `apply_brand.sh` 会在 `</head>` 注入页面级货币、把 HTML 里写死的 `$` 价格改 `¥`、
  Compare Plans 的 `330` 改为 `1,000`、未登录也显示 VIP/SVIP 切换器
- `apply_brand.sh` 的 JS 版本号改为分钟级 `$JS_STAMP`，同时刷新
  `subscriptions / purchasePoints / crypto-pay / pending-order / brand.js`，避免 CDN 旧缓存
- 2026-11-02 修复 Tokens 充值页破版：`purchasePoints.js` 的 `renderPlans()` 不能再用
  `$('#tk-plan-grid').html(html)` 整块重写——浏览器解析时充值页的 `<style>`/支付弹窗会落进
  `#tk-plan-grid`，`.html()` 会把它们一起删掉，导致卡片全宽堆叠、金币图按原图尺寸溢出。
  现在改为 `$grid.find('.tk-plan, .tk-empty').remove()` + `$grid.prepend(html)`，只替换卡片；
  `purchasePoints.html` 同时补了 `#token-container{margin-left:200px}`（侧栏展开 199px）和
  `.tk-benefits-wrap{grid-column:1/-1}`（权益区横跨整行）
- 功能消耗已改：聊天 0、通话 3/分钟、图片 3/张、视频 30/条；Live Action、高清图、短剧/商城是逐项数据或未接配置，未批量改
- 微信通道已启用；支付宝仍 `status=0`（网关通道 2 建单成功但不返回收银台地址）
- 定价部署备份：`/root/kelymi_pricing_bak_20261101_184218`

### B''''. kiss HissPay 代收（2026-11-03）

- 网关：`https://transaction.hisspay.com`；代收下单 `POST /transaction/payin/v1/apply`，查单 `POST /transaction/payin/v1/query`
- 签名：请求体 + 请求头 `appId/timestamp(毫秒)/nonce/sign` 按 key 升序拼 `k=v&k=v`，末尾直接追加 `appSecret`，SHA-256 小写十六进制；不是 HMAC/RSA，空值保留、`null` 不参与
- 代码：`php/config/hisspay.php`、`php/app/live/service/HissPayService.php`、`RechargeService`（法币下单 / `callFiatGateway` / `reconcileGateway` / `confirmByGateway`）、`Recharge`（`notifyHissPay`）、`api/route/recharge.php`、`live/FiatChannel.php`、`live/RechargeChannel.php`、`live/RechargeOrder.php`；前台 `js/crypto-pay.js`（姓名/邮箱/手机号表单）、`js/purchasePoints.js` 与 `js/subscriptions.js`（Visa/Mastercard 标）
- 回调：`https://域名/api/v1/recharge/notifyHissPay`，验签后必须返回**纯文本 `SUCCESS`**（无换行）；失败通知 `status=2` 只写 `pay_state=2`，不冲正已入账订单
- `.env` 新增 `[HISSPAY]`：`APP_ID=PzRlHNO`、`APP_SECRET`、`NOTIFY_URL`、`RETURN_URL`、`RETURN_URL_VIP`、`CURRENCY=USD`、`PRODUCT=USD-YKMZXQKYCA`、`PAY_TYPE=CARD`、`AMOUNTS=<48 个固定金额>`、`ORDER_PREFIX=HSP`
- **已跑通**：2026-11-03 产品 `USD-YKMZXQKYCA` + `payType=CARD` 用 `49.99` 打通；2026-11-05 商户补开三档后，`10.00 / 34.99 / 299.99` 也均返回 `payUrl`。通道行 `lp_fiat_channel` id=14（`gateway=hisspay`，USD/2 位）`status=1`，`gateway_ref=USD-YKMZXQKYCA`
- **金额限制**：该产品只支持《商品ID及价格-新.xlsx》里的固定金额（1.99~499）。2026-11-04 商户补开了 `10 / 34.99 / 299.99` 三个面额，现共 48 档；`.env` 的 `AMOUNTS` 逗号分隔配置。`HissPayService::createOrder` 会在下单前拦截非档位金额并返回明确提示，不会把无效金额发给网关（网关会返回 `10005/10009`）
- **2026-11-05 已补齐**：`10 / 34.99 / 299.99` 已追加到 `.env AMOUNTS`（共 48 档）；用站点自身 `HissPayService::createOrder` 实测三档均返回 `payUrl`，`47.88` 这类非档位金额被本地白名单拦截。`/api/v1/recharge/fiat-channels` 的 `amounts` 已含三档；JS 无需改、版本号无需 bump
- **前台联动**：`FiatChannelService::normalize` 把 `amounts` 下发给前台；`js/purchasePoints.js` 与 `js/subscriptions.js` 只在当前套餐价命中档位时才展示 HissPay，避免用户点到必失败的支付方式。改这两个 JS 后 HTML 里的版本号要同步 bump（kiss 已改到 `?v=20261103hiss3`）
- **价格策略（2026-11-03 客户确认）**：不改现有套餐/VIP 价格，只放开命中的档位。2026-11-04 补开三档后，6 个 Token 套餐（`10.00/34.99/49.99/99.99/199.99/299.99`）全部显示 HissPay；VIP 里 `19.99/24.99/49.99/59.99/69.99/79.99/139.99` 可用，`47.88/119.88/191.88/335.99` 等仍不显示 HissPay（继续走其它网关）
- 以前试过的产品码：`USD-EKCBBONA`、`USD-APPLEPAY-PAYIN`、`USD-CASHAPP-PAYIN` 返回 `10004 The product is not activated yet`；`USD-CARD-PAYIN` / `USD-HOSTED-PAYIN` / `USD-LOCAL-PAYOUT` 返回 `10011 The product invalid`；`USD-XGZBJQNJMI` 返回 `10005 No available channels`（该码无可用通道，已弃用）
- 部署备份：`/root/kiss_hisspay_bak_20261103_083652`；`.env` 另有 `.env.bak.hisspay_20261103_product` / `.env.bak.hisspay_20261103_ykm` / `.env.bak.hisspay_20261103_amounts`；`php-fpm` 已 reload，`notifyHissPay` 路由已通（无效签名返回 400 `FAIL`）
- 联调结论：网关签名、余额查询、建单、通知验签均已通过；只差真实付款后的回调入账验证

### B'''''. 会员赠送 Token 漏发修复（2026-11-04，全站）

- 现象：heygirl 客户订阅 1 个月成功、会员已生效，但页面承诺的 100 tokens 没到账（用户 34 / 订单 `RC202611032043490298` / Premium · 1 Month · 100 tokens/mo）
- 根因 ①：heygirl / sugus 服务器上的 `php/app/live/service/VipService.php` 仍是旧版，**没有 `vip_grant` 发放逻辑**（kelymi 已有）
- 根因 ②：旧逻辑只在 `lp_vip_plan.daily_diamond = 0` 时发放，heygirl / sugus 老 18 档套餐的 `daily_diamond` 是 20/25/30/50/60/80，因此即使代码升级也会被跳过；前台又没有「每日领取」入口，这笔 Token 永远拿不到
- 修复：`VipService::activate` 改为**外部订单（网关支付 / 后台人工开通）一律按 `tokens_month × months` 发放**，不再看 `daily_diamond`；发放移入开通事务（失败整体回滚，网关重试可补），流水备注带订单号。站内 Token 直购（`buy()` 传空订单号）仍不赠送，避免套利
- 已部署：sugus `172.81.98.55`（库 `zhibo`）、heygirl `172.81.99.11`、kelymi `172.81.99.42`、kiss `111.90.150.230`；四台均 `php -l` 通过并 reload `php8.3-fpm`。备份：`VipService.php.bak.20261104_125925`（kiss 为 `bak.20261105_143742`）
- 2026-11-05 kiss 主机恢复后已单独同步 `VipService.php`（md5 `62d20cca...`）；kiss 老套餐同样是 `daily_diamond>0`，不同步会继续漏发
- 历史漏发补发（`WalletService::credit`，`biz_type=vip_grant`，备注带订单号）：
  - sugus 用户 25 / `RC202610071254261048` / 3960（Premium Plus 12 月 330/mo，余额 2246 → 6206）
  - heygirl 用户 34 / `RC202611032043490298` / 100（本次投诉，余额 0 → 100）
  - heygirl 用户 9 / `MKHM202610201630516948` / 100（余额 4845 → 4945）
  - kelymi 用户 2 / `RC202610211715119802` / 100（余额 3305 → 3405）
  - kiss 用户 8 / `RC202610211340482918` / 100（余额 2181 → 2281）
  - kiss 用户 11 / `RC202610241752341747` / 100（余额 80 → 180）
- 复盘口径：以后核对会员到账，要同时看 `lp_vip_order`（会员单）、`lp_recharge_order`（支付单 status=1）和 `lp_wallet_ledger`（`biz_type='vip_grant'`）；只有前两者、没有后者就是漏发
- 排查 SQL：`SELECT r.order_no,r.user_id,r.vip_plan_id,p.name,p.tokens_month,p.months FROM lp_recharge_order r JOIN lp_vip_plan p ON p.id=r.vip_plan_id WHERE r.biz_type='vip' AND r.status=1 AND p.tokens_month>0 AND NOT EXISTS (SELECT 1 FROM lp_wallet_ledger l WHERE l.user_id=r.user_id AND l.biz_type='vip_grant' AND ABS(TIMESTAMPDIFF(MINUTE,l.created_at,r.created_at))<=10);`

### B''. kiss 全栈初始化实录（2026-10-19 装机 → 2026-10-20 域名/仓库收尾，裸机 Ubuntu 22.04）

**装机（10-19）**

- 裸机装机：nginx 1.18 + php8.3（ondrej PPA，缺 `php8.3-gmp` 会导致 composer 解析 kornrunner/secp256k1 失败）+ mysql 8 + redis 6
- 代码来源：服务器直连 gitee `zhibo.git`（`--depth 1`，约 30 秒），大陆本机上传只有 ~68KB/s，不要从本机推大文件
- 静态站/后端/DB 首装：站点与 php 均取 gitee HEAD；DB 结构 + 种子数据从 kelymi 导出（93 表），**kelymi 库落后上游近期 SQL**，需补跑 `upgrade_gen_credit / upgrade_mkpay / upgrade_popup_settings / upgrade_vip_order_admin / upgrade_register_email_verify` 等（否则 `image_daily_refill` 之类列缺失会 500）
- 媒体：kelymi `php/public/storage` 只迁 `default/ai_media/chat_media/gift_*/home_*/covers/recharge*/brand/upload/loops/ai/media/shorts`（约 3.1G，kelymi→kiss 实测 ~3.8MB/s）；`private*/certification` 不迁
- 后台：`/www/wwwroot/douyin/admin/dist` 从 kelymi 打包迁移；管理员用 PHP `password_hash` 生成 bcrypt 后**用 SQL 文件导入**（命令行 `mysql -e` 会被 bash 吃掉 `$2y$10$` 里的 `$`，密码永远不对）
- nginx 关键：`.php` location 必须 `fastcgi_split_path_info ^(.+\.php)(/.*)$` + `PATH_INFO $fastcgi_path_info`，否则 `/api/*` 全部落到后台默认响应
- 服务：`kiss-ws.service` = `apps/ws-webman` 的 webman（HTTP 8787 / WebSocket 8788），nginx `location /ws/` 反代 8788

**域名与仓库收尾（10-20）**

- `kisss.ai` / `www.kisss.ai` 已解析到 111.90.150.230，Certbot 证书生效（2026-10-20 → 2027-01-18，自动续）
- 80 端口被 Certbot 改成 `default_server { return 404; }`，所以**只能走 https 域名**验证
- **服务器上的 git 状态：HEAD 停在 `7a38a72`，工作区却已被手工更新到接近上游 HEAD**——两者不一致，
  且工作区里还混着品牌替换（~70 个 html/js）、`face_swap` 已删、以及 `admin/`（未跟踪）。因此：
  既不能 `git reset --hard`（丢手工更新），也不能信 `git status` 判断线上版本；
  核对版本一律用 `git diff --ignore-cr-at-eol origin/main -- <文件>` 逐文件看真实差异
  （合并 CRLF 噪声；品牌行属预期，非品牌差异才是要关注的）
- 首次接同步时服务器基线提交就取 `7a38a72`（`--seed-snapshot-from 7a38a72`），
  它之后的上游变更会再推一遍（同尺寸文件自动跳过，只是幂等重放，不会重复上传大文件）
- **服务器上 `php/` 与上游的差异**（同步工具不覆盖后端，需手工对齐）：`php/config/buildadmin.php` 的
  `admin_login_captcha` 被改成 `false`（kiss 后台不要验证码）——**这是客户差异，下次 `git checkout` php 时别冲掉**
- **`apps/ws-webman/start.php` 曾被改回不含 `support\Env::load()` 的版本**：本机 webman vendor 里没有
  `vlucas/phpdotenv`，`support\App` 的 `.env` 载入被 `class_exists(Dotenv::class)` 短路，所有 `getenv()`
  返回 false，DB 退化成 `zhibo/zhibo`（kiss 库是 `live_platform`）→ WS 写库静默失败。已恢复上游版本并重启
  `kiss-ws`；自查：`php -r` 走 `support\Env::load()` 后 `PDO` 应能连上 `live_platform`

**换脸功能：只砍前台（客户要求）**

| 层 | 处理 | 位置 |
|---|---|---|
| 页面 / 脚本 | 删除 `face_swap.html`、`js/face_swap.js` | `apply_brand.sh` 每次同步后 `rm -f` |
| 侧边导航 | `js/site-shell.js` 的 `['faceswap', './face_swap.html', ...]` 行 | `apply_brand.sh` `sed -i '/face_swap\.html/d'` |
| 推广弹窗 CTA | `js/promo-popup.js` 的 `href="./face_swap.html"` 行 | 同上 |
| 后台路由 / 模板表 | **保留**（后台菜单、`lp_*` 表不动） | — |
| 同步范围 | `face_swap.html` / `js/face_swap.js` 在 `sync_clients.py` 的 `exclude` 里，避免白推后再删 | `CLIENTS["kiss"]["exclude"]` |

> 自查：`grep -rn "face_swap" /www/wwwroot/douyin/ai-girl-malaysia.com --include=*.html --include=*.js` 应无输出
> （`*_files/` 里的抓页残留不算前台入口）；`curl -sk -o /dev/null -w '%{http_code}' https://kisss.ai/ai/face_swap.html` 应为 404。

**品牌**

- 源码 `.zcode/brand_kit_kiss/`（naming 同 kelymi），服务器 `/opt/brand-kit` 与它同源；
  `tools/apply_brand.sh` 也已纳入源码（此前只存在于服务器，属于漂移）
- 图片为 Segoe UI Bold 文字版，拿到官方素材后替换同名 PNG 即可
- 客户仓库 `D:\ever\kiss`（2026-10-20 建，init 提交见仓库日志）：`brand/kiss/` + `tools/apply_brand.sh`
  + `.gitattributes`（`*.sh text eol=lf`），业务文件 = 上游中性基线

**首次同步的正确姿势**（服务器落后上游时）

```bash
# 服务器上确认站点来自哪个提交，再看它之后上游改了什么
ssh -p 20203 root@111.90.150.230 'cd /www/wwwroot/douyin && git rev-parse --short HEAD'

cd D:\ever\douyin\douyin
python .zcode/sync_clients.py --client kiss --seed-snapshot-from <服务器HEAD>   # ② 记基线，不推送
python .zcode/sync_clients.py --client kiss --dry-run                          # ③ 应只剩 HEAD..上游 的变更
python .zcode/sync_clients.py --client kiss                                    # ④ 推送 + 打品牌 + 验证 + 客户仓库跟进
```

> 别用 `--init-snapshot`：它记的是上游 HEAD，会把「服务器还没有的变更」一并标成已同步，服务器永远停旧版。

### C. heygirl 特有文件的先例（客户可以有，同步不会被冲掉）

- `privacy.html` / `terms.html`（合规页）：上游没有，`git checkout` 不会删
- `Home.html` 页脚合规链接：每次同步后脚本不自动恢复——**heygirl 仓库做同步时注意补**
  （后续可把「页脚注入」挪进 apply_brand.sh，彻底免手工）

## 七、踩坑记录（新增坑请补在这里）

| 坑 | 现象 | 规避 |
|---|---|---|
| rsync 整目录从 Sugus 拉 | 客户品牌/配置整体被冲（两次事故） | 已禁用 pull_from_172.sh；素材(/data/media)除外 |
| 部署清单漏 assets/ | HTML/JS 新了但 fy.css 旧，底栏样式不生效 | 脚本按 blob 快照全目录对比，天然覆盖 |
| Windows CRLF 假差异 | 服务器文件 md5 永远和本地对不上 | 别用 md5 判断版本；用 git blob hash（脚本内置）或去 `\r` 后内容 diff（`core.autocrlf=true`，推的是工作区字节，线上 css/js 带 CRLF 但浏览器无碍） |
| 只改服务器不进仓库 | 下次事故没人知道改过什么 | 脚本同步后自动在客户仓库提交推送 |
| 客户仓库无共同历史 | `git merge douyin` 报 unrelated histories | 一律用 `git checkout douyin/main -- <目录>` 内容对齐 |
| nginx admin server 兜底 404 | 2FA 二维码库 /static/js 404 | 新静态路径要在对应 server 显式放行 |
| **`_shared/` 不在推送范围**（10-18） | 456b178 抽共享资源到 `_shared/`，52 个内页引用它，但 in_scope 只认 js/assets → 页面 JS/CSS 整页 404；且快照已记 hash，增量永远补不上 | in_scope 已加 `_shared/`；新增顶层目录时要同步扩 in_scope |
| **upload() 远程路径双前缀 bug**（10-18） | `rel` 带 `ai-girl-malaysia.com/` 前缀直接 join site，全部推进 `site/ai-girl-malaysia.com/` 嵌套目录，真实站点零变更；而「线上验证」读的是旧文件照样通过 | upload() 已剥前缀；**线上验证必须配 blob hash 硬校验**（第四节清单），不能只信关键词验证 |
| **快照预热 ≠ 已同步**（10-18） | `--init-snapshot` 把 HEAD 记成已推送，后续增量全部跳过，服务器实际停在旧提交 | init-snapshot 只在「确认服务器与 HEAD 一致」时用；怀疑时删 `.sync_state_<客户>.json` 强制全量 |
| **SSH 高频建连被限流**（10-18） | kelymi 短时间几十次 paramiko 建连后，banner 正常但会话密钥交换后被掐（EOFError），网站不受影响 | 等几分钟自动恢复；诊断脚本合并命令、减少建连；OpenSSH 客户端能正常握手可区分「sshd 挂了」和「会话限流」 |
| **_files 素材不同步 → 资产级品牌残留**（10-18） | Chat.html 引用 `Chat_files/allow-permissions-sugus-<hash>.png`（教学图，画面含 Sugus 品牌），服务器 _files 里没有 → 404；就算有也是 Sugus 画面 | 文本 sed 管不了二进制；需要时按客户替换素材后 `--include-files` 单独补推，或后续把此类图挪进 brand kit |
| **sync_clients 只覆盖静态站**（10-19 支付部署） | php 后端改动（如 MKPay 支付）推完静态后线上并不生效——后端不在工具范围 | 后端部署五步（见 pay 实例）：服务器备份 → 传 php 文件 → `.env` 加配置段（敏感 key 不进仓库，两台各自的前缀/回调域名不同）→ 幂等 SQL 种子（用 php+PDO 读 .env 跑，别在命令行嵌 mysql 密码）→ `systemctl restart php8.3-fpm` → `_test_*.php` CLI 自测；客户仓库 php 部分需手工 `git checkout douyin/main -- php/<相关文件>` 精准对齐 |
| **sugus 服务器有 CN 区域封锁**（10-19） | 98.55 nginx `if ($is_cn) return 403`——从大陆 curl www.sugus.ai / 172.81.98.55 拿到 403 页，极易误判成「线上旧内容」（403 页没有品牌词也没有新特征） | 验证 sugus 必须从服务器本机（`curl 127.0.0.1`）或非 CN 出口（如 99.11 上 curl）发起；对比文件 hash 前先确认拿到的不是 403 页 |
| **新客户 `.env` 缺 `[LLM]` 段**（10-20） | kiss / heygirl / kelymi 前端发消息均报「Failed to send: AI 聊天尚未配置 API Key」——`php/config/llm.php` 本身读的是 `env('llm.api_key')`，客户服务器 `.env` 按约定不进仓库，初始化时没人补这一段 | 新客户初始化时把 `[LLM]` 一并写进服务器 `.env`（与 sugus 生产一致：`BASE_URL=https://api.x.ai/v1` / `MODEL=grok-4.5` / `TEMPERATURE/MAX_TOKENS/TIMEOUT`，key 用上游 xAI key）；验证用 `php -r` bootstrap 读 `config('llm.api_key')` + HTTP 实测 `/api/live/iceBreaker`（回包 `data.text` 非空即通） |
| **kiss 服务器 sshd 只有 rsa/dsa host key**（10-20） | paramiko 2.7 连 kiss 报 `Incompatible ssh peer (no acceptable host key)`，OpenSSH 客户端却正常——paramiko 2.7 的 `_preferred_keys` 只认 `ssh-rsa`（SHA-1 签名），OpenSSH 8.8+ 默认不再接受 | 服务器 `ssh-keygen -A` 补 ecdsa/ed25519 + `systemctl restart ssh`；本地把 paramiko 升到 ≥3（3.x 支持 `rsa-sha2-*`）。判别：`ssh-keyscan <host>` 只出 `ssh-rsa` 一行就是缺 key |
| **服务器上 `git checkout` 会顺手冲掉客户后端差异**（10-20 kiss） | 排查时用 `git checkout origin/main -- apps/ws-webman/start.php` 归位，把「kiss 上不带 `support\Env::load()` 的本地改动」一起冲掉了；而 webman vendor 没有 `vlucas/phpdotenv` 时 `.env` 整体不被读，WS 的 DB 退化成硬编码 `zhibo`（kiss 库是 `live_platform`）→ 写库静默失败 | 后端文件 checkout 前先 `git diff <file>` 确认不是客户差异；客户后端差异（如 kiss 的 `buildadmin.php` `admin_login_captcha=false`）记进本文档，checkout 后补回 |
| **24.04 三台的 apt 源缺 `noble-security`**（10-22 PHP 补丁） | `/etc/apt/sources.list.d/ubuntu.sources` 只有 `noble noble-updates`；HK 镜像的 `noble-updates` 滞后，候选版本比官方修复版本低一档 → **`apt upgrade` 显示成功、版本号也变了，但漏洞仍在** | 单独加 `ubuntu-security.sources`（Suites: `noble-security`）；升级后必须做版本断言（`dpkg --compare-versions $ver ge $fixed`），不能只看「升级成功」 |
| **launchpad PPA 返回 503**（10-22 kisss） | `ppa.launchpadcontent.net/ondrej/php` 的 .deb 下载 503，apt 卡在 `Ign:` 重试后 `EXIT=100` | 换作者官方源 `packages.sury.org`（实测 200/0.18s）；同一作者同一构建，版本号后缀不同但内容一致 |
| **kisss IP 直连冒烟必 404**（10-22） | kisss 的 nginx 有 `default_server { return 404; }`（Certbot 写的 80 端口兜底），`curl 127.0.0.1/ai/Home.html` 返回 404，会被误判成「升级把站搞挂了」 | 冒烟一律用公网域名 `https://kisss.ai/...` |
| **`sites-enabled` 可能是实体文件而非软链**（10-22 kiss 改上传限制） | kiss 的 `/etc/nginx/sites-enabled/kiss.conf` 是实体文件且比 `sites-available/kiss.conf` 新（多了 kisss.si 跳转、HLS `open_file_cache off`、http2）。改了 available 再 reload，`nginx -T` 里仍是旧值 → **等于白改** | 改 nginx 前先 `ls -l /etc/nginx/sites-enabled/`：`-rw-` 就是实体文件，要直接改它；验证只看 `nginx -T \| grep ...`，别信源文件 |
| **全局 fetch 钩子自我触发 → 死循环**（10-22 userInfo） | `shell-new.js` 的 `window.fetch` 钩子对「任何 POST /api/live/*」触发余额刷新，而刷新本身就是 POST /api/live/userInfo → 无限循环，每标签页 3.3 次/秒。表现为「某个接口提交没响应」，但**服务端 FPM 池是空的、零 5xx**，真因是浏览器 6 个并发连接被死循环占满 | 钩子必须排除自己会发的那个路径；再加 inflight 单飞。排障先看 `systemctl show php8.3-fpm -p StatusText`（active/慢请求数）与 502/504 计数，能快速排除服务端 |
| **兜底阈值必须按真实故障速率校准**（10-22） | userInfo 第一次限流设 5r/s，实测对 1 次/秒的死循环**零作用**（浏览器后台标签限流后正好 1r/s）——加了个不会触发的兜底等于没加 | 限流参数要用真实故障模式打一遍验证；最终用 30r/m + burst 15，300ms 间隔连发 40 次实测 18 次被 429 |
| **`--use-sage-attention` 打黑图片**（10-22 黑图） | sage attention 只给视频做过 A/B；写成全局开关后，Qwen-Image-Edit 的 **fp8 UNET** 在 sm_120 上溢出成 NaN，`VAEDecode` 里 `np.clip().astype(uint8)` 静默把 NaN 变 0 → 5.6KB 纯黑 PNG，**任务状态仍是 completed、用户照样扣费**。日志只有一行 `RuntimeWarning: invalid value encountered in cast` | 性能开关要按**工作流分支**验证，不能按整机；产物层加内容断言（worker v6.1 的 `assert_not_black`）；`invalid value encountered` 出现即 NaN，必须当报警看。详见 `docs/黑图事故复盘-sageattention.md` |
| **"跑成功" ≠ "产物可用"**（10-22 黑图） | ComfyUI 返回 success、文件是合法 PNG、HTTP 200 —— 三个都过，图仍可能全黑。冒烟用 800×800 测试图 + 短提示词都正常，生产是 1079×1920 竖图 + 长英文提示词才暴露 | 验收必须用**真实源图 + 真实提示词**各跑一次；关键产物做内容断言（尺寸/大小/亮度占比），不要只看状态码 |
| **nginx 备份不能放 sites-enabled/**（10-22） | 备份成 `zhibo.conf.bak.userinfo` 放在 `sites-enabled/`，nginx 把 `*.bak` 也当配置加载 → `duplicate default server` → `nginx -t` 失败 | 备份统一放 `/root/nginx_bak/`；只信 `nginx -T` 的实际输出，不看源文件 |
| **chnroutes.mmdb 无 v6 → 大陆手机绕过封锁**（10-25 sugus） | geoip2 的 mmdb 只有 IPv4 段；手机蜂窝网 IPv6 访客经 XFF 还原出 v6 地址后查无记录 → 判不出 CN → 能打开站点。PC（v4）curl 却是 403，极具迷惑性 | 改用 APNIC 生成、v4+v6 全覆盖的 geo 名单（第十节）；验证封锁必须含手机蜂窝网真机 |
| **「CN 出口 curl 403」≠「封锁完好」**（10-25） | v4 链路正常时 IPv6 通道可能仍在漏；反之只测真机手机也会漏掉 v4 边缘名单过期（Bunny 新增边缘不在 realip 信任列表 → 源站把边缘 IP 当访客） | 封锁验证 = v4 curl（403）+ admin 豁免（200）+ 海外出口（200）+ 手机蜂窝网真机，缺一不可；realip 名单月度 cron 别停 |
| **kiss 所有 `.php` 都被硬指到 index.php**（10-22） | kiss 的 php location 写死 `fastcgi_param SCRIPT_FILENAME .../index.php`（ThinkPHP 单入口），往 `public/` 放临时探针 PHP 不执行，被 TP 接走跳 `/install/` | 该站 PHP 行为验证走真实 API；别用探针文件判断配置是否生效 |
| **`--init-snapshot` 用在「服务器落后上游」= 灾难**（10-20 kiss） | 新客户服务器 HEAD 常比上游旧（kiss 停在 `7a38a72`，上游已到 `8363173`）。`--init-snapshot` 记的是上游 HEAD，把没推过的变更全标成已同步，之后增量永远推不动 | 用 `--seed-snapshot-from <服务器实际HEAD>` 记基线，`--dry-run` 确认清单 = 服务器 HEAD..上游 HEAD 的变更后再真跑 |
| **「客户仓库已提交推送」可能什么都没提交**（10-20 kiss 首同步） | 脚本无条件打印这行；客户仓库已与上游内容一致时 `git commit` 为空（`git add` 无改动），实际只有一次空 push | 别把它当提交凭证，去客户仓库 `git log --oneline -1` 核实；内容一致时没有新提交是正确结果 |
| **kiss 的 80 端口被 Certbot 改成 `default_server { return 404; }`**（10-20） | `curl http://111.90.150.230/ai/Home.html` 永远 404，容易误判成「同步没生效/站点挂了」；IP + `Host: kisss.ai` 只会 301 到 https | 一律用 `https://kisss.ai/ai/...` 验证；`sync_clients.py` 里 kiss 的 `verify_url` 也必须是域名 |
| **`open_file_cache` 让直播 m3u8 冻住 60 秒**（10-21 全量修复） | 10-20 加的「性能调优」`/etc/nginx/conf.d/zz-perf.conf` 里有 `open_file_cache max=20000 inactive=30s; open_file_cache_valid 60s;`。nginx 缓存的是**打开的文件描述符**，而 SRS 写 m3u8 是「写临时文件 + rename」换 inode → nginx 手里的 fd 仍指向旧 inode，**m3u8 最长 60 秒不更新**。播放器拿旧播放列表去请求已被 `hls_cleanup` 删掉的 ts 段 → 404 → 画面卡住反复重试。实测修复前 disk 落后 https 10 段（40 秒） | 每台机器所有 `/hls/` location 加 `open_file_cache off;`（只关 HLS，静态资源照旧）。**kiss / heygirl / kelymi / sugus 已全部修完**；新客户初始化必须一并加 |
| **直播没接 CDN（`HLS_CDN_DOMAIN` 指向自己）**（10-21 kiss） | `.env` 里 `HLS_CDN_DOMAIN = https://kisss.ai` = 源站直连（荷兰机房），大陆拉单段 ts 实测 21 KB/s，而当时 3.5 Mbps 需要 437 KB/s，差 20 倍 → 完全播不动。参考：sugus=`ever-candy.b-cdn.net`、heygirl=`hey-girl.b-cdn.net` | 建 Bunny Pull Zone（Origin 指向 CDN 专用回源口）→ `.env` 改 `HLS_CDN_DOMAIN` → 重启 php-fpm。核对 `curl -s https://<域名>/api/v1/feed/live \| grep hls_url` 应为 CDN 域 |
| **CDN 回源后真实 IP 丢失 → 全站共用一个限流额度**（10-21 kiss） | `php/config/throttle.php` 按 IP 限流 `120/m`（`'key' => true` → `$request->ip()`）。挂 CDN 后 `$remote_addr` 全是边缘 IP，所有用户共用配额；后台 IP 白名单与日志同样失真 | nginx 加 `real_ip_header X-Forwarded-For; real_ip_recursive on;` + Bunny 边缘 IP 段 `set_real_ip_from`（`/etc/nginx/bunny_realip.conf`，584 条，每月 1 号刷新）。**必须先装 realip 再切 CDN**。sugus 的 `00-cn-block.conf` 里已有这套（CN 封锁依赖它） |
| **MPA 静态资源走 CDN 的零风险做法**（10-21 kiss） | 想让首页也加速，但把 apex A 记录指到 CDN 会连带支付回调 `/api/v1/recharge/notify*`、后台 `/admin/`、WebSocket 一起过 CDN，风险高 | 只改 HTML 里的**子资源引用**（`./js/ ./_shared/ ./assets/ ./<页面>_files/` → CDN 域名），页面跳转 `.html`、favicon、`/api/`、`/ws/` 保持同源。登录态是 localStorage + `Authorization` 头，不依赖同源 cookie，跨源加载 js/css 不影响会话。实现：`apply_brand.sh` 第 3.5 节 + `brand/<客户>/cdn_assets.env`（留空=关闭） |
| **Bunny zone 挂 `/api/` 能通，但 WebSocket 必须 HTTP/1.1**（10-21） | 用 `curl` 测 WebSocket 升级常拿到 `000`，因为 curl 默认 HTTP/2，而 WS 升级要求 HTTP/1.1 | 加 `--http1.1` 或用 Python 原生 socket 握手；正确结果 `101 Switching Protocols`。Bunny Pull Zone 支持 WS 透传，无需额外开关 |
| **`ultrafast` 预设 + 无 GOP = 码率 3~9 Mbps + 分片 4.2 秒**（10-21 kiss） | `worker.py` 预转用 `libx264 -preset ultrafast -crf 24`，且**不设 `-g`** → x264 默认 keyint=250 + 场景切换插帧，实测 244 帧素材里只有 **1 个 I 帧**；SRS 只能按关键帧切，于是分片忽长忽短（3.4/4.2/4.2s），`TARGETDURATION:5`。同时 ultrafast 关了 CABAC/去块滤波，压缩效率极差 | 三层修：① `-preset veryfast`（实测同 CRF 码率 3~9 Mbps→1.1~2.4 Mbps，PSNR 持平）；② `-g 2*fps -keyint_min -sc_threshold 0`（分片固定 2.5s）；③ `-maxrate 2500k` capped-CRF 兜底。**改完实测 kiss 全房间 0.57~1.45 Mbps、分片 2.5s、TARGETDURATION:3** |
| **预转缓存键不含 CQ/GOP → 改参数静默不生效**（10-21 kiss） | 原键是 `f"{rel}|{TS_SCALE}|{TS_FPS}"`，只认分辨率和帧率。改 `TS_CQ` 或 GOP 后旧缓存仍然命中，**看不到任何变化**，极易误判成「改了没用」 | 键改成 `{rel}|{TS_SCALE}|{TS_FPS}|{TS_CQ}|{TS_GOP}|{TS_PRESET}|{TS_MAXRATE}`。改任一参数都会自然生成新缓存；但**旧缓存不会自动清理**，要手动 `mv /data/runtime/ts_cache/* /data/runtime/ts_cache.bak.<日期>/` |
| **各客户角色/播单数据会互相漂移**（10-21） | 测试反馈「sugus 和 kelymi 的直播间角色不一样，kelymi 多一个已删角色」。查库确认：`lp_room_binding.room_id=24` 在 sugus 是 `persona=Liora / tpl=71(PL_liora)`，在 kelymi 是 `persona=Ophelia / tpl=71(PL_ophelia)`；kelymi 的 `lp_persona` 里 Ophelia(149) `status=2`（已下架）但 room 24 仍 `status=1`，所以前台还在展示。**重启 worker 完全无用**——worker 只读库，播的是库里绑定的那套素材 | 这类差异属于**客户侧运营数据**，不是代码 bug：① 前台是否展示由 `lp_room.status` + `feedLive` 决定，下架角色要同时改 `lp_persona.status=2` **和** `lp_room.status=2`；② 新客户初始化时 DB 是从别的客户导出的，**播单模板/绑定会带着上一个客户的素材**，必须逐房间核对 `room_binding.persona` 与模板条目的 `lp_media_asset.persona` 是否一致（`模板素材=[persona:条数]` 对不上就是这个坑）；③ 不要靠重启进程解决数据问题 |
| **各客户 worker.py 版本不一致**（10-21） | kiss 已是新版（含对象存储 `/storage` 签名拉取），heygirl/sugus 与仓库旧版一致（md5 `b195c0a5…`），**kelymi 更旧**（缺 media 配置、`/storage` 远程拉取等 123 行差异）。直接整文件覆盖会把 kelymi 的既有定制冲掉 | 打补丁脚本按**内容匹配**而非行号（`TS_CQ` 定义行、`key = f"{rel}…"`、`venc = (` 块），并在入口做幂等判断（`if 'TS_GOP' in s: skip`）；先在本地用该服务器的 worker.py 副本跑一遍验证语法，再上传执行 |
| **`live-worker@N` 僵尸实例（房间被删但 unit 没 disable）**（10-21 sugus） | sugus 的 `live-worker@21` 从 2026-10-01 14:32 起每 10 秒崩一次，累计重启 **17335 次**、journal 攒了 **49 万行**。原因：那天后台把房间 21（room_no=R2005）**硬删除**，`lp_room` / `lp_room_binding` 都没了，worker 启动即报「房间 21 未配置流绑定」退出；`Restart=always` + want 软链还在 → 无限重启。台账上留下断口：`lp_room` 是 `1,4,17-20,22-26,27-29`（缺 21 = R2005） | 排查：`systemctl list-units 'live-worker@*' --state=failed,running` 对比 `lp_room` 实际 id；手动跑 `python3 worker.py --room=N` 看是否「未配置流绑定」。处理：`systemctl disable --now live-worker@N` + `rmdir /data/hls/room/N`。**同时必查反例**：sugus 当时 want 清单有 21 却**缺 24**（room 24 是手工 start 的，重启就丢），所以「enabled 列表」和「running 列表」要双向对齐 |
| **`journalctl --vacuum-unit` 在 Ubuntu 22.04 不存在**（10-21） | `journalctl: unrecognized option '--vacuum-unit=...'`——该选项是 systemd 254+ 才有，22.04 是 249 | 清单个 unit 的日志只能 `journalctl --since/--until` 导出后不管，或整机按大小/时间 vacuum（`--vacuum-size`/`--vacuum-time`），无法只删某一个 unit。想防日志无限涨就配 `/etc/systemd/journald.conf` 的 `SystemMaxUse=`（sugus 的 449G 盘按默认 10% = 44.9G 上限，实际已攒 3.6G） |
| **kiss 的 HTML 不走 CDN（只 JS/CSS/图片走）**（10-21） | 实体 URL 仍是 `https://kisss.ai/ai/Home.html`，HTML 由源站（阿姆斯特丹）直接返回；只有被 `apply_brand.sh` 改写的子资源走 `kisss-live.b-cdn.net`。实测 HTML 从大陆 TTFB 0.6~1.0s，子资源 37 个全部 200、合计 639KB（gzip） | 如果还要更快，两条路：① Bunny 建第二个 zone 并绑 `static.kisss.ai`，把 HTML 也改指过去（要改所有页面跳转，工作量大）；② 让主域 apex 整站走 Bunny（像 sugus，但支付回调 `/api/v1/recharge/notify*`、后台、WS 全要回归测试）。当前方案是「子资源加速 + 同源会话不动」的折中，已经覆盖首屏 500KB 里的大头 |
| **Bunny 会给响应改写 `Cache-Control`**（10-21 实测） | 源站给 HTML 的是 `public, max-age=60, stale-while-revalidate=300`，但经 Bunny 拉取后响应头变成 **`cache-control: no-cache`** 且每次都 `cdn-cache: MISS`（不缓存）。而图片/JS（`max-age=604800` / `max-age=2592000, immutable`）能正常 `HIT` | 想验证某个文件到底有没有被 CDN 缓存，不能只看源站头，要**连续请求两次看 `cdn-cache` 是否 HIT**；HTML 类短缓存依赖 `stale-while-revalidate` 时 Bunny 倾向于不缓存，属正常现象（本就该回源） |
| **Bunny 边缘 IP 段需要定期刷新**（10-21 kiss） | `set_real_ip_from` 的 584 条边缘 IP 是快照，Bunny 会增删节点。不刷新的话新节点回源时 `$remote_addr` 就还原不出来，限流/日志会把它当真实用户 | 加每月 cron：`0 4 1 * * /root/deploy/update_bunny_realip.sh`（脚本会拉 edgeserverlist → 重新生成 conf → `nginx -t` → reload）。**heygirl/sugus/kelymi 未配此 cron**，切 CDN 时记得一并加 |
| **一台服务器可承载多个 zone，但 zone 归属账号可能不同**（10-21 heygirl） | `hey-girl`（pullzone 6769428）在**另一个（欠费）Bunny 账号**里，且其 Origin 只配了 `/hls/`（`172.81.99.49:80` 那个 server block 只出 HLS）→ 直接拿它加速静态资源会 404。账号欠费后 HLS 也会整条断掉 | 客户换账号/欠费时，**新建独立 zone**（如 `heygirl-live`，pullzone 6841791），Origin 指向本机 CDN 回源口（`http://<ip>:8081`），然后改两处：① `php/.env` 的 `HLS_CDN_DOMAIN`；② `brand/<客户>/cdn_assets.env` 的 `CDN_ASSET_BASE`。迁移后必须 grep 确认旧域名零残留（html / .env / vue dist） |
| **CDN zone 的标准四件套**（10-21 kiss/kelymi/heygirl 实践定稿） | 每接入一个客户要建：① 本机 `:<port>` CDN 回源口（只放 /ai/ /hls/ /api/ /ws/，/admin/ 与 /storage/ 404）；② `$ai_cache` 分级缓存 map（**没有它 CDN 一个资源都缓存不住**，因为 `/ai/` 默认全 `no-cache`）；③ Bunny realip（584 条 `set_real_ip_from` + `real_ip_header`）；④ 每月 1 号刷新 cron。**端口占用**：8080 常被 SRS 占，用 8081；先 `ss -ltn` 确认空闲 |
| **同一 zone 可同时服务 HLS + 静态，不必拆两个**（10-21） | `kiss`/`kelymi` 都是一个 zone 同时扛 HLS 和 `/ai/` 静态（`kisss-live` / `kelymi-live`），实测 `hls=200 ai=200`。只有 sugus 把 media 拆到独立 zone（`sugus-media`，走 `media.sugus.ai` CNAME） | 新客户优先「1 个 live zone（HLS+静态）+ 1 个 media zone（素材）」，别学 heygirl 早期只配 `/hls/` 的窄 Origin |
| **客户仓库 checkout 到旧版（douyin remote 引用没刷新）**（11-02） | 同步脚本里 `git checkout douyin/main -- <站点目录>` 用的是客户仓库内的 `douyin/main` **本地引用**。如果该引用落后（很久没 fetch），checkout 出来的是旧内容，但脚本仍打印「客户仓库内容对齐: OK」——服务器是新版、仓库是旧版，两边静默分叉 | 同步后核对版本号而非只看脚本输出：`grep -oE 'shell-new\.js\?v=[0-9a-z]+' <仓库>/ai-girl-malaysia.com/Home.html` 应与服务器一致。发现落后就 `git fetch douyin && git checkout douyin/main -- ai-girl-malaysia.com/` 再提交 |
| **CN 封锁导致 sync_clients 的线上验证假失败**（11-02 heygirl） | 脚本用本机（大陆出口）curl `verify_url`，被 nginx 的 `$is_cn_site` 拦成 403 页，页面里既无品牌词也无新特征 → 打印「线上验证: !! 失败」，但实际部署成功 | 看到失败先查是不是 403：`curl -sk -o /dev/null -w '%{http_code}' <verify_url>`。403 就改从服务器本机验证（`curl -H "Host: <域名>" http://127.0.0.1:8081/ai/Home.html`），或走非 CN 出口 |
| **新增 JS 修复必须同步升 `?v=` 版本号**（11-02） | 静态资源 `Cache-Control: max-age=2592000, immutable`（30 天），只推 JS 不升版本号 = 用户端和 CDN 边缘继续用旧文件，修复不生效。且 `client_overrides/` 里的覆盖页（Image.html）也要一起升 | 改 JS 后全局升版本号 + `client_overrides/*/Image.html`；升完 `grep -ohE 'shell-new\.js\?v=[0-9a-z]+' *.html | sort | uniq -c` 确认全站只剩一个新版本 |
| **上传成功但前端 404 / 轮播图黑屏**（11-13 kiss） | 后台传了轮播图，数据库有记录、API 也返回 200，但图片 URL 打开是 **404**（响应体是 S3 的 `application/xml` 错误）→ 前端黑屏。根因是**三个 bug 叠加**：① `MEDIA` 的 S3 凭据（ACCESS_KEY/SECRET_KEY/GATEWAY_KEY）为空；② **`Ajax::upload()` 把驱动从 S3 覆盖回 `local`**（`param('driver','local')` + `setDriver()`，把 `Upload` 构造函数里已经设定的 S3 又改回本地）→ 文件写进本机 `public/storage/`，而 `Media::url()` 仍按对象存储生成 `media.<域名>/pub/<项目>/...` 地址；③ `Media::relPath()` 只剥离带协议头的域名，`kisss.ai/storage/...` 这种不带协议的会原样落到 `keyFor()`，生成 `sec/kiss/kisss.ai/storage/...` 双前缀 key | 修复（已入库 `085f611a` + `3657befd`）：① `.env` 补齐 `ACCESS_KEY/SECRET_KEY/GATEWAY_KEY`（存储服务器 `/mnt/storage/etc/minio.env`）；② `Ajax::upload()` 的 `driver` 参数改默认空串，仅在 `!Media::storageEnabled()` 时才允许覆盖；③ `relPath()` 域名正则改为 `#^(?:https?:)?//[^/]+/#i` 并补一条 `host.tld/storage/` 规则。排查手段：用 `php -r '\$M::putFile(...)'` 直接验 S3 写入（绕过后台），两边对比就能区分「S3 不通」还是「上传路由错」 |
| **对比素材时要区分 `pub/kiss/` 与 `pub/sugus/` 两个前缀**（11-13） | `media.<客户域名>` 同一个存储桶里存着多个项目前缀（`sugus/`、`kiss/`、`hey/`、`kelymi/`）。上传如果用了 A 前缀、API 取用了 B 前缀，就会出现「文件存在但读不到」。实测方法：把 API 返回的 URL 里的前缀互换再试一次 | `curl -sk -o /dev/null -w '%{http_code}' <把 /pub/kiss/ 换成 /pub/sugus/ 的 URL>`——一个 200 一个 404 就是前缀错位。治标方案：修上面两个 bug 后把已有图“下载→重传”到正确前缀，并把数据库里的相对路径（`/storage/...`）改成**完整 URL**（`Media::url()` 对完整 URL 会原样返回，不再参与前缀推导） |
| **数据库里的文件名被重复 URL 编码 → 前端 404，但对象存储里的文件是好的**（11-13 sugus） | 轮播图 9 张里 2 张 404，响应体是 S3 的 xml 错误。查 DB 原值发现文件名被编码了两三层：`%2525283%252529.png…`（实际是 `(3)`）、`%25284%2529.png…`（实际是 `(4)`）；而 `S3 list` 列出的对象名是**明文** `(3).png…` / `(4).png…`。也就是说**文件没错、前缀没错，纯粹是库里存的路径被多编码了一遍**。其余 7 行的 DB 值都是明文 `(2).png…`，浏览器地址栏里的 `%28` 是输出时正常编码，别混淆 | 判断三步：① 用 `Media::s3()->list('pub/<项目>/<目录>/')` 看存储端真实文件名；② `SELECT id, HEX(cover_url)` 看库里原始字节（`%25` 就是多编码）；③ 两者比对。**修复方向是改库、不是重传**——把 `cover_url` 改成存储端真实文件名即可：`UPDATE lp_home_banner SET cover_url='/storage/home_banner/<日期>/(3).png<hash>.webp' WHERE id=29;`。改完清 `runtime/media_cache/*` 再逐张实测。教训：看到 404 先分清「文件不在」还是「路径错」，前者要重传、后者改一行 SQL 就够 |
| **`.env` 是分区 INI，用 `grep DB_PASSWORD` 取密码会拿到空串**（11-13 sugus） | 在服务器上写运维脚本时常这么干：`PW="$(grep -oP '(?<=DB_PASSWORD = ).*' .env)"; mysql -uroot -p"$PW" …`。但本项目 `.env` 是 ThinkPHP 的**分区格式**（`[APP]` `[DATABASE]` `[REDIS]` `[MEDIA]` …），键名是 `PASSWORD` 而不是 `DB_PASSWORD`，且 `[REDIS]` 下也有一个同名 `PASSWORD`。grep 结果必然是空 → `mysql -p""` 退化为**交互式要密码** → 它从 stdin 读，把后面 heredoc 里剩下的脚本**整段吃掉当密码**。表现是：命令不报错，但输出在第 1 条之后戛然而止，后续 UPDATE / 复测一条都没执行，极易误判成「改了没生效」 | 运维脚本里**不要手搓 `.env` 解析**，直接走应用自己的连接：`php -r 'require "vendor/autoload.php"; $app=new think\App(); $app->initialize(); use thinkacade\Db; foreach(Db::query("SELECT …") as $r){…}'`，或用 `php think` 命令。既避开密码提取，也避开库名/端口/字符集不一致。必须用 mysql CLI 时加 `--defaults-extra-file` 或把密码放 `~/.my.cnf`，别从 stdin 喂 |
| **同一份 PHP 在不同客户机上被手工改出多个变体**（11-13 kiss） | 修完上传 404 后核对 md5，发现 kiss 的 `Media.php` 是 `3707a200`，而 heygirl/kelymi/sugus 与仓库都是 `23040c90`：KISS 上的版本注释措辞不同、域名正则写成等价但不同的 `#^[^/]+\.[a-z]{2,}(?:\.[a-z]{2,})?/storage/#i`、并且 `sign_ttl` 的**代码兜底值**是 `21600`/`3600`（仓库是 `691200`/`604800`）。三处都不影响功能（实际值来自 `config/media.php` + `.env`），但**每次同步都会报差异，也会让「线上是不是最新版」无法用 md5 快速判断** | 排查：`md5sum` 四个站的同名文件与仓库比，同一份代码只应有一个 md5。处理：确认功能等价后**直接用仓库版本覆盖**（`cp` 前先 `php -l` 验语法），`install -o www-data -g www-data -m 644` + `systemctl reload php8.3-fpm`。注意 PHP 层**不在 `sync_clients.py` 的跟踪范围内**（它只管 html/js/assets/manifest），所以 PHP 改动必须**手工逐站推 + 手工核 md5**，别指望 `--check` 能发现 |
| **`/storage/` 被 nginx `return 403` 时本地素材全不可读**（11-13 kiss） | kiss 的 `kiss.conf` 里 `location ^~ /storage/ { return 403; }`（假定素材全部走对象存储）。一旦上传回退到本地磁盘，这些文件就永远读不到 | 排查时临时改成 `alias /www/wwwroot/douyin/php/public/storage/`（并加 `Access-Control-Allow-Origin *`），作为回退兜底；彻底修复后再决定是否恢复 403（建议保留可读，因为上传失败时至少不黑屏） |
| **站点同步后品牌被冲回上游**（11-10 kiss 重建） | 对全新服务器首次跑 `sync_clients.py` 时，它会把上游中立版本推过去并覆盖已打品牌的文件，结果标题变回 `Sugus.ai`、品牌全丢。脚本的 `apply_brand` 步骤在黑名单提醒后仍可能未生效 | 同步后**必须手动重跑**：`bash /opt/brand-kit/tools/apply_brand.sh <客户> <站点目录> /opt/brand-kit`，然后验证标题/logo/CDN 引用数。**新机首次同步尤其容易中招**——快照为空导致全量推送，品牌必然被覆盖 |
| **SRS `on_publish` 回调被 301 导致推流失败**（11-10 kiss 重建） | SRS 配的 `on_publish http://127.0.0.1/api/v1/srs/publish`，而 80 端口配了强制跳 HTTPS → 回调收到 301、SRS 判定失败 → `RTMP Input/output error`，worker 无限重启推流，HLS 永远出不来 | 回调改走**不跳转的端口**（如 `http://127.0.0.1:8081/api/v1/srs/publish`）。排查方法：`docker logs srs \| grep http_hooks -A5`，看到 `code=301` 就是这个问题 |
| **cloud 镜像自带 ufw 默认拒绝入站**（11-10 Shinjiru） | 新机只开了 SSH（20203），80/443/8081/1935/1986 全部不可达，但 VM 内部 `ss -ltn` 显示都在监听、`curl 127.0.0.1` 返回 200 → 误判成“商家防火墙”，差点去提工单要 root 密码 | **先查 VM 内部**：`ufw status numbered`。开放：`ufw allow 80/tcp && ufw allow 443/tcp && ufw allow 8081/tcp && ufw allow 1935/tcp && ufw allow 1986/tcp`。判断法：外部全端口不通但内部正常 ≈ 本机防火墙；商家层封锁通常连 ICMP 也一起断 |
| **8080 端口被 SRS 占用**（11-10） | `docker run --network host` 的 SRS 默认监听 8080，导致 nginx CDN 回源口起不来（或冲突） | CDN 回源口用 **8081**；建置前先 `ss -ltn \| grep 808` 确认空闲 |
| **`php/public` 不可写 → API 全面 500**（11-10） | ThinkPHP 需要往 `php/public/debug.log` 写日志，目录属主是解压包带来的非 root 用户 → `error_log(...): Permission denied` → 所有 `/api/*` 返回 500（而 CLI 模式下正常，极难定位） | `chown -R www-data:www-data php/public php/runtime && chmod -R 775`。排查技巧：临时开 `APP_DEBUG=true` 看真实错误，定位后立即关回去 |
| **webman `runtime` 属主 root → kiss-ws 启不来**（11-10） | systemd 以 `www-data` 运行 webman，但 `runtime/` 是解压包带来的 `root:root` → `can not save pid to .../webman.pid` → 服务 `activating (auto-restart)` 死循环 | `chown -R www-data:www-data apps/ws-webman/runtime && chmod -R 775`。验证：`su -s /bin/bash www-data -c 'cd .../ws-webman && php start.php start'` 能看到真实报错 |
| **worker 缺 Python 依赖**（11-10） | `live-worker@*` 全部 failed，日志 `ModuleNotFoundError: No module named 'pymysql'` | `pip3 install pymysql redis`。**Ubuntu 22.04 的 pip 不支持 `--break-system-packages`，直接装即可** |
| **worker 优先走 CDN 拉素材导致全部 404**（11-10） | worker 配了 `MEDIA_ENDPOINT=https://media.kisss.ai` 但素材从未上传到存储服务器 → 每个素材都 `HTTP Error 404`，预转全失败、HLS 不出 | 素材已在本机 `/data/media` 时，**清空 `MEDIA_ENDPOINT`** 让 worker 直读本地：`sed -i 's#^Environment=MEDIA_ENDPOINT=.*#Environment=MEDIA_ENDPOINT=#' /etc/systemd/system/live-worker@.service && systemctl daemon-reload` |
| **Composer 2.10+ 安全公告拦截依赖安装**（11-10） | `paragonie/sodium_compat` 命中 `PKSA-*` 公告 → `composer install` 报 `not installable`，而这些是生产环境既有依赖，升级不在本次范围 | `composer config policy.advisories.block false`。另注意：**阿里云 composer 镜像缺包**（如 `firebase/php-jwt v7`），报 `higher repository priority` 时要 `composer config -g --unset repos.packagist` 回到官方源 |
| **`open_file_cache` 让直播 m3u8 冻住 60 秒**（10-21 kiss） | 10-20 加的「性能调优」`/etc/nginx/conf.d/zz-perf.conf` 里有 `open_file_cache max=20000 inactive=30s; open_file_cache_valid 60s;`。nginx 缓存的是**打开的文件描述符**，而 SRS 写 m3u8 是「写临时文件 + rename」换 inode → nginx 手里的 fd 仍指向旧 inode，**m3u8 最长 60 秒不更新**。播放器拿旧播放列表去请求已被 `hls_cleanup` 删掉的 ts 段 → 404 → 画面卡住反复重试。实测修复前 disk 落后 https 10 段（40 秒） | 两个 HLS location 加 `open_file_cache off;`（只关 HLS，静态资源照旧享受缓存）。**heygirl / sugus / kelymi 也有同一份 zz-perf.conf，同样受影响**，新客户初始化时要一起加 |
| **直播没接 CDN（`HLS_CDN_DOMAIN` 指向自己）**（10-21 kiss） | `.env` 里 `HLS_CDN_DOMAIN = https://kisss.ai` = 源站直连（荷兰机房），大陆拉单段 ts 实测 21 KB/s，而 3.5 Mbps 需要 437 KB/s，差 20 倍 → 完全播不动。参考：sugus=`ever-candy.b-cdn.net`、heygirl=`hey-girl.b-cdn.net` | 建 Bunny Pull Zone（Origin 指向 CDN 专用回源口）→ `.env` 改 `HLS_CDN_DOMAIN` → 重启 php-fpm。核对 `curl -s https://<域名>/api/v1/feed/live \| grep hls_url` 应为 CDN 域 |
| **CDN 回源后真实 IP 丢失 → 全站共用一个限流额度**（10-21 kiss） | `php/config/throttle.php` 按 IP 限流 `120/m`。挂 CDN 后 `$remote_addr` 全是边缘 IP，所有用户共用配额；后台 IP 白名单与日志同样失真 | nginx 加 `real_ip_header X-Forwarded-For; real_ip_recursive on;` + Bunny 边缘 IP 段 `set_real_ip_from`（`/etc/nginx/bunny_realip.conf`，584 条，每月 1 号刷新）。**必须先装 realip 再切 CDN** |
| **MPA 静态资源走 CDN 的零风险做法**（10-21 kiss） | 想让首页也加速，但直接把 `kisss.ai` 的 A 记录指到 CDN 会连带支付回调 `/api/v1/recharge/notify*`、后台 `/admin/`、WebSocket 一起过 CDN，风险高 | 只改 HTML 里的**子资源引用**（`./js/ ./_shared/ ./assets/ ./<页面>_files/` → CDN 域名），页面跳转 `.html`、favicon、`/api/`、`/ws/` 保持同源。登录态是 localStorage + `Authorization` 头，不依赖同源 cookie，不受影响。实现：`apply_brand.sh` 第 3.5 节 + `brand/<客户>/cdn_assets.env`（留空=关闭） |
| **Bunny zone 挂 `/api/` 能通，但 WebSocket 必须 HTTP/1.1**（10-21） | 用 `curl` 测 WebSocket 升级通常拿到 `000`，因为 curl 默认 HTTP/2，而 WS 升级要求 HTTP/1.1 | 加 `--http1.1` 或用 Python 原生 socket 握手；正确结果 `101 Switching Protocols`。Bunny Pull Zone 支持 WS 透传，无需额外开关 |
| **客户站点页面被覆盖后品牌回退成 Sugus**（10-30 heygirl） | `heygirl.chat/ai/Home.html` 的 `<title>` 和侧栏字标又变回 `Sugus.ai`（10-15 / 10-17 之后第三次）。原因是上游中性基线被写回客户站点（单文件上传、回填、`git checkout`、手工 cp 都可能），但没有重跑品牌脚本；而 `Home.html` 本身不引 `js/brand.js`，前台无法自愈 | ① **任何**向客户服务器写 HTML/JS 的动作（`sync_clients.py`、宝塔/IDE 上传、手工 cp、`git checkout`）之后必须立即 `bash /opt/brand-kit/tools/apply_brand.sh <客户>`；② 验收不能只看主域，要覆盖全部别名域名：`for h in heygirl.live heygirl.chat heygirl.cam heygirl.fun heygirl.one heygirl.shop; do printf '%-15s ' $h; curl -s https://$h/ai/Home.html \| grep -o '<title>[^<]*'; done`，并同时核对 `img src=*wordmark*` 与 `class="logo">`；③ heygirl 的 kit 现已内置 `brand/heygirl/assets/brand.js`，`apply_brand.sh` 会**整文件覆盖** `js/brand.js`（不要 sed 它）并把引用刷成 `?v=$STAMP`，绕开 CDN 的 immutable 缓存，给引了 brand.js 的页面兜底；④ 前端还有 `Cache-Control: max-age=60`，修完让用户 `Ctrl+F5` 或等 1 分钟 |

## 七之二、Telegram 深链登录（2026-11-10 四站上线）

### 为什么不用官方 Login Widget

官方 widget 要求 bot 在 BotFather 里登记 **Allowed URLs（域名白名单）**，漏配的域名显示
`Bot domain invalid`；heygirl（5 个域名）、kelymi（4 个域名）维护成本高。改成 **Bot 深链 + 轮询**：

```
点图标 → POST /api/live/telegramStart → 返回 {code, deep_link, browser}
       → 开 t.me/<bot>?start=<code>，用户按 START
       → bot webhook 把 Telegram 身份写进 code
       → 页面每 2.5s POST /api/live/telegramPoll {code, browser} → 拿到 token 自动登录
```

**任何域名天然可用**，不需要 BotFather 域名配置。

### 四层依赖（缺一层就点不动/不跳转）

| 层 | 文件 / 配置 | 说明 |
|---|---|---|
| PHP 控制器 | `app/api/controller/LoginGateway.php` | `telegramStart` / `telegramPoll` / `telegramWebhook` / `telegramLogin` + `throttle()` |
| 验签服务 | `app/live/service/SocialLoginService.php` | `telegramReady()` / `verifyTelegram()`（HMAC-SHA256 + 24h 时效 + Redis nonce 防重放）+ `TELEGRAM_AUTH_TTL` |
| 路由 | `app/api/route/social_login.php` | 4 条 `live/telegram*` |
| 配置 | `php/config/social.php` + 各站 `.env [TELEGRAM]` | `BOT_USERNAME` / `BOT_TOKEN` |
| 身份白名单 | `app/live/service/UserService.php` | `loginWithIdentity()` 的 provider 数组必须含 `'telegram'` |
| bot 回调 | Telegram `setWebhook` | `url=https://<域名>/api/live/telegramWebhook`，`secret_token=sha256(bot_token)[:24]` |

### 各站 bot 清单

| 站点 | bot username | bot id |
|---|---|---|
| sugus | `Sugus_Login_Bot` | 9086006244 |
| heygirl | `HeyGirl_Login_Bot` | 9230426910 |
| kelymi | `Kelymi_Login_Bot` | 8833683498 |
| kiss | `Kisss_Login_Bot` | 9274257832 |

> Bot token 只写各站 `.env`，绝不进仓库。webhook 密钥由 token 派生，不用另存。

### 部署要点（kiss 尤其注意）

1. **上游必须先有代码**：这套 PHP 曾「只在服务器、没进仓库」，被一次
   `chore(sync): 补齐未入库的登录网关` 的整文件覆盖抹掉。功能上线后立刻 commit + push 双 remote。
2. **kiss 后端比上游旧，禁止整文件覆盖**（SOP 9.5）。正确的做法是最小补丁：
   - `config/social.php` 末尾追加 `telegram` 段
   - `route/social_login.php` 追加 4 条路由
   - `UserService.php` 的白名单加 `'telegram'`（kiss 旧版 `issueTokens` 无 `$isRegister` 参数，**不要**跟着加）
   - `SocialLoginService.php` 插 `TELEGRAM_AUTH_TTL` + `telegramReady/verifyTelegram`
   - `LoginGateway.php` 插 `use GuzzleHttp\Client;` + `options()` 的 telegram 条目 + 5 个方法
3. **线上改 git 文件**：kiss 的 `php/` 不在 sync_clients 范围，手工补丁后要
   `git checkout douyin/main -- php/...` 交叉核对，或按 9.5 先追平上游。

### 坑（新增）

| 坑 | 现象 | 规避 |
|---|---|---|
| **Python 写 PHP heredoc 把 `\a` 吃掉**（11-10 kiss） | 用 `<<'PYEOF'`（带引号）仍可能被外层解释；路由里 `'\app\api\controller\...'` 变成 `^Gpp^Gpi\controller\...`（BEL 控制符），报 `ClassNotFoundException: 类不存在: pp pi\controller\LoginGateway` | 写 PHP 里的命名空间用 `chr(92)` 拼，或写成独立 `.py` 文件（不经 heredoc）；写完 **必须** `php -l` + `cat -A` 抽查控制符 |
| **依赖 Set-Cookie 做浏览器绑定在 CDN 后失效**（11-07 sugus） | 用户点登录 → bot 收到 /start 且回复成功 → 网页轮询 47 次全是 `pending`。nginx 日志：`telegramStart` 200、webhook 200、`telegramPoll` 全 80 字节 | SSE 无 cookie 时 CDN/浏览器会丢 Set-Cookie。`telegramStart` 应把随机 `browser` 令牌**放在 JSON 里**返回，`telegramPoll` 用 POST body 回传比对；cookie 只作兜底 |
| **后台「认证方式」列显示英文原文** | TG 注册用户显示 `telegram` 而非 `Telegram`，详情弹窗无「（Telegram）」后缀 | 两处都要改：`app/admin/controller/user/LiveUser.php` 的 `$authTypeText` match 加 `telegram/google/discord/twitter`；后台 SPA bundle（`admin/dist/assets/liveUser-*.js`，**不在 git**）的 `{email:邮箱,...}[auth_type]` 映射表同步加。**后台 dist 重建后补丁会丢，需重打** |
| **测试账号会真实入库** | E2E 脚本用假 Telegram id 跑通后会真的建号（kiss 甚至拿到 user_id=1） | 验证后按 `lp_user_auth.auth_type='telegram' AND auth_key=<测试id>` 反查并清理 `lp_user_auth / lp_user_profile / lp_user`；线上客户库尤其不能留 |

### 自测（每站三证据）

```bash
# ① 代码标记
grep -c telegramStart php/app/api/controller/LoginGateway.php      # ≥1
grep -c telegram php/app/api/route/social_login.php                # 4
grep -c BOT_USERNAME php/.env                                      # ≥1

# ② 运行时：loginOptions 应 enabled=true 且带 bot 名
curl -s https://<域名>/api/live/loginOptions | grep -o '"telegram"[^}]*'

# ③ 深链发码（应返回 code + deep_link + browser 三件套）
curl -s -X POST https://<域名>/api/live/telegramStart

# ④ webhook 防伪造（无/错 secret 头应 403）
curl -s -o /dev/null -w '%{http_code}\n' -X POST https://<域名>/api/live/telegramWebhook -d '{}'
```

## 七之三、Google 登录四站接入（2026-11-13 完成）

### 7.3.1 流程是「服务端授权码 + PKCE」，不是前端 GIS

`SocialLoginService` 走 `https://accounts.google.com/o/oauth2/v2/auth` + `response_type=code`
+ `code_challenge(S256)`，token 交换在服务端完成。

**所以 OAuth 客户端只需填 `Authorized redirect URIs`，`Authorized JavaScript origins` 留空。**
（`main.js` 里那套 `initTokenClient` GIS 组件已被 `window.LiveLoginGateway = true` 短路，是死代码。）

### 7.3.2 前置：Search Console 域名所有权（最容易卡住的一步）

同意屏幕填「已获授权的网域」时，Google 会校验该域名是否在当前账号名下。没有就会报
**"您的首页网址对应的网站未注册到您的名下"**。

两个坑：

| 坑 | 说明 |
|---|---|
| **账号绑错** | 验证文件是 Google 账号级的，Cloud Console 与 Search Console 必须同一账号。11-13 sugus 就因此卡了一轮 |
| **根路径是 SPA fallback** | `https://域名/googleXXX.html` 返回 200 + Vue 后台 HTML（内容对不上 → 验证失败）。**验证文件必须放 `/ai/` 目录**，即用「网址前缀」`https://域名/ai/` 类型 |

验证文件由 `sync_clients` 一起分发（放仓库根，同步后位于 `/ai/` 下）：
`ai-girl-malaysia.com/googleXXXXXXXXXXXXXXXX.html`

```bash
# 自查（走海外出口）
curl -sk https://<域名>/ai/googleXXXXXXXXXXXXXXXX.html   # 应返回 53 字节的 google-site-verification: ...
```

### 7.3.3 四站 Client ID 与回调

| 站点 | GCP project | 回调域名 |
|---|---|---|
| sugus | propane-purpose-514609-i0 | sugus.ai / www.sugus.ai |
| heygirl | heygirl-514610 | heygirl.live（别名已 301 跳主域） |
| kelymi | kelymi | kelymi.autos（其余 3 域名 DNS 不通） |
| kiss | kiss-514612 | kisss.ai / www.kisss.ai |

`.env` 写法（多域名用 `REDIRECT_URIS` 逗号分隔，按请求 origin 自动匹配）：

```ini
[GOOGLE]
CLIENT_ID = xxx.apps.googleusercontent.com
CLIENT_SECRET = GOCSPX-xxx
REDIRECT_URI = https://<主域>/api/live/socialCallback/google
REDIRECT_URIS = https://<主域>/api/live/socialCallback/google,https://www.<主域>/api/live/socialCallback/google
```

**别名域名处理（heygirl 已做）**：nginx 加独立 443 块，别名 301 到主域，
这样同意屏幕只需登记一个网域，免去逐个 Search Console 验证。注意该块必须定义在主 443 块**之前**。

### 7.3.4 验证方法（不消耗真实账号）

```bash
# 1) loginOptions 应 google=enabled:true
curl -s https://<域名>/api/live/loginOptions

# 2) 生成授权 URL（origin 必须由前端上报，CDN 会改写 Host）
curl -s -X POST 'https://<域名>/api/live/socialStart/google' --data-urlencode 'origin=https://<域名>'

# 3) 拿 ② 返回的 authorize_url 直接请求 —— 返回 accounts.google.com 登录页 = 回调已登记
#    redirect_uri_mismatch = 后台漏填该域名
curl -sL -o /dev/null -w '%{http_code}' '<authorize_url>'
```

### 7.3.5 坑（新增）

| 坑 | 现象 | 规避 |
|---|---|---|
| **kiss 缺 PromoService::platformFromUserAgent()**（11-14） | Google/Discord/Telegram 首次登录全部失败，回调 302 后 `socialExchange` 返回 **500**（6808 字节异常页），前端显示 "Authorization failed"。CLI 复现：`Call to undefined method PromoService::platformFromUserAgent()` | kiss 后端旧、`UserService` 是新的。最小补丁：把上游 `PromoService` 的该方法（约 30 行正则表）追加到 kiss 版末尾 |
| **kiss 缺 AgentService 类**（11-14，上一条修完立刻暴露） | 同上 500，报 `Class "app\live\service\AgentService" not found` | `UserService::createUser()` 里 `if (AgentService::ready())` 改为 `if (class_exists(AgentService::class) && AgentService::ready())`。kiss 没接代理体系，跳过即可 |
| **回调成功但前端报 failed** | 看 nginx 日志：`socialCallback` 302（正常）+ `socialExchange` **500/4xx**。真正失败在兑票，不在 Google | 别被 302 骗了，一定查 `socialExchange` 的状态码与 runtime 日志 |
| **CDN 改写 Host 导致域名校验失败** | `socialStart` 报 "Please sign in on the configured site domain" | Bunny 回源把 Host 改成源站 IP。前端 `context()` 上报 `location.origin`，后端按白名单校验（2026-11-13 已实现） |
| **Bunny 吞 Set-Cookie** | 回调拿不到浏览器绑定 cookie | browser 令牌随 JSON 下发，前端存 localStorage，兑换时 body 回传 |

### 7.3.6 新客户接入清单

1. Search Console 验证 `https://<域名>/ai/`（HTML 文件放 `/ai/` 下）
2. 建 OAuth 客户端（Web 应用），只填 Authorized redirect URIs
3. 同意屏幕填 4 项：首页 `/ai/Home.html`、隐私 `/ai/privacy.html`、条款 `/ai/terms.html`、网域 `<裸域>`
4. **Publish App**（Testing 状态只有测试用户能登录）
5. `.env` 写 `[GOOGLE]`，`systemctl reload php8.3-fpm`
6. 按 7.3.4 三条验证
7. **新客户站务必先验一遍建号路径**：`php -r` 跑 `UserService::loginWithIdentity('google','TEST_SUB','t@e.com','T')`，确认没抛异常再交付

## 八、四台服务器与仓库速查

| 项目 | 网站服务器 | brand kit | 客户仓库（本地路径） |
|---|---|---|---|
| heygirl | 172.81.99.11（heygirl.live） | /opt/brand-kit（heygirl） | gitee `heygirl.git`（D:\ever\heygirl，master） |
| sugus | 172.81.98.55（上游生产） | 无需（自己就是 Sugus） | gitee `zhibo.git`（= 上游 douyin 仓库） |
| kelymi | 172.81.99.42（kelymi.autos，nginx 单站点 zhibo.conf） | /opt/brand-kit（kelymi，源码 `.zcode/brand_kit_kelymi/`） | gitee `kelymi.git`（D:\ever\kelymi，master） |
| kiss | 111.90.150.230（kisss.ai，nginx 单站点 kiss.conf，Certbot https；SSH 端口 **20203**） | /opt/brand-kit（kiss，源码 `.zcode/brand_kit_kiss/`，含 `tools/apply_brand.sh`） | gitee `kisss.git`（D:\ever\kiss，master） |
| kiss 回源口 | 111.90.150.230**:8080**（`kiss-cdn-origin.conf`，只放 /ai/ /hls/ /api/ /ws/，/admin/ 与 /storage/ 404） | — | Bunny zone `kisss-live`（含 HLS + /ai/ 静态资源） |

- 四台站点路径均为 `/www/wwwroot/douyin/ai-girl-malaysia.com`，URL 前缀均为 `/ai/`
- sugus 链路走 99.11 跳板（跳板上有 `n99_pull` key 直连 98.55），无需额外凭据
- 线上验证地址：`https://heygirl.live/ai/Home.html` ｜ `http://172.81.98.55/ai/Home.html` ｜ `https://kelymi.autos/ai/Home.html` ｜ **`https://kisss.ai/ai/Home.html`**（kiss 的 IP:80 是 404，别用）
- kiss 的 JS/CSS/图片已改走 `https://kisss-live.b-cdn.net/ai/...`（HTML 与 /api/ /ws/ 仍同源）；关掉整个 CDN 只需删服务器上 `/opt/brand-kit/brand/kiss/cdn_assets.env` 再跑一次 apply_brand
- heygirl 的 kit（`/opt/brand-kit`）除资产外还含 `brand/heygirl/assets/brand.js`：apply_brand 会**整文件覆盖** `js/brand.js` 并把引用版本刷成 `?v=$STAMP`。注意 `Home.html` 不引 brand.js，首页标题/字标只能靠静态替换 → **页面被覆盖后必须重跑 apply_brand**
- 后台 SPA（`/www/wwwroot/douyin/admin/dist`，与站点目录同级）同样由 apply_brand 打品牌：`<title>` → `<客户> Admin`、`login-logo.png`/`logo.png`/`favicon.png` 换客户资产、打包进 JS 的 `sugus-logo-*.png` 内容换客户词标。**后台 dist 重新部署后必须重跑 apply_brand**，否则标签页标题/登录页图标又变回 Sugus（2026-11-06 heygirl 复现，客户反馈「又变 sugus 了」）
- kelymi 备份：同步前全量在 `/root/kelymi_pre_sync_20261018/`（7.1M）；apply_brand 每日回滚备份在 `/tmp/brand_bak_<客户>_<日期>/`
- kiss 服务器上站点目录本身就是 gitee `zhibo.git` 的克隆（HEAD 停在部署点），可用于逐文件核对与回滚

---

*维护约定：本文档随体系演进同步更新（改了流程/加了客户/踩了新坑都要回来改），
文档与 `.zcode/sync_clients.py` 一起提交在 douyin 仓库。*

## 六、发布前自检（2026-10-21 新增）

```bash
python .zcode/sync_clients.py --client heygirl --check     # 只体检，不分发
python .zcode/sync_clients.py --client heygirl --strict    # 有问题就中止分发
```

自检扫 `*.html` + `js/*.js`，分两类报告：

| 类别 | 例子 | 处理 |
|---|---|---|
| **需手工修复**（脚本修不掉） | `uta-api` 埋点（数据会发往原站）、`og:url`/`canonical` 指向别家或不存在的根路径 | 改源码 |
| **自动替换**（apply_brand 会处理） | `candy.ai` / `sugusmail.ai` / `MotionGen` 品牌串 | 无需处理 |

判据来源：10-18~10-20 三次踩坑（品牌被冲、分享文案写成 Candy AI、uta-api 往别家发数据）。
注意：开发注释里的 `/* 照 candy.ai 原版 */` 不算问题，已排除。

---

## 九、跨站功能改动核对（2026-10-21 新增，强制）

> **这是本文件关于「跨站改动核对」的唯一权威章节**（第四节不再重复，只在红线里引用）。
> **任何跨站改动完成前必须过这一节。**

### 9.1 三条独立证据，缺一不可

「文件推过去了」不等于「功能生效了」。每站都要验三个维度，**任一维度只能证明一部分**：

| 维度 | 证明什么 | 不能证明什么 |
|---|---|---|
| ① 代码标记（grep 特征串） | 文件内容确实更新了 | 浏览器是否加载到 |
| ② 资源版本（`?v=` 与发布号一致） | 缓存不会给旧文件 | 后端逻辑是否对 |
| ③ 运行时行为（接口 JSON / DB 值 / 真实扣费） | 端到端确实生效 | 其他站也这样 |

### 9.2 检查清单（每次复制执行）

```bash
# 1) 四站逐站跑，不许抽样、不许"其他应该一样"
for each site in [sugus, heygirl, kisss, kelymi]:
    ① grep  本次改动的特征串（期望值写死，不能只看有输出）
    ② grep  HTML 里的 ?v= 是否等于本次发布号
    ③ 调接口看 JSON 字段 / 查库看配置值

# 2) 有扣费/额度类改动时，必须做一次真实业务流
#    例：额度清零 → 提交 → 核对 返回的 paid_tokens 与 余额差额 一致 → 还原测试数据
```

### 9.3 数据库值优先于代码默认值

凡是通过 `lp_platform_config` / `lp_vip_plan` 可配的项（价格、额度、开关）：

```
生效值 = 库里的值（非空时） > 代码默认常量
```

**所以改完代码默认值必须同时改库**，否则线上仍是旧值。查库命令：

```sql
SELECT `key`, `value` FROM lp_platform_config WHERE `key` IN ('video_gen_price', ...);
SELECT image_daily_limit, image_daily_refill, COUNT(*) FROM lp_vip_plan GROUP BY 1,2;
```

### 9.4 本次（10-21）漏同步实例存档

| 站点 | Outfit 按钮 | Apply Outfit | js 版本 | price | VIP 额度 |
|---|---|---|---|---|---|
| sugus（正确） | 1 | 3 | 20261020c | 30→100 | 5/5 |
| heygirl（漏） | **0** | 3 | **20261018a** | 30 | **3/1×12 + 10/1×12** |
| kisss（漏） | 1 | 3 | 20261020c | 30 | **2/1×24** |
| kelymi（漏） | **0** | **0** | 20261020d | 30 | 5/5 |

根因：10-20 只核对了「代码文件已推送」，没核对 `Image.html` 与 `customVideo.js` 是否真到 heygirl；
套餐额度也只改了 sugus + kelymi，忘了 heygirl / kisss。

### 9.5 新客户站额外前置检查

老客户站可能落后上游好几个版本。动手前先探基线：

```bash
ssh root@<host> 'cd /www/wwwroot/douyin && git rev-parse --short HEAD'
git -C D:/ever/douyin/douyin diff --name-status <服务器HEAD>..HEAD -- php/
```

差异大时按 `docs/kelymi对齐sugus.md` 的九步流程整体追平，**不要只推单个文件**
（会因缺依赖类而 500，kelymi 的 `Class "app\common\library\media\Media" not found` 就是这么来的）。

---

## 十、大陆访问封锁（2026-10-25 四站统一上线）

> 客户要求四个站都禁止大陆访问。此封锁在 **nginx 层**，不属于 sync_clients 的静态站分发范围，
> 改封锁配置 = 直接改各服务器 nginx（备份在 `/root/nginx_bak/cnblock_20261025/`）。

### 10.1 判定链与漏洞复盘

```
访客 → Bunny 边缘(v4 回源) → set_real_ip_from 还原真实 IP(XFF) → geo 名单判 CN → if ($is_cn*) return 403
```

旧 sugus 方案（geoip2 + chnroutes.mmdb）的漏洞：**mmdb 只有 IPv4 段**。手机蜂窝网默认
IPv6 优先，访客 v6 经 XFF 还原后 mmdb 查无记录 → 判不出 CN → 绕过封锁（大陆手机能打开
www.sugus.ai 的事故，2026-10-25）。v2 方案改用 APNIC delegated 数据生成的纯 geo 名单
（v4 8797 段 + v6 2046 段），四站统一。

### 10.2 各机文件清单（全部同源，改动时四台同步）

| 文件 | 作用 |
|---|---|
| `/etc/nginx/cn_geo.conf` | CN v4+v6 段（APNIC 生成，勿手改） |
| `/etc/nginx/bunny_realip.conf` | Bunny 边缘 v4 信任列表（584 条，v6 不需要——源站无 AAAA，回源恒为 v4） |
| `conf.d/00-cn-geo.conf`（客户站）/ `conf.d/00-cn-block.conf`（sugus，含 realip） | geo + map 判定层 |
| `snippets/cn-block-site.conf` / `cn-block-full.conf` | 挂到 server 块：site=豁免 /admin*，full=不豁免 |
| `/root/deploy/{cn_geo.py,bunny_realip.py,update_cn_block.sh}` | 月度刷新脚本（cron `0 4 1 * *`，APNIC latest + Bunny edges） |

变量语义：`$is_cn`=CN 访客；`$is_cn_site`=CN 且非 /admin（**运营在国内，/admin 必须豁免**，
后台自身有 IP 白名单 + 2FA 兜底）。

### 10.3 封锁面（10-25 实施记录）

| 服务器 | 加封锁（snippet） | 明确不封 |
|---|---|---|
| sugus 98.55 | 80 主站 + 8082（10 月既有 if 块，换 v2 判定层） | 8081 / sugus.it.com（后台） |
| heygirl | zhibo.conf 80+443（site）、99.49 HLS 口 + 8081 回源口（full） | heygirl.sbs（后台域） |
| kelymi | zhibo.conf 80+443（site）、8081 回源口（full） | admin.kelymi.autos（.73） |
| kiss | kiss.conf HTTP 站块 + 443（site）、80 default(HLS 回源) + 8080 回源口（full） | kisss.si（后台域） |

### 10.4 验证清单（改封锁后必跑）

```bash
# ① CN 出口（本机即大陆）→ 四站全 403
curl -sk -o /dev/null -w '%{http_code}\n' https://<域名>/ai/Home.html

# ② admin 豁免 → 应 200 不是 403
curl -sk -o /dev/null -w '%{http_code}\n' https://<域名>/admin/

# ③ 非 CN 出口（99.11）→ 应 200
ssh 99.11 curl -sk -o /dev/null -w '%{http_code}\n' https://<域名>/ai/Home.html

# ④ 手机蜂窝网（IPv6）复查——v6 是 10-25 之前的漏洞通道，必须真机验证
```

注意：kiss 的 80 端口是 HLS 明文回源口 + Certbot 404 兜底，验证一律走 https 域名；
heygirl/kelymi 的 nginx listen 绑定具体 IP，本机 curl 测试用 `--resolve <域名>:443:<本机IP>`。

---

## 十一、站点首页后台可配（2026-10-26 sugus 首发）

客户问题：「后台能不能自由设置哪个页面是首页」。已实现：后台选页面，`/` 与 `/ai/` 302 到该页面。

### 11.1 结构

| 层 | 位置 | 说明 |
|---|---|---|
| 后台 | `/admin/live.SiteHome/index?_key=<ADMIN_FLOAT_KEY>` | 下拉选页面（自动扫描站点 `*.html`），保存即生效 |
| 配置 | `lp_platform_config.site_home_page` | 空 = 默认 `Home.html` |
| 状态 | `<站点目录>/.state/home.json` | 后台写入，`www-data` 可写，前台零依赖读取 |
| 前台 | `<站点目录>/index.php` | 读状态 → 302 `/ai/<page>`；默认 `Home.html` |
| 接口 | `GET /api/live/siteConfig` | 返回 `{home_page, default}` |

行为：`/`、`/ai/` 走 index.php；直连 `/ai/X.html` 不受影响；302 而非 301，随时可改回默认。

### 11.2 新客户接入（4 步）

1. 站点目录放 `index.php`（仓库 `ai-girl-malaysia.com/index.php`，同步脚本会带过去）
2. `mkdir <站点目录>/.state && chown www-data:www-data <站点目录>/.state`（后台写状态用）
3. 检查 `.user.ini`：`open_basedir` 必须包含 `/www/wwwroot/douyin/`。sugus 旧值是
   `/www/wwwroot/ai-girl-malaysia.com/:/tmp/`（缺 `/douyin`），会让 index.php 直接
   `No input file specified`（500）
4. nginx（公开 80/443 的 server，即 `/etc/nginx/sites-enabled/douyin`）三处改动：
   - `location = / { rewrite ^ /ai/index.php last; }`（原为 `rewrite ^ /ai/Home.html redirect;`）
   - `location ^~ /ai/ { index index.php index.html; ... }`（原为 `index Login.html index.html;`）
   - 新增：

     ```nginx
     location = /ai/index.php {
         include fastcgi_params;
         fastcgi_param SCRIPT_FILENAME /www/wwwroot/douyin/<站点目录>/index.php;
         fastcgi_param SCRIPT_NAME /ai/index.php;
         fastcgi_pass unix:/run/php/php8.3-fpm.sock;
     }
     ```

   然后 `nginx -t && systemctl reload nginx`。

### 11.3 坑

- **备份文件不能留在 `/etc/nginx/sites-enabled/`**：该目录是 `include ... /*`，`xxx.bak_*` 一样会被加载。
  2026-10-26 就因为 `douyin.bak_*` 里重复的 `limit_req_zone` 让 `nginx -t` 失败、reload 没生效，
  表面看「改了配置没反应」。备份一律放 `/root/nginx_bak/`。
- 生效范围只在公开 vhost；`douyin-8082`（回源口）同步加 `location = /index.php` 只是方便本机排查。
- Bunny 对 HTML 默认缓存 60s（`map $ai_cache`），改完首页最多 1 分钟全边缘生效。

## 十二、流量来源面板（访问 / 注册 / 付费，2026-11-05 四站同步）

客户诉求：后台能看到「哪个平台（X / TikTok / Instagram / Reddit / Threads / YouTube…）带来多少点击、注册、付费」，用于分配投放预算。

### 12.1 数据链路

| 环节 | 实现 |
|---|---|
| 落地访问 | 前台 `js/tracking.js` 每个标签页会话 POST `/api/v1/site/visit` 一次（`sessionStorage` 去重） |
| 入库 | `lp_promo_visit`：平台 / 域名 / Referer / ch / l / px / 广告宏参数 / UA / IP |
| 注册归因 | `lp_user.reg_platform`（首触）；`main.js` / `login-gateway.js` 已提交 `reg_platform` |
| 付费归因 | `lp_recharge_order` 已支付订单，按会员的 `reg_*` 归属 |
| 报表 | `/admin/live.PromoSource/index`（菜单「流量来源」）：平台分布 + 访客/访问/注册率/付费率/金额 |

平台识别优先级：链接参数 `?px=`（渠道配置ID）/ `?ch=` / `?l=` → 推广链接名称关键词 → 像素平台 → Referer 域名 → App 内置浏览器 UA（Instagram / TikTok / FBAN / Twitter 等）→ 直接访问。

> **曝光量（展现）无法从站内采集**：自然流量平台不提供展现数据；付费广告的展现需要接各平台 Marketing API。
> 面板提供的是站内可验证的点击/访客/注册/付费，不虚构曝光。

### 12.2 每个客户上线步骤

```bash
# ① 先跑 SQL（建 lp_promo_visit + 补 reg_platform + 菜单改名，幂等）
mysql -u<user> -p<pwd> <db> < php/sql/upgrade_promo_visit.sql

# ② 正常同步（静态站 + PHP 一起）
python .zcode/sync_clients.py --client kiss --dry-run
python .zcode/sync_clients.py --client kiss
#    其余客户同理：heygirl / sugus / kelymi

# ③ 验证
curl -sk -X POST 'https://<域名>/api/v1/site/visit' \
  -H 'Content-Type: application/json' \
  -d '{"sid":"test-20261105","vid":"v-test","domain":"<域名>","ch":"x","referer":""}'
# 返回 {"code":"00000"} 后，后台「流量来源」应出现 1 次访问（X / Twitter）
```

### 12.3 给客户的使用方式（重点）

- 每个平台单独建一条「渠道配置」，平台选 X / Instagram / Reddit / Threads / YouTube 等（这些平台不要求像素ID）。
- 从该渠道复制推广链接（带 `?px=渠道ID&ch=平台`），分别投放到对应平台的直播 / 视频 / 简介。
- 社媒 App 内置浏览器经常不带 Referer；**不要依赖 Referer，依赖每个平台自己的链接**最准确。
- 老库历史注册没有 `reg_platform` 的，会在平台报表里落到「未填 / 未识别」；新流量开始后逐步补齐。

### 12.4 坑

- `lp_promo_visit` 未建表时，面板不会 500，会提示「访问表尚未创建」，但访问接口写入会静默失败（不影响前台）。
- 访问按访问时间、注册按注册时间、订单按会员注册时间；跨阶段转化率不要用「今天访问 ÷ 今天注册」硬算。
- 新加平台后，`PromoSetting` 的渠道统计 Tab 仍按原来的广告平台列统计（X/Reddit 等不进那几列），以「流量来源」面板为准。

## 十三、前台语言按浏览器语言自适应（2026-11-07 全站）

需求：首次访问按浏览器语言自动展示对应语种；用户手动切过语言后，以用户选择为准，不再自动改。

### 13.1 实现

| 位置 | 说明 |
|---|---|
| `js/shell-new.js` `matchBrowserLang()` | 把 `navigator.languages` 逐个映射到 `LANG_META` 的语种代码（`zh-*` 按地区分简繁、`pt-*`→`pt-BR`、`nb/nn/no`→`no`） |
| `js/shell-new.js` `autoApplyBrowserLang()` | 页面解析时执行：已有 `googtrans` cookie（用户手动选过）直接跳过；否则命中非英文语种就写 `googtrans=/en/<code>` |
| `ensureTranslate()`（原有） | `run()` 里按 `currentLang() !== 'en'` 加载 Google 翻译组件并套用，自动检测复用这条链路 |
| 全部前台 html | `shell-new.js` 版本号统一升到 `v=20261107lang`（51 个页面），避免浏览器命中旧缓存 |

规则：

- 浏览器语言按 `navigator.languages` 顺序取第一个能匹配的语种；匹配不到 → 默认英文。
- `LANG_HIDDEN`（当前只有 `zh-CN`）里的语种不参与自动切换：中文浏览器默认英文。要开放简体自动切换，把 `'zh-CN'` 从 `LANG_HIDDEN` 删掉即可。
- 手动选择优先：语言弹窗/设置页切换都会写 `googtrans`，自动检测见到 cookie 即让位；会话内不再被浏览器语言覆盖。

### 13.2 同步与验证

```bash
python .zcode/sync_clients.py --client heygirl --dry-run
python .zcode/sync_clients.py --client heygirl     # sugus / kelymi / kiss 同理
```

验证（每个客户站抽 1 台）：

1. 浏览器首选语言设为日语 / 马来语，清掉 `googtrans` cookie，打开 `/ai/Home.html`：页面应自动翻译，侧栏语言项显示日本语 / Bahasa Melayu。
2. 手动切到 English 后再刷新：保持英文（cookie 生效，不再被自动改回）。
3. 浏览器语言为中文（`zh-CN`）：保持英文（`LANG_HIDDEN`）。

坑：

- 这是**客户端 Google 翻译**，不是独立语言包：首屏会先出现英文再被翻译，且依赖 `translate.google.com` 可达（大陆出口已被 403 封锁，不受影响）。
- 只改 `shell-new.js` 内容不升版本号会命中旧缓存，表现为「改了没效果」；版本号在 51 个页面里统一替换。

## 十四、kiss 换机后支付恢复（2026-11-14）

客户反馈：支付方式与换服务器前不一致。期望的三条（旧机截图）：

```
Crypto · USDT / BTC (Cregis)
Card · Visa / Mastercard          (AIPowerPay)
HissPay (Visa / Mastercard)
```

### 14.1 根因

11-10 旧机（103.217.252.52）磁盘不可恢复，新机用 **sugus 库**做基线重建（`docs/sql/kiss_建库清空用户.sql`）。
该脚本第 2 节写明 `lp_recharge_package` / `lp_vip_plan` / `lp_fiat_channel` / `lp_recharge_channel`
要「保留行、改成 kiss 的值」，但实际只清了用户、加了 HissPay，其余仍是 sugus 的：

- Token 套餐变成 sugus 的 `199/$10.99、350/$25.99、550/$39.99…`
- sugus 的 MKPay 越南/墨西哥/印度被启用，kiss 原有的 Cregis、AIPowerPay 两条通道不存在
- 证据：同库 `lp_cs_channel` 还是 `sugus-AI / t.me/P_V_sugusai`

### 14.2 已恢复内容（kiss 111.90.150.230）

```sql
-- Token 套餐回到 kiss 原价（与 php/sql/upgrade_recharge_starpay.sql 一致）
UPDATE lp_recharge_package SET tokens=100,  price=10.00,  bonus_percent=0  WHERE id=1;
UPDATE lp_recharge_package SET tokens=350,  price=34.99,  bonus_percent=0  WHERE id=2;
UPDATE lp_recharge_package SET tokens=550,  price=49.99,  bonus_percent=10 WHERE id=3;
UPDATE lp_recharge_package SET tokens=1150, price=99.99,  bonus_percent=15 WHERE id=4;
UPDATE lp_recharge_package SET tokens=2400, price=199.99, bonus_percent=20 WHERE id=5;
UPDATE lp_recharge_package SET tokens=3750, price=299.99, bonus_percent=25 WHERE id=6;

-- 支付方式：只保留截图里的三条，下架 sugus 的
UPDATE lp_fiat_channel SET status=0 WHERE gateway IN ('mkpay','mkpay_vn','mkpay_mx','mkpay_in','skytrade');
UPDATE lp_fiat_channel SET status=1, sort=10 WHERE gateway='cregis';
UPDATE lp_fiat_channel SET status=1, sort=20 WHERE gateway='aipowerpay';
UPDATE lp_fiat_channel SET status=1, sort=30, name='HissPay (Visa / Mastercard)' WHERE gateway='hisspay';
```

`.env` 补两段（密钥取自旧机部署脚本，不入 git）：
`[CREGIS]` 见 `.trae/_deploy_cregis_kiss.py`；`[AIPOWERPAY]` 生产段见 `.trae/_switch_aipowerpay_prod.py`
（`BASE_URL=https://api.aipowerpay.com`，`SITE_DOMAIN=https://kisss.ai`）。改完 `systemctl reload php8.3-fpm`。

Cregis / AIPowerPay 的通道行字段与旧机脚本一致：
`name/gateway/USD/$/usd_rate=1/auto_rate=0/decimals=2/min_amount=0/diamond_rate=100/status=1`。

### 14.3 验证结果

| 项 | 结果 |
|---|---|
| `GET /api/v1/recharge/fiat-channels` | 恰好三条：Cregis → AIPowerPay → HissPay |
| `GET /api/v1/recharge/packages` | `100/10.00、350/34.99、550/49.99、1150/99.99、2400/199.99、3750/299.99` |
| `GET /api/v1/recharge/quote?tokens=100` | `$10.00` |
| HissPay 建单（CLI） | ✅ 返回 `payUrl` |
| MKPay 巴西建单（CLI） | ✅ 返回收银台；但旧截图无此方式，已下架 |
| Cregis 建单 | ❌ `E0001 The IP is not added to the whitelist list 111.90.150.230` |
| AIPowerPay 建单 | ❌ `20001 No payment method available`（`https://kisss.ai` 与 `https://www.kisss.ai` 同结果，排除 `invalid site`） |

> 测试订单均已删除。备份：`/root/kiss_pay_restore_20261114/`（`tables.sql` / `env.bak` / `env.bak2_aipower`）。

### 14.4 卡在商户后台的两步（服务器侧无开关）

1. **Cregis**：客户端 → 项目 → API → IP 白名单，加 `111.90.150.230`（要 2FA）。加完即可建单。
2. **AIPowerPay**：生产账号无可用支付方式。站点域名已审核通过（换别的域名才报 `11000 invalid site`），
   需在商户后台确认卡通道/账户状态，或联系 AIPowerPay。

### 14.5 还没动的遗留项

| 项 | 现状 | 风险 |
|---|---|---|
| 网关回调白名单 | HissPay / AIPowerPay / Cregis 是否绑了旧 IP 未知 | 用户付款后订单不确认；需各商户后台确认新 IP |
| `lp_vip_plan` | 仍是 sugus 的 18 档在售；kiss 旧档 `24.99/49.99/139.99` 是 `status=0` | 会员页档位比换机前少，待客户确认再改 |
| `lp_recharge_channel` | 7 条链上通道仍是 sugus 地址，全部 `status=0` | 直接启用会把钱打进 sugus 钱包；必须先换 kiss 自己的地址 |
| `lp_cs_channel` | 仍是 `sugus-AI / t.me/P_V_sugusai` | 客服导流到 sugus |

### 14.6 教训

- 「按 sugus 内容基线重建」时，**先逐表核对「保留·商业配置」清单再同步页面**：
  `lp_recharge_package` / `lp_vip_plan` / `lp_fiat_channel` / `lp_recharge_channel` / `lp_cs_channel` / `lp_promo_*`。
- 支付方式是否显示只看 `lp_fiat_channel.status`；能付与否要**逐网关真实建单**验证，两者是独立的。
- 换 IP 后网关白名单（Cregis 的 IP、各家的回调 IP）是必查项，建单成功不代表回调能到。
