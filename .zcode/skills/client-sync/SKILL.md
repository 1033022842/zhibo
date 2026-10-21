---
name: client-sync
description: 把 douyin 上游（Sugus）的改动分发到 heygirl / sugus / kelymi / kiss 四台客户站——含静态站同步、品牌重打、发布前自检、客户仓库跟进，以及后端 php/SQL/.env 部署。当用户说「同步到客户」「分发」「上线到 heygirl/kelymi/sugus/kiss」「推一下客户站」「打品牌」「客户站更新了没」「支付要上到两个站」时使用；改动涉及 ai-girl-malaysia.com/ 或 php/ 且要落到客户服务器时也要用。
---

# 客户站分发

四台客户服务器共用一个上游（douyin 仓库 = Sugus 生产），品牌差异收敛在各自的
`/opt/brand-kit`，分发后由 `apply_brand.sh` 幂等打上。**这张卡只负责路由和验收门槛，
流程细节以 `docs/多客户同步SOP.md` 为唯一事实源——动手前先读它。**

## 动手前必做

1. 读 `docs/多客户同步SOP.md`（架构、红线、故障排查都在那里）
2. `git status --short --untracked-files=no -- ai-girl-malaysia.com` 必须为空（脚本会拦截）

## 第一步：判断改了什么（决定走哪条路）

```bash
git status --short --untracked-files=no        # 已跟踪文件的改动
git diff --stat HEAD -- ai-girl-malaysia.com php/
```

| 改动落在 | 走哪条路 | 说明 |
|---|---|---|
| 只有 `ai-girl-malaysia.com/*.html`、`js/`、`assets/`、`_shared/` | **A. 纯静态**（最常见） | sync_clients.py 全自动 |
| 有 `php/`、`sql/`、`.env-example` | **B. 静态 + 后端** | 后端不在工具范围，见 `references/deploy-backend.md` |
| `*_files/` 素材（图片/视频） | **C. 带素材** | 默认不同步，需 `--include-files`，先确认体积 |

若 html 显示为「M」但 `git diff` 为空，是 Windows CRLF 噪声：`git checkout -- ai-girl-malaysia.com/`
还原即可，**不要提交**（提交会产生全站无意义变更）。

## A. 纯静态同步

```bash
# ① 上下游先提交（脚本会拦未提交改动）
git add <具体文件> && git commit -m "feat: xxx" && git push

# ② 预览（务必先跑：确认变更文件数和范围符合预期）
python .zcode/sync_clients.py --client heygirl --dry-run

# ③ 执行：发布前自检 → 推文件 → 打品牌 → 线上验证 → 客户仓库自动提交推送
python .zcode/sync_clients.py --client heygirl
python .zcode/sync_clients.py                      # 全部客户
python .zcode/sync_clients.py --client heygirl --strict   # 自检有问题就中止（发版建议加）
```

有用的参数：

| 参数 | 何时用 |
|---|---|
| `--dry-run` | 每次同步前，看变更清单 |
| `--strict` | 正式发布：自检发现 uta-api 埋点等**手工才能修**的问题就中止 |
| `--full` | 忽略快照全量对比（怀疑服务器漂移时） |
| `--seed-from <commit>` | 服务器已人工部署到某提交时，用它当基线（比 `--init-snapshot` 安全，见踩坑表） |
| `--include-files` | 连 `*_files/` 素材一起推（体积大，默认排除） |

**发布前自检会扫**：`candy.ai` 原站引用、`uta-api` 第三方埋点（把用户数据发往别家）、
`MotionGen`/`sugusmail` 旧品牌串、`og:url`/`canonical` 指向错误。自检输出里的
「apply_brand 会自动替换」可以忽略，「需手工修复」必须处理。

## B. 静态 + 后端（php / SQL / .env）

先做完 A，再读 `references/deploy-backend.md` 按五步走。**后端改动 push 完静态不会生效**。

## 硬验证（每次同步后必跑，这是本卡的核心）

```bash
python .zcode/verify_client.py --client <客户>           # 后端有改动时加 --payment
python .zcode/verify_client.py --client all
```

它用内容级证据替代关键词匹配：`assets/ + _shared/` **108 个文件全量** blob 与上游逐字节比对、
`_shared` 内页引用且文件在、标题/logo 品牌、上游品牌泄漏、嵌套垃圾目录、
（`--payment`）MKPAY 配置段/网关连通/回调路由。**退出码非 0 = 同步没完成，先排查。**

任何 FAIL 都要如实报告，不要用「应该没问题」收尾。

## 已知陷阱（详见 SOP 踩坑表）

- **别信「线上验证 OK」**：曾因推送落进嵌套目录而假通过 → 必须跑 verify_client.py
- **`--init-snapshot` 危险**：记的是 HEAD，会把没推过的变更也标成已同步；
  服务器已人工部署时用 `--seed-from <那个提交>`
- **sugus 有 CN 区域封锁**：从大陆 curl 拿到的是 403 区域页，看着像旧内容 →
  从服务器本机或境外出口验证（verify_client.py 已对 sugus 走跳板处理）
- **新增顶层静态目录**：要同步改 `in_scope()`，否则永久漏推（`_shared/` 踩过一次）
- **别手改客户服务器**：会被下次同步冲掉；客户差异要么提上游，要么进 brand kit
- **凭据**：只在 `.zcode/sync_clients.py` 的 `CLIENTS` 里，不要复制到文档、提交信息或对话输出

## 客户速查

| 客户 | 服务器 | 品牌 | 域名 | 备注 |
|---|---|---|---|---|
| heygirl | 172.81.99.11 | heygirl | heygirl.live | 有 privacy/terms 专属页，同步后需补页脚合规链接 |
| sugus | 172.81.98.55（经 99.11 跳板） | 无（即上游） | www.sugus.ai | CN 区域封锁 |
| kelymi | 172.81.99.42 | kelymi | kelymi.autos | |
| kiss | 103.217.252.52 | kiss | kisss.ai | `exclude` 换脸页；80 端口 404 必须走 https |
