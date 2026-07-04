# AI 直播平台启动指南

## 概述

本项目包含 5 个运行时进程 + 2 个外部依赖，需要按顺序启动：

| 序号 | 进程 | 路径 | 端口 | 命令 | 必需 |
|------|------|------|------|------|------|
| 1 | MySQL | 系统服务 | 3306 | 确保服务运行 | ✅ |
| 2 | Redis | 系统服务 | 6379 | 确保服务运行 | ✅ |
| 3 | ThinkPHP 后端 | `php/` | 8000 | `php think run` | ✅ |
| 4 | WebSocket | `apps/ws-webman/` | 8788 | `php windows.php` | ✅ |
| 5 | Vue 前端 | `vue/` | 3000 | `pnpm dev` | ✅ |
| 6 | AI 女友前端 | `ai-girl-malaysia.com/` | 8080 | `npx serve` | ✅ |
| 7 | 管理后台前端 | `php/web/` | 动态 | `pnpm dev` | 按需 |
| 8 | channel-worker | `services/channel-worker/` | CLI | `php bin/channel-worker.php` | HLS直播需要 |

---

## 前置条件（仅首次）

### 1. 安装依赖
```bash
# PHP 后端
cd php
composer install

# WebSocket
cd apps\ws-webman
composer install

# Vue 前端
cd vue
pnpm install

# 管理后台前端（可选）
cd php\web
pnpm install
```

### 2. 配置 .env
```bash
cd php
copy .env-example .env
# 编辑 .env，配置数据库连接和 Redis 连接
```

### 3. 数据库初始化
在 MySQL 中创建 `live_platform` 数据库，执行 SQL：
```sql
source php/sql/upgrade_persona_ai.sql
-- 如果有完整建表 SQL，也执行：
-- source php/sql/live_platform.sql
```

---

## 日常启动（开 5 个终端）

### 终端 1 — ThinkPHP 后端 API
```bash
cd d:\ever\douyin\douyin\php
php think run -H 127.0.0.1 -p 8000
```
> 验证：浏览器打开 http://127.0.0.1:8000

### 终端 2 — WebSocket 实时服务
```bash
cd d:\ever\douyin\douyin\apps\ws-webman
php windows.php
```
> 启动后会显示 `WebSocket server listening on ws://0.0.0.0:8788`
> 有 Redis 连接报错不影响启动，但弹幕/礼物功能需要 Redis

### 终端 3 — Vue 前端 H5
```bash
cd d:\ever\douyin\douyin\vue
pnpm dev
```
> 访问：http://localhost:3000
> Vite 已配置代理：`/api` → `127.0.0.1:8000`，`/hls` → `127.0.0.1:8000`

### 终端 4 — AI 女友前端
```bash
cd d:\ever\douyin\douyin\ai-girl-malaysia.com
npx serve -p 8080
```
> 访问：http://127.0.0.1:8080/Login.html
> **为什么不用 PHP built-in server？** PHP 单线程会死锁，AI 前端只是静态文件，用 `npx serve` 最合适。
> AI 前端 JS 直接跨域调用 8000 端口的 ThinkPHP API，不经过代理。

### 终端 5（可选）— 管理后台前端
```bash
cd d:\ever\douyin\douyin\php\web
pnpm dev
```

---

## 启动后验证清单

### 基础功能验证
- [ ] `http://localhost:3000` — 抖音前端首页能打开
- [ ] `http://localhost:3000/home/live?roomId=2` — 直播页能加载，未登录会跳转到 AI 登录页
- [ ] `http://127.0.0.1:8080/Login.html` — AI 前端能打开
- [ ] `http://127.0.0.1:8000/api/live/login` — 后端 API 可达（POST 测试）

### AI 对接功能验证
- [ ] `POST /api/live/registerFromAi` — 注册（username + email + password）
- [ ] `POST /api/live/login` — 登录（account + password）
- [ ] `POST /api/live/customRoleOne` — 创建角色（需 Bearer Token）
- [ ] `POST /api/live/customOneList` — 我的角色列表
- [ ] `POST /api/live/upload` — 上传角色封面图

### 管理后台验证
- [ ] 管理后台打开 Persona 列表，能看到新字段"所属用户"
- [ ] 新建/编辑 Persona，状态可选"禁用/准备中/启用"
- [ ] 管理员创建 Room 时可选择 Persona

### AI 前端验证
- [ ] `http://127.0.0.1:8080/Login.html` — 能注册/登录，JWT 存入 localStorage（key: `live_access_token`）
- [ ] 角色创建流程（Girls.html → 选择属性 → summary.html）— 提交后 charactersIndex.html 看到"准备中"
- [ ] charactersIndex.html — 角色卡片显示"📹 历史切片"按钮（有切片数据时弹窗播放）
- [ ] 管理后台给角色创建 Room 并启用 → AI 前端显示"进入直播间"可点击

### 跨项目登录验证
- [ ] AI 前端登录后 → 打开 `http://localhost:3000/home/live?roomId=2` → 自动识别登录态，不需重新登录
- [ ] Vue 前端未登录时访问直播间 → 自动跳转到 AI 前端 Login.html → 登录成功后回跳直播间

---

## 常见问题

**Q: `php think run` 报数据库连接失败？**
检查 `php/.env` 中的 `DATABASE` 配置是否正确。

**Q: WebSocket 启动报 Redis 连接失败？**
Redis 没启动或没安装，不影响 WebSocket 启动，但发弹幕/送礼需要 Redis。

**Q: 前端接口 404？**
确保 ThinkPHP 和 Vue 都启动了，Vite 的 proxy 会将 `/api` 转发到 8000 端口。

**Q: HLS 直播看不到画面？**
需要额外启动 SRS + channel-worker，不在本指南范围内（参考 `docs/CLOUD_DEPLOY_MANUAL.md`）。

**Q: git 推不上去？**
检查 `*.zip` 文件是否已加入 `.gitignore`，大文件（>100MB）GitHub 会拒绝。
