# client_overrides —— 客户差异化页面

某些页面只在个别客户上需要**完全不同的实现**时，把客户版放在这里；
上游 `ai-girl-malaysia.com/` 里的同名文件继续只服务其余客户。

## 当前配置

| 变体目录 | 应用客户 | 覆盖文件 | 说明 |
|---|---|---|---|
| `image-sugus/` | sugus | `Image.html` | 新版图片页，仅 sugus |
| `image-heygirl/` | heygirl | `Image.html` | 新版图片页，仅 heygirl |

谁在用哪份，永远以 `.zcode/sync_clients.py` 里各客户的 `"overrides"` 字段为准：

```bash
grep -n overrides .zcode/sync_clients.py
```

## 目录规则

- 目录结构 = **站点根镜像**（不是 `ai-girl-malaysia.com/` 镜像）：
  `client_overrides/image-sugus/Image.html` → 站点 `/Image.html`
  `client_overrides/image-sugus/js/foo.js` → 站点 `/js/foo.js`
- 以 `.` 开头的文件（如 `.gitkeep`）不会发布
- 覆盖文件不在 git blob 快照里，每次同步都会重传（同尺寸自动跳过），请保持体积小
- 没有配 `overrides` 的客户（kelymi、kiss）继续用上游基线，改这里不会影响它们

## 分发时脚本自动做什么

`python .zcode/sync_clients.py --client sugus` 会：

1. 把被覆盖的路径（如 `ai-girl-malaysia.com/Image.html`）从本次推送清单剔除，不推上游版；
2. 增量文件推完后补传覆盖文件（跳板 / 直连两条链路都支持）；
3. 直连客户再跑 `apply_brand.sh`，品牌替换作用在覆盖页上；
4. 客户仓库 `git checkout douyin/main -- ai-girl-malaysia.com/` 之后，把覆盖文件写回仓库再提交，
   保证「服务器 = 客户仓库」；
5. 发布前自检一并扫覆盖文件（candy.ai / uta-api / 品牌串 / canonical）。

## 红线

- 改 sugus / heygirl 的 `Image.html` **只能改对应变体目录**；
  改上游 `ai-girl-malaysia.com/Image.html` 只会影响 kelymi / kiss。
- 不要手改客户服务器上的覆盖页，也不要在客户仓库里手敲
  `git checkout douyin/main -- ai-girl-malaysia.com/Image.html`（会把客户版冲掉）。
- 新增变体或调整应用客户时，同步更新：本 README 表格、`docs/多客户同步SOP.md`、
  `.zcode/skills/client-sync/SKILL.md`。

## 协作约定（Gitee）

- 只有一个仓库、一条 `main`，**不建客户分支、不建客户专属仓库放差异化页面**。
- `main` 设为保护分支，改动走 Pull Request；PR 描述注明「是否改动了 `client_overrides/`」。
- 同事拉取 `main` 后，打开上游同名文件会看到头部注释，指向本目录，避免误改。
