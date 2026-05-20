# 当前项目云服务器部署操作手册

## 1

当前仓库不是一个“单体包上传后直接双击启动”的项目，而是由 4 个部分组成：

- `php`：ThinkPHP 8 业务 API + BuildAdmin 后台入口
- `php/web`：BuildAdmin 管理后台前端源码，打包构建后需要同步到 `php/public`目录下
- `vue`：用户侧 H5 页面，构建后是纯静态站点
- `apps/ws-webman`：Webman WebSocket 服务，当前监听 `8787` 和 `8788`

如果你的目标是“先把项目丢到云服务器上看看效果”，推荐采用下面这套最稳妥方案：

- `MySQL + Redis + Nginx + PHP-FPM + Supervisor`
- `php` 用 `Nginx + PHP-FPM` 跑
- `vue` 构建成静态文件，由 `Nginx` 直接托管
- `apps/ws-webman` 用 `Supervisor` 守护
- 首次上云只看页面和 HLS 效果时，`SRS` 可以先不装

说明：

- 当前 H5 前端接口是相对路径 `/api/...`
- 当前 H5 播放地址优先走 `HLS`，其次尝试 `WebRTC`
- 如果只是先看页面和基础播放效果，`HLS` 足够
- 如果你后面要看低延迟 `WebRTC/WHEP`，再补 `SRS`

---

## 2. 推荐部署拓扑

建议至少准备 1 台 Linux 云服务器，系统建议：

- Ubuntu 22.04 LTS
- 2C4G 起步
- 40G 以上磁盘

建议使用 3 个域名或子域名：

- `h5.example.com`：用户 H5
- `admin.example.com`：后台管理
- `ws.example.com`：WebSocket，可选

也可以只用一个域名，但首次部署更容易绕进静态资源和路由问题。分域名最省心。

---

## 3. 服务器依赖准备

### 3.1 系统软件

安装以下软件：

- `nginx`
- `mysql-server`
- `redis-server`
- `supervisor`
- `git`
- `unzip`

### 3.2 PHP 运行环境

建议使用 `PHP 8.2` 或 `PHP 8.3`，至少安装这些扩展：

- `bcmath`
- `gd`
- `iconv`
- `mbstring`
- `pdo_mysql`
- `openssl`
- `fileinfo`
- `zip`
- `curl`

### 3.3 Node 环境

建议：

- `Node.js 20`
- `pnpm 10`

### 3.4 Composer

安装最新版 `composer`。

---

## 4. 当前项目怎么打包

## 4.1 推荐方式

推荐不要先在本地打“成品包”，而是直接把**源码包**传到服务器，然后在服务器安装依赖和构建。

原因：

- `php` 和 `apps/ws-webman` 都需要分别执行 `composer install`
- `php/web` 和 `vue` 都需要分别执行 `pnpm build`
- 服务器本地构建更接近线上环境，排错更容易

## 4.2 源码包里保留什么

上传源码时，保留这些目录和文件：

- `php`
- `vue`
- `apps/ws-webman`
- `docs`
- 根目录的 `API_INTERFACE.md`
- 根目录的 `live_platform_schema.sql`

## 4.3 源码包里排除什么

打包时建议排除这些目录：

- `.git`
- `node_modules`
- `vendor`
- `runtime`
- `.idea`
- `.vscode`
- 各种本地日志文件

## 4.4 如果你想在 Windows 本地先压缩

可以直接把项目复制到一个临时目录，再手动排除上面这些目录，最后压成 zip。

如果你已经把代码提交到 Git，最简单的是：

```bash
git archive --format zip -o douyin-src.zip HEAD
```

注意：

- 这个命令只会打包已提交文件
- 如果你有本地未提交改动，不要用这个命令，改用手动压缩

---

## 5. 服务器目录建议

建议服务器目录如下：

```text
/data/www/douyin/
├─ php/
├─ vue/
├─ apps/
│  └─ ws-webman/
├─ logs/
└─ backup/
```

上传后最终结构建议保持和仓库一致。

---

## 6. 代码上传

可以任选一种方式：

### 方式 A：服务器直接拉 Git

```bash
cd /data/www
git clone <你的仓库地址> douyin
cd /data/www/douyin
```

### 方式 B：本地压缩后上传

把源码包上传到服务器后解压：

```bash
mkdir -p /data/www/douyin
cd /data/www/douyin
unzip /path/to/douyin-src.zip
```

---

## 7. 安装后端依赖

### 7.1 安装 ThinkPHP 依赖

```bash
cd /data/www/douyin/php
composer install --no-dev --optimize-autoloader
```

### 7.2 安装 Webman 依赖

```bash
cd /data/www/douyin/apps/ws-webman
composer install --no-dev --optimize-autoloader
```

---

## 8. 初始化数据库

这里分成两步做，比较稳。

## 8.1 先安装 BuildAdmin 基础后台

原因：

- 当前仓库里的 `php/sql/live_platform.sql` 主要是表结构，不带后台初始账号数据
- `BuildAdmin` 更适合先走安装向导，自动生成后台管理员和基础配置

操作步骤：

1. 确保 `php/public/install.lock` 被删除
2. 先配置好站点，让 `admin.example.com` 指到 `php/public`
3. 浏览器访问：

```text
http://admin.example.com/install/
```

4. 按页面提示填写数据库信息
5. 创建后台管理员账号
6. 安装完成后会重新生成 `install.lock`

## 8.2 再导入项目业务表

安装完 BuildAdmin 后，再把直播业务表导入：

```bash
mysql -uroot -p live_platform < /data/www/douyin/live_platform_schema.sql
```

说明：

- 这个 SQL 主要是 `lp_*` 业务表
- 这样不会覆盖 BuildAdmin 安装过程生成的后台基础数据

## 8.3 写入直播后台菜单

导入业务表后，执行菜单脚本：

```bash
cd /data/www/douyin/php
php tools/seed_live_admin_menu.php
```

执行成功后会输出：

```text
Live admin menus seeded.
```

---

## 9. 配置环境变量

## 9.1 ThinkPHP `.env`

当前项目有一个模板文件：

- `php/.env-example`

复制并修改：

```bash
cd /data/www/douyin/php
cp .env-example .env
```

至少确认这些配置：

```ini
APP_DEBUG = false

[DATABASE]
TYPE = mysql
HOSTNAME = 127.0.0.1
DATABASE = live_platform
USERNAME = your_mysql_user
PASSWORD = your_mysql_password
HOSTPORT = 3306
PREFIX = ba_
CHARSET = utf8mb4

[CACHE]
DRIVER = redis

[REDIS]
HOST = 127.0.0.1
PORT = 6379
PASSWORD =
SELECT = 0
PREFIX = live:
```

补充说明：

- `php/config/database.php` 使用的是 `database.*`
- `php/config/cache.php` 使用的是 `redis.*`
- `php/config/jwt.php` 里的 `jwt.secret` 建议改成你自己的随机字符串

## 9.2 Webman 环境变量

`apps/ws-webman` 读取的是系统环境变量，而不是 ThinkPHP 的 `.env`。

建议新建：

- `/data/www/douyin/apps/ws-webman/.env`

内容示例：

```ini
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=live_platform
DB_USER=your_mysql_user
DB_PASSWORD=your_mysql_password
DB_CHARSET=utf8mb4

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=
REDIS_DB=0
```

如果你的部署方式没有自动加载 `.env`，就把这些变量写进 `supervisor` 配置里。

---

## 10. 构建前端

这个项目有两个前端，要分别构建。

## 10.1 构建管理后台前端

```bash
cd /data/www/douyin/php/web
pnpm install
pnpm build
```

构建完成后，把 `php/web/dist` 同步到 `php/public`：

```bash
rsync -av --delete /data/www/douyin/php/web/dist/ /data/www/douyin/php/public/
```

说明：

- `php/public/index.php` 会优先把非接口访问转到 `index.html`
- 所以后台前端静态资源最终必须落在 `php/public`

## 10.2 构建 H5 前端

```bash
cd /data/www/douyin/vue
pnpm install
pnpm build
```

构建结果在：

- `vue/dist`

这个目录直接给 `Nginx` 托管即可。

---

## 11. 配置 Nginx

下面给一套可直接改域名后使用的示例。

## 11.1 后台站点 `admin.example.com`

站点根目录：

```text
/data/www/douyin/php/public
```

示例配置：

```nginx
server {
    listen 80;
    server_name admin.example.com;

    root /data/www/douyin/php/public;
    index index.php index.html;

    client_max_body_size 100m;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }

    location ~* \.(js|css|png|jpg|jpeg|gif|svg|ico|woff|woff2|ttf)$ {
        expires 7d;
        access_log off;
    }
}
```

## 11.2 H5 站点 `h5.example.com`

站点根目录：

```text
/data/www/douyin/vue/dist
```

示例配置：

```nginx
server {
    listen 80;
    server_name h5.example.com;

    root /data/www/douyin/vue/dist;
    index index.html;

    client_max_body_size 100m;

    location / {
        try_files $uri $uri/ /index.html;
    }

    location /api/ {
        proxy_pass http://127.0.0.1:9001;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    location /hls/ {
        proxy_pass http://127.0.0.1:9001;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    location /ws/ {
        proxy_pass http://127.0.0.1:8788/;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }
}
```

### 11.2.1 为什么 `/api` 和 `/hls` 要反代给同域名

因为当前 `vue` 前端代码里：

- 接口地址是相对路径 `/api/...`
- 播放地址也希望尽量走当前访问域名

这样做的好处：

- 不需要额外改前端接口域名
- 避免跨域
- `RoomService` 返回的 `hls_url` 更容易和当前域名保持一致

## 11.3 本机 PHP 站点端口

上面例子把 `Nginx` 的 `/api`、`/hls` 转给了 `127.0.0.1:9001`，所以你需要再给 ThinkPHP 配一个本机监听站点。

最简单有两种方式：

### 方式 A：直接让 `admin.example.com` 的 PHP 站点同时承担 API

那就把 `h5.example.com` 中的 `proxy_pass http://127.0.0.1:9001;` 改成：

```nginx
proxy_pass http://admin.example.com;
```

### 方式 B：给 PHP 再配一个内网站点

例如：

- `127.0.0.1:9001 -> /data/www/douyin/php/public`

这个方式更清晰，也更适合后续扩容。

---

## 12. 启动 Webman

当前 `apps/ws-webman` 配置里：

- HTTP 监听：`8787`
- WebSocket 监听：`8788`

先手工试启动：

```bash
cd /data/www/douyin/apps/ws-webman
php start.php start
```

如果没报错，再交给 `Supervisor` 托管。

---

## 13. 用 Supervisor 守护 Webman

新建配置：

- `/etc/supervisor/conf.d/douyin-ws.conf`

内容示例：

```ini
[program:douyin-ws]
directory=/data/www/douyin/apps/ws-webman
command=/usr/bin/php /data/www/douyin/apps/ws-webman/start.php start
autostart=true
autorestart=true
startsecs=5
user=www-data
stdout_logfile=/data/www/douyin/logs/ws-webman.out.log
stderr_logfile=/data/www/douyin/logs/ws-webman.err.log
environment=DB_HOST="127.0.0.1",DB_PORT="3306",DB_NAME="live_platform",DB_USER="your_mysql_user",DB_PASSWORD="your_mysql_password",DB_CHARSET="utf8mb4",REDIS_HOST="127.0.0.1",REDIS_PORT="6379",REDIS_PASSWORD="",REDIS_DB="0"
```

生效命令：

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start douyin-ws
sudo supervisorctl status
```

---

## 14. 是否要部署 SRS

## 14.1 第一次上云看效果

第一次上云只为了看页面、房间列表、后台、基础播放效果时：

- 可以先**不部署 SRS**
- 先使用现有 `HLS` 播放链路

## 14.2 什么时候必须部署 SRS

出现以下需求时，再上 `SRS`：

- 想验证 `WebRTC/WHEP`
- 想验证更低延迟直播
- 想对接真实推流

## 14.3 为什么后面需要 SRS

当前 H5 前端的播放器逻辑是：

- 优先尝试 `HLS`
- 其次尝试 `WebRTC`

而 WebRTC 的实现会把类似：

```text
webrtc://your-domain/live/room/1
```

转换成类似：

```text
http://your-domain:1985/rtc/v1/whep/?app=live/room&stream=1
```

所以如果你后面启用 WebRTC，需要保证：

- `SRS` 正常运行
- `1985` 或反向代理后的 `WHEP` 地址可访问

---

## 15. 建议的首次上线顺序

建议按这个顺序，不容易踩坑：

1. 上传源码
2. 安装 `composer` 依赖
3. 安装 `pnpm` 依赖
4. 配置 `MySQL` 和 `Redis`
5. 打开 `admin.example.com/install/` 跑 BuildAdmin 安装向导
6. 导入 `live_platform_schema.sql`
7. 执行 `php tools/seed_live_admin_menu.php`
8. 构建 `php/web`
9. 把 `php/web/dist` 同步到 `php/public`
10. 构建 `vue`
11. 配置 `Nginx`
12. 手工启动 `Webman`
13. 再接入 `Supervisor`
14. 最后再考虑 `SRS`

---

## 16. 上线后如何运行

## 16.1 ThinkPHP

生产环境建议：

- 不要用 `php think run`
- 用 `Nginx + PHP-FPM`

平时不需要单独“启动 ThinkPHP”，只要：

- `nginx` 在运行
- `php-fpm` 在运行

它就会工作。

## 16.2 Webman

Webman 是常驻进程，建议：

- 用 `Supervisor` 托管
- 需要重启时执行：

```bash
sudo supervisorctl restart douyin-ws
```

## 16.3 Redis / MySQL

用系统服务管理：

```bash
sudo systemctl enable redis-server
sudo systemctl enable mysql
sudo systemctl restart redis-server
sudo systemctl restart mysql
```

---

## 17. 首次验收清单

部署完成后按这个顺序检查：

### 17.1 后台

访问：

```text
http://admin.example.com
```

确认：

- 后台登录页能打开
- 登录后能看到“直播运营”菜单

### 17.2 H5

访问：

```text
http://h5.example.com
```

确认：

- 首页能正常加载
- 房间列表接口有返回
- 进入房间能看到视频区域

### 17.3 接口

```bash
curl "http://h5.example.com/api/v1/feed/live?server=1&limit=5"
```

确认：

- 返回 `code = "00000"`

### 17.4 HLS

如果房间接口里返回了 `hls_url`，直接访问一个真实地址，例如：

```text
http://h5.example.com/hls/room/1.m3u8
```

确认：

- 能返回 `m3u8` 内容

### 17.5 Webman

查看进程状态：

```bash
sudo supervisorctl status
```

确认：

- `douyin-ws` 是 `RUNNING`

---

## 18. 常见问题

## 18.1 H5 打开了，但接口全是 404

通常是因为：

- `h5.example.com` 没有把 `/api/` 反向代理到 PHP
- 或 `Nginx` 的 PHP 站点根目录不是 `php/public`

## 18.2 后台能打开，但登录不上

优先检查：

- BuildAdmin 安装向导是否真的执行过
- 数据库里是否已经生成管理员数据
- `php/public/install.lock` 是否被错误保留或错误删除

## 18.3 HLS 地址打不开

优先检查：

- `php/public/hls` 下是否真的有 `m3u8` 和 `ts`
- `Nginx` 是否把 `/hls/` 正确转到了 PHP 站点
- 文件权限是否允许 `www-data` 读取

## 18.4 WebSocket 连不上

优先检查：

- `apps/ws-webman` 是否在运行
- `8788` 是否监听成功
- `Nginx` 是否加了 `Upgrade` 和 `Connection upgrade`

## 18.5 WebRTC 播放失败

优先检查：

- 是否部署了 `SRS`
- `1985` 或 `WHEP` 代理地址是否可达
- HTTPS 页面下是否还在请求 HTTP 的 `WHEP`

---

## 19. 我给你的最终建议

如果你现在只是想“先放到云服务器上跑起来看看效果”，按这个最小方案走：

### 最小可运行方案

- 部署 `MySQL`
- 部署 `Redis`
- 部署 `Nginx + PHP-FPM`
- 部署 `php`
- 构建并部署 `vue`
- 构建 `php/web` 并同步到 `php/public`
- 启动 `apps/ws-webman`
- 暂时不部署 `SRS`

### 这样你能先看到什么

- H5 页面
- 后台页面
- 基础接口
- 房间列表
- HLS 播放链路

### 后面再补什么

- `SRS`
- HTTPS
- 域名证书
- WebRTC
- 守护脚本完善
- 日志轮转
- 备份策略

---

## 20. 一组可直接执行的最小命令

下面这组命令适合“源码已上传到 `/data/www/douyin`，现在开始部署”：

```bash
cd /data/www/douyin/php
composer install --no-dev --optimize-autoloader
cp .env-example .env

cd /data/www/douyin/apps/ws-webman
composer install --no-dev --optimize-autoloader

cd /data/www/douyin/php/web
pnpm install
pnpm build
rsync -av --delete /data/www/douyin/php/web/dist/ /data/www/douyin/php/public/

cd /data/www/douyin/vue
pnpm install
pnpm build

mysql -uroot -p live_platform < /data/www/douyin/live_platform_schema.sql

cd /data/www/douyin/php
php tools/seed_live_admin_menu.php

cd /data/www/douyin/apps/ws-webman
php start.php start
```

注意：

- 上面这组命令默认你已经通过安装向导初始化过 BuildAdmin
- 如果你还没初始化后台，请先访问 `admin.example.com/install/`

