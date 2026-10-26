# 站点首页可配（.zcode/sitehome）

后台「站点设置 → 站点首页」可以把 `/` 与 `/ai/` 指到站点任意 `.html`。
本目录只放**服务器侧接线脚本**，业务代码在仓库里：

| 文件 | 作用 |
|---|---|
| `ai-girl-malaysia.com/index.php` | 站点入口：读 `.state/home.json` → 302；默认 `Home.html` |
| `php/app/admin/controller/live/SiteHome.php` | 后台页：扫描站点页面、保存配置 |
| `php/app/api/controller/Live.php` `siteConfig()` | `GET /api/live/siteConfig` |
| `php/route/live.php` | 路由注册 |
| `.zcode/sync_clients.py` | `in_scope()` 已放行站点根 `index.php` |

## 上线步骤（按 SOP）

1. php 代码按常规发布流程上服务器（`SiteHome.php` / `Live.php` / `route/live.php`）
2. 跑接线脚本（幂等，会先检查 sites-enabled 里有没有 `.bak`）：

   ```bash
   set SITEHOME_SSH_HOST=172.81.98.55
   set SITEHOME_SSH_USER=root
   set SITEHOME_SSH_PASS=***
   python .zcode/sitehome/apply_clients.py --site sugus --dry-run
   python .zcode/sitehome/apply_clients.py --site sugus
   ```

3. 后台验收：`/admin/live.SiteHome/index?_key=<ADMIN_FLOAT_KEY>`，选页面 → 保存 → 刷新 `/`
4. 同步：`python .zcode/sync_clients.py`（`index.php` 会随之分发到各客户站点）

## 脚本改了什么

- 站点目录：建 `.state/`（`www-data` 可写）、上传仓库 `index.php`
- `.user.ini`：`open_basedir` 补成 `/www/wwwroot/douyin/:/tmp/`
  （sugus 旧值缺 `/douyin`，会让 `index.php` 直接 `No input file specified`）
- 公开 vhost：`location = /` → 内部转 `/ai/index.php`；`/ai/` 的 index 顺序 `index.php` 优先；
  新增 `location = /ai/index.php`
- 回源 vhost（8082）：新增 `location = /index.php`（只为本机排查）
- 备份：`/root/nginx_bak/sitehome_<日期>/`、`*.bak_sitehome`

## 坑

- **`/etc/nginx/sites-enabled/` 里的 `.bak` 会被 include**：2026-10-26 就因重复
  `limit_req_zone` 让 `nginx -t` 失败、reload 没生效。脚本开跑前会直接拦下来。
- 只影响 `/` 和 `/ai/`；直连 `/ai/X.html` 不受影响，老链接与投放统计不断。
- 用 302 不用 301，随时可「恢复默认」回到 `Home.html`。
- Bunny 对 HTML 默认缓存 60s（`map $ai_cache`），改完最多 1 分钟全网生效。
