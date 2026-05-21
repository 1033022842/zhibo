# Git 拉取代码后的安装操作流程

## 1. 适用场景

这份文档适合下面这种情况：

- 你准备在一台 Linux 云服务器上直接 `git clone`
- 目标是先把当前项目跑起来，看到后台、H5、接口和基础播放效果

当前项目不是单一服务，而是由这些部分组成：

- `php`：ThinkPHP 8 业务 API + BuildAdmin 后台
- `php/web`：后台前端源码，构建后同步到 `php/public`
- `vue`：用户侧 H5 前端
- `apps/ws-webman`：Webman WebSocket 服务
- `services/channel-worker`：可选的本地 HLS demo worker 源码

首次上云建议先跑最小可用方案：

- `MySQL`
- `Redis`
- `Nginx`
- `PHP-FPM`
- `Supervisor`
- `php`
- `vue`
- `php/web`
- `apps/ws-webman`

***

## 2. 服务器准备

建议环境：

- Ubuntu 22.04 LTS
- 2C4G 起步
- 40G 以上磁盘

建议先安装这些软件：

```bash
sudo apt update
sudo apt install -y nginx mysql-server redis-server supervisor git unzip curl
```

***

## 3. 安装 PHP、Node、Composer

### 3.1 PHP

建议使用 `PHP 8.2` 或 `PHP 8.3`，并安装至少这些扩展：

- `bcmath`
- `gd`
- `iconv`
- `mbstring`
- `pdo_mysql`
- `openssl`
- `fileinfo`
- `zip`
- `curl`

Ubuntu 22.04 常见安装方式示例：

```bash
sudo apt install -y php8.2 php8.2-fpm php8.2-cli php8.2-mysql php8.2-curl php8.2-mbstring php8.2-zip php8.2-bcmath php8.2-gd php8.2-xml
```

### 3.2 Node 和 pnpm

建议：

- `Node.js 20`
- `pnpm 10`

示例：

```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
sudo npm install -g pnpm
```

### 3.3 Composer

如果服务器还没有 `composer`，安装示例：

```bash
cd /tmp
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php
sudo mv composer.phar /usr/local/bin/composer
composer --version
```

***

## 4. 拉取 Git 代码

建议统一放到：

```text
/data/www/douyin
```

执行：

```bash
sudo mkdir -p /data/www
cd /data/www
sudo git clone https://github.com/1033022842/zhibo.git douyin
sudo chown -R $USER:$USER /data/www/douyin
cd /data/www/douyin
```

## 5. 安装后端依赖

### 5.1 安装 ThinkPHP 依赖

```bash
cd /data/www/douyin/php
composer install --no-dev --optimize-autoloader
```

### 5.2 安装 Webman 依赖

```bash
cd /data/www/douyin/apps/ws-webman
composer install --no-dev --optimize-autoloader
```

***

## 6. 配置数据库和缓存

先确保 `MySQL` 和 `Redis` 已启动：

```bash
sudo systemctl enable mysql
sudo systemctl enable redis-server
sudo systemctl restart mysql
sudo systemctl restart redis-server
```

建议先在 MySQL 里创建数据库：

```bash
mysql -uroot -p
```

进入 MySQL 后执行：

```sql
CREATE DATABASE live_platform DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

***

## 7. 配置环境变量

### 7.1 配置 ThinkPHP

当前仓库提供模板文件：

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

### 7.2 配置 Webman

`apps/ws-webman` 读取系统环境变量，建议准备一个 `.env`：

```bash
cd /data/www/douyin/apps/ws-webman
cat > .env <<'EOF'
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
EOF
```

***

## 8. 初始化后台和数据库

### 8.1 先安装 BuildAdmin

当前项目建议先用 BuildAdmin 安装向导生成基础后台数据。

先确认安装锁文件状态：

```bash
rm -f /data/www/douyin/php/public/install.lock
```

然后先把 `admin.example.com` 指向：

```text
/data/www/douyin/php/public
```

再在浏览器访问：

```text
http://admin.example.com/install/
```

按页面提示完成：

- 数据库连接信息
- 后台管理员账号
- 基础系统配置

### 8.2 导入业务表

安装完 BuildAdmin 后，再导入直播平台业务 SQL：

```bash
mysql -uroot -p live_platform < /data/www/douyin/live_platform_schema.sql
```

### 8.3 写入直播后台菜单

```bash
cd /data/www/douyin/php
php tools/seed_live_admin_menu.php
```

如果输出下面内容，说明执行成功：

```text
Live admin menus seeded.
```

***

## 9. 构建前端

这个项目有两个前端，需要分别构建。

### 9.1 构建后台前端

```bash
cd /data/www/douyin/php/web
pnpm install
pnpm build
```

构建完成后，把 `dist` 同步到 `php/public`目录下：

```bash
rsync -av --delete /data/www/douyin/php/web/dist/ /data/www/douyin/php/public/
```

### 9.2 构建 H5 前端

```bash
cd /data/www/douyin/vue
pnpm install
pnpm build
```

构建产物在：

```text
/data/www/douyin/vue/dist
```

***

## 10. 配置 Nginx

建议至少配两个站点：

- `admin.example.com` 指向 `php/public`
- `h5.example.com` 指向 `vue/dist`

### 10.1 后台站点

根目录：

```text
/data/www/douyin/php/public
```

参考配置：

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
}
```

### 10.2 H5 站点

根目录：

```text
/data/www/douyin/vue/dist
```

参考配置：

```nginx
server {
    listen 80;
    server_name h5.example.com;

    root /data/www/douyin/vue/dist;
    index index.html;

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

如果 `h5.example.com` 的 `/api/` 和 `/hls/` 要走本机 PHP 站点，记得额外准备：

```text
127.0.0.1:9001 -> /data/www/douyin/php/public
```

***

## 11. 启动 Webman

先手工测试启动：

```bash
cd /data/www/douyin/apps/ws-webman
php start.php start
```

如果正常，再交给 `Supervisor` 托管。

***

## 12. 配置 Supervisor

新建：

```text
/etc/supervisor/conf.d/douyin-ws.conf
```

示例：

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
sudo mkdir -p /data/www/douyin/logs
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start douyin-ws
sudo supervisorctl status
```

***

## 13. 可选：初始化 channel-worker 本地 HLS demo

这一步不是首次部署必需，但如果你想快速验证本地 demo HLS 链路，可以再做。

当前仓库已经纳入了 `services/channel-worker` 的源码，但运行时文件和素材不在 Git 里，所以首次上服务器后需要自己初始化。

如果服务器已具备 `ffmpeg`，可以执行：

```bash
cd /data/www/douyin/php
php ../services/channel-worker/bin/seed-demo.php
```

这个脚本会做两件事：

- 下载 demo 素材到 `services/channel-worker/runtime/assets`
- 把素材地址回写数据库

之后再根据你的运行方式启动对应 worker。

如果你当前只是先跑后台、H5 和接口，这一步可以先跳过。

***

## 14. 首次验收

建议按这个顺序检查：

### 14.1 后台

访问：

```text
http://admin.example.com
```

确认：

- 登录页能打开
- 能成功登录
- 能看到“直播运营”菜单

### 14.2 H5

访问：

```text
http://h5.example.com
```

确认：

- 首页正常加载
- 房间列表正常返回
- 进入房间后能看到视频区域

### 14.3 接口

```bash
curl "http://h5.example.com/api/v1/feed/live?server=1&limit=5"
```

确认返回里有：

```text
code = "00000"
```

### 14.4 HLS

如果接口里已经返回了 `hls_url`，可以直接访问类似：

```text
http://h5.example.com/hls/room/1.m3u8
```

### 14.5 Webman

```bash
sudo supervisorctl status
```

确认：

- `douyin-ws` 为 `RUNNING`

***

## 15. 日常更新流程

后续服务器更新代码，建议按下面顺序执行：

```bash
cd /data/www/douyin
git pull origin main

cd /data/www/douyin/php
composer install --no-dev --optimize-autoloader

cd /data/www/douyin/apps/ws-webman
composer install --no-dev --optimize-autoloader

cd /data/www/douyin/php/web
pnpm install
pnpm build
rsync -av --delete /data/www/douyin/php/web/dist/ /data/www/douyin/php/public/

cd /data/www/douyin/vue
pnpm install
pnpm build

sudo supervisorctl restart douyin-ws
sudo systemctl reload nginx
```

如果这次更新涉及数据库变更，再额外执行对应 SQL 或数据初始化脚本。

***

## 16. 一组最小可执行命令

如果你已经：

- 装好了 `MySQL`
- 装好了 `Redis`
- 装好了 `Nginx`
- 装好了 `PHP-FPM`
- 已经把域名解析好了

那么 `git clone` 后最短可以照下面执行：

```bash
cd /data/www
git clone <你的仓库地址> douyin
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

- 上面这组命令默认你已经先通过安装向导初始化过 BuildAdmin
- 如果还没有初始化后台，请先访问 `admin.example.com/install/`

