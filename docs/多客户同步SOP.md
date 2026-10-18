# 多客户同步 SOP（douyin 上游 → 3 个客户项目）

> 工具：`.zcode/sync_clients.py`（2026-10-18 建立，方法来自 heygirl 两轮同步实战）
> 架构方案全文：heygirl 仓库 `docs/多客户品牌隔离与上游同步方案.md`

## 一、总原则（必须遵守）

1. **douyin 仓库 = 唯一功能上游**，功能改动只在这里做、提交 git
2. **客户服务器永不从别处 rsync 整目录**（10-15/10-17 两次冲掉 heygirl 品牌的事故根源，`pull_from_172.sh` 已停用）
3. **客户品牌不在业务文件里**：客户服务器的品牌由各自 brand kit（`/opt/brand-kit`）的 `apply_brand.sh` 在每次分发后幂等打上
4. **同步 = 内容对齐不是 git merge**（客户仓库独立 init，无共同历史）：`git checkout douyin/main -- ai-girl-malaysia.com/`
5. **同步后客户仓库必须跟进提交**（防止「只改服务器不进仓库」漂移——上次事故的另一半根源）

## 二、日常同步（改完 Sugus 功能后分发给客户）

```bash
cd D:\ever\douyin\douyin
git add -A && git commit -m "feat: xxx" && git push    # ① 源头先提交（脚本会检查）

python .zcode/sync_clients.py --client heygirl --dry-run   # ② 预览变更清单
python .zcode/sync_clients.py --client heygirl             # ③ 执行：推文件 → 服务器打品牌 → 线上验证 → 客户仓库跟进提交推送
python .zcode/sync_clients.py                              #    全部客户（heygirl / sugus / kelymi）
python .zcode/sync_clients.py --client heygirl --full      #    忽略快照全量对比（首次或怀疑漂移时）
python .zcode/sync_clients.py --client heygirl --include-files   # 连 *_files/ 素材一起推（默认排除，素材目录几十万文件）
python .zcode/sync_clients.py --client heygirl --init-snapshot   # 只记快照不推送（服务器已人工同步过时预热用）
```

脚本每次同步会记录 git blob 快照（`.zcode/.sync_state_<客户>.json`），下次只推**真正变更**的文件——
部署清单 = `*.html + js/*.js + site-manifest.json + assets/` 中有变更的文件，**不会再漏 assets**（第二轮同步踩过的坑）。

## 三、新客户一次性初始化（kelymi 已于 2026-10-18 照此初始化）

### A. 客户仓库（gitee 私有仓库）
1. `git clone` douyin 仓库为初始基线，删掉与部署无关引用后作为客户仓库首提交
2. 建 `brand/<client>/`（manifest.env + assets：favicon/touch/wordmark(+2x)/icon192/share_card/manifest/svg）
3. 建 `tools/apply_brand.sh`（从 heygirl 仓库拷，改正则目标值）
4. **业务文件保持中性**（= 与上游完全一致，`git diff douyin/main -- ai-girl-malaysia.com` 应为空）：
   html 里出现 Sugus 文案属预期（上游本身就是 Sugus 生产，品牌只在客户服务器上打）；
   要零残留的是**客户自己的品牌词**（kelymi 仓库 html 不应出现 kelymi）

> kelymi 客户仓库已建（2026-10-18，本地 `D:\ever\kelymi`，origin=gitee kelymi.git，douyin=上游 zhibo）：
> douyin 本地克隆为基线 + brand kit 提交（init 88af874），master 分支，完整继承上游历史；
> `tools/apply_brand.sh` 需 `git add -f`（上游 .gitignore 全局忽略 `tools/`）。
> brand kit 源码在 douyin 仓库 `.zcode/brand_kit_kelymi/`，与服务器 /opt/brand-kit 同源。

### B. 客户服务器
1. 站点目录部署（首次全量，后续走 sync_clients）
2. `mkdir -p /opt/brand-kit`：上传 `tools/apply_brand.sh` + `brand/<client>/`（与客户仓库保持同步）
3. 跑一遍 `bash /opt/brand-kit/tools/apply_brand.sh <client>`，浏览器验证品牌
4. 在 `sync_clients.py` 的 `CLIENTS` 里补齐该客户配置（host/凭据/site/brand/repo 路径）

### B'. kelymi 初始化实战经验（2026-10-18，供第四个客户参考）
- **替换规则从实测 diff 提炼**，不靠拍脑袋：找服务器上次人工同步对应的上游提交
  （`git log --until=<同步时间>`），`git show <c>:ai-girl-malaysia.com/Home.html` 与服务器文件
  difflib 逐行对齐，32 处变更即完整替换表（含 2 处版本号噪声）
- **`js/brand.js` 整文件由 kit 覆盖**（不要 sed）：它是运行时域名切换器，
  DOMAINS 数组/DEFAULT_BRAND/window 全局/正则四处联动，逐条 sed 极易漏；
  代价是上游改 brand.js 逻辑时 kit 版会静默过时——manifest.env 里有提醒
- **localStorage key 也是品牌面**：人工基线把 `'sugus.attr/chat_price/promo.*/site.cfg.v2/appdl.dismissed`
  全换成了 kelymi.*，规则一条搞定：`s/'sugus\./'kelymi./g`（引号内前缀，不会误伤裸文本）
- **sed 匹配字面正则文本要转义中括号**：上游 JS 里的媒体域名白名单是字面量 `sugus\.[a-z.]+`，
  模式必须写成 `sugus\\\.\[a-z.\]\+`（`\[` 转义），否则 `[` 匹配不上、规则静默不生效
- **manifest 图标尺寸如实声明**：kelymi favicon 实际 500x500（人工版错标 260x260 且引用了
  不存在的 v3 文件），kit 版直接声明 500x500，无独立 192 资产就不写 192 条目
- 服务器资产就地取材：kelymi 的 png 本来就在站点目录里，`cp` 进 kit 即可，不必回传本地

### C. heygirl 特有文件的先例（客户可以有，同步不会被冲掉）
- `privacy.html` / `terms.html`（合规页）：上游没有，`git checkout` 不会删
- `Home.html` 页脚合规链接：每次同步后脚本不自动恢复——**heygirl 仓库做同步时注意补**
  （后续可把「页脚注入」挪进 apply_brand.sh，彻底免手工）

## 四、踩坑记录（新增坑请补在这里）

| 坑 | 现象 | 规避 |
|---|---|---|
| rsync 整目录从 Sugus 拉 | 客户品牌/配置整体被冲（两次事故） | 已禁用 pull_from_172.sh；素材(/data/media)除外 |
| 部署清单漏 assets/ | HTML/JS 新了但 fy.css 旧，底栏样式不生效 | 脚本按 blob 快照全目录对比，天然覆盖 |
| Windows CRLF 假差异 | 服务器文件 md5 永远和本地对不上 | 别用 md5 判断版本；用 git blob hash（脚本内置）或内容 diff（`core.autocrlf=true`，推的是工作区字节，线上 css/js 带 CRLF 但浏览器无碍） |
| 只改服务器不进仓库 | 下次事故没人知道改过什么 | 脚本同步后自动在客户仓库提交推送 |
| 客户仓库无共同历史 | `git merge douyin` 报 unrelated histories | 一律用 `git checkout douyin/main -- <目录>` 内容对齐 |
| nginx admin server 兜底 404 | 2FA 二维码库 /static/js 404 | 新静态路径要在对应 server 显式放行 |
| **`_shared/` 不在推送范围**（10-18 发现） | 456b178 抽共享资源到 `_shared/`，52 个内页引用它，但 in_scope 只认 js/assets → 页面 JS/CSS 整页 404；且快照已记 hash，增量永远补不上 | in_scope 已加 `_shared/`；新增顶层目录时要同步扩 in_scope |
| **upload() 远程路径双前缀 bug**（10-18 发现） | `rel` 带 `ai-girl-malaysia.com/` 前缀直接 join site，全部推进 `site/ai-girl-malaysia.com/` 嵌套目录，真实站点零变更；而「线上验证」读的是旧文件照样通过 | upload() 已剥前缀；**线上验证必须配 blob hash 硬校验**（对 assets/_shared 等不打品牌的文件 `git hash-object` 比对），不能只信关键词验证 |
| **快照预热 ≠ 已同步**（10-18 发现） | `--init-snapshot` 把 HEAD 记成已推送，后续增量全部跳过，服务器实际停在旧提交 | init-snapshot 只在「确认服务器与 HEAD 一致」时用；怀疑时删 `.sync_state_<客户>.json` 强制全量 |
| **SSH 高频建连被限流**（10-18 遇到） | kelymi 短时间几十次 paramiko 建连后，banner 正常但会话密钥交换后被掐（EOFError），网站不受影响 | 等几分钟自动恢复；诊断脚本合并命令、减少建连次数；OpenSSH 客户端可正常握手可区分「sshd 挂了」和「会话限流」 |
| **_files 素材不同步 → 资产级品牌残留** | Chat.html 引用 `Chat_files/allow-permissions-sugus-<hash>.png`（教学图，画面含 Sugus 品牌），服务器 _files 里没有 → 404；就算有也是 Sugus 画面 | 文本 sed 管不了二进制；需要时按客户替换素材后 `--include-files` 单独补推，或后续把此类图挪进 brand kit |

## 五、三台服务器速查

| 项目 | 网站服务器 | brand kit | 仓库 |
|---|---|---|---|
| heygirl | 172.81.99.11（heygirl.live） | /opt/brand-kit（heygirl） | gitee autumn_birds_in_the_moon_sea/heygirl（本地 D:\ever\heygirl） |
| sugus | 172.81.98.55（上游生产） | 无需（自己就是 Sugus） | gitee autumn_birds_in_the_moon_sea/zhibo |
| kelymi | 172.81.99.42（kelymi.autos，nginx 单站点 zhibo.conf） | /opt/brand-kit（kelymi，源码 `.zcode/brand_kit_kelymi/`） | gitee autumn_birds_in_the_moon_sea/kelymi（本地 D:\ever\kelymi） |

kelymi 备份：同步前全量 `html/js/manifest` 在服务器 `/root/kelymi_pre_sync_20261018/`（7.1M）；
apply_brand 每日回滚备份在 `/tmp/brand_bak_kelymi_<日期>/`。
