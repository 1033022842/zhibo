# 多客户同步 SOP（douyin 上游 → 4 个客户项目）

> **给使用者的 30 秒版本**：功能只改 douyin 仓库 → 提交 → 跑一条同步命令，脚本自动把变更推到客户服务器、打上客户品牌、线上验证、客户仓库跟进提交。**永远不要直接 rsync/手改客户服务器。**
>
> 工具：`.zcode/sync_clients.py` ｜ 建立于 2026-10-18（方法提炼自 heygirl 两轮同步实战）
> 架构方案全文：heygirl 仓库 `docs/多客户品牌隔离与上游同步方案.md`
> 服务器密码等凭据：都在 `sync_clients.py` 的 `CLIENTS` 配置里，本文不重复

---

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
172.81.99.11      172.81.98.55            172.81.99.42      103.217.252.52
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
部署范围 = `*.html + js/ + assets/ + _shared/ + site-manifest.json`，`*_files/` 素材目录默认不同步。

### 变体参数

| 命令 | 用途 |
|---|---|
| `--client <name> --dry-run` | 只看会推哪些文件，不动任何东西 |
| `--client <name> --full` | 忽略快照全量对比（首次或怀疑漂移时） |
| `--client <name> --include-files` | 连 `*_files/` 素材一起推（默认排除，素材几十万文件） |
| `--client <name> --init-snapshot` | 只记快照不推送——**仅在确认服务器已与 HEAD 一致时用**（误用=服务器永远停在旧版，见踩坑表） |
| `--client <name> --seed-snapshot-from <rev>` | 服务器已部署 `<rev>`（不是 HEAD）时用它做基线：首次只推 `rev..HEAD` 的变更。**新客户首同步正确姿势**：先 `git log --oneline` 在服务器上确认站点来自哪个提交 |

### 同步后验证清单（别只看脚本输出 OK）

脚本的关键词验证可能**假通过**（读的是旧文件/CDN 缓存，10-18 真实发生过）。关键变更按此清单复核：

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

> **kiss 必须用域名验证**：`http://103.217.252.52/...` 是 Certbot 写的 80 端口
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

- `kisss.ai` / `www.kisss.ai` 已解析到 103.217.252.52，Certbot 证书生效（2026-10-20 → 2027-01-18，自动续）
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
ssh root@103.217.252.52 'cd /www/wwwroot/douyin && git rev-parse --short HEAD'

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
| **`--init-snapshot` 用在「服务器落后上游」= 灾难**（10-20 kiss） | 新客户服务器 HEAD 常比上游旧（kiss 停在 `7a38a72`，上游已到 `8363173`）。`--init-snapshot` 记的是上游 HEAD，把没推过的变更全标成已同步，之后增量永远推不动 | 用 `--seed-snapshot-from <服务器实际HEAD>` 记基线，`--dry-run` 确认清单 = 服务器 HEAD..上游 HEAD 的变更后再真跑 |
| **kiss 的 80 端口被 Certbot 改成 `default_server { return 404; }`**（10-20） | `curl http://103.217.252.52/ai/Home.html` 永远 404，容易误判成「同步没生效/站点挂了」；IP + `Host: kisss.ai` 只会 301 到 https | 一律用 `https://kisss.ai/ai/...` 验证；`sync_clients.py` 里 kiss 的 `verify_url` 也必须是域名 |

## 八、四台服务器与仓库速查

| 项目 | 网站服务器 | brand kit | 客户仓库（本地路径） |
|---|---|---|---|
| heygirl | 172.81.99.11（heygirl.live） | /opt/brand-kit（heygirl） | gitee `heygirl.git`（D:\ever\heygirl，master） |
| sugus | 172.81.98.55（上游生产） | 无需（自己就是 Sugus） | gitee `zhibo.git`（= 上游 douyin 仓库） |
| kelymi | 172.81.99.42（kelymi.autos，nginx 单站点 zhibo.conf） | /opt/brand-kit（kelymi，源码 `.zcode/brand_kit_kelymi/`） | gitee `kelymi.git`（D:\ever\kelymi，master） |
| kiss | 103.217.252.52（kisss.ai，nginx 单站点 kiss.conf，Certbot https） | /opt/brand-kit（kiss，源码 `.zcode/brand_kit_kiss/`，含 `tools/apply_brand.sh`） | gitee `kisss.git`（D:\ever\kiss，master） |

- 四台站点路径均为 `/www/wwwroot/douyin/ai-girl-malaysia.com`，URL 前缀均为 `/ai/`
- sugus 链路走 99.11 跳板（跳板上有 `n99_pull` key 直连 98.55），无需额外凭据
- 线上验证地址：`https://heygirl.live/ai/Home.html` ｜ `http://172.81.98.55/ai/Home.html` ｜ `https://kelymi.autos/ai/Home.html` ｜ **`https://kisss.ai/ai/Home.html`**（kiss 的 IP:80 是 404，别用）
- kelymi 备份：同步前全量在 `/root/kelymi_pre_sync_20261018/`（7.1M）；apply_brand 每日回滚备份在 `/tmp/brand_bak_<客户>_<日期>/`
- kiss 服务器上站点目录本身就是 gitee `zhibo.git` 的克隆（HEAD 停在部署点），可用于逐文件核对与回滚

---

*维护约定：本文档随体系演进同步更新（改了流程/加了客户/踩了新坑都要回来改），
文档与 `.zcode/sync_clients.py` 一起提交在 douyin 仓库。*
