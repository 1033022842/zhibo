# 服务器端 SRS 部署指南

> 本文档面向 **服务器端（公网低配机）**：安装 SRS 接收 AI 电脑推来的 RTMP 流，转成 HLS 由 Nginx 分发。
>
> AI 电脑端（推流端）部署见：[`services/channel-worker/python/README_STREAM.md`](../services/channel-worker/python/README_STREAM.md)

## 架构回顾

```
N 台 AI 电脑(推流)                      服务器(本文档)
  worker.py --room=5/6/7...                SRS(:1935 收RTMP → :1985 API)
    └─ ffmpeg NVENC 编码                    └→ /www/wwwroot/douyin/hls/room/{id}/index.m3u8
       └─ RTMP 推到服务器 SRS                    → Nginx /hls/ 分发 → 浏览器 hls.js
```

**服务器只做转封装 + 分发，零编码负载**（SRS 把 RTMP 重封装成 HLS，不重新编码）。CPU 占用 < 10%，彻底解决服务器推不动的问题。

## 1. 安装 SRS

### 方式 A：包管理器（推荐，Debian/Ubuntu）

```bash
# 官方仓库
apt-get update
apt-get install -y srs
# 安装后配置在 /etc/srs/srs.conf，二进制 /usr/bin/srs
```

### 方式 B：官方二进制

```bash
mkdir -p /opt/srs && cd /opt/srs
# 到 https://github.com/ossrs/srs/releases 选最新稳定版 Linux 包，例如：
wget https://github.com/ossrs/srs/releases/download/v5.0.200/srs-server-5.0.200-linux-amd64.zip
unzip srs-server-*.zip
# 二进制: /opt/srs/srs-server-*/objs/srs  ，配置: /opt/srs/srs-server-*/conf/srs.conf
```

验证：
```bash
srs -v          # 或 /opt/srs/objs/srs -v
```

## 2. 配置 srs.conf

关键：用 `hls_m3u8_file` 模板让 HLS 输出路径 = 现有 `/www/wwwroot/douyin/hls/room/{id}/index.m3u8`，**前端和播放 URL 零改动**。

```conf
# /etc/srs/srs.conf  （包管理器安装）
# 或 /opt/srs/conf/srs.conf （二进制安装）

listen              1935;              # RTMP 监听端口
max_connections      1000;
daemon              off;               # 前台运行（交给 supervisor/systemd 管理）
srs_log_tank        file;
srs_log_file        /var/log/srs.log;

# HTTP API（给后台探活/RoomService 用，仅本机访问）
http_api {
    enabled         on;
    listen          1985;
    allow           127.0.0.1;
    allow           ::1;
    # 如需让 AI 电脑或后台服务器查 SRS API，放开对应 IP：
    # allow         192.168.1.0/24;
    deny            all;
}

http_server {
    enabled         off;               # 不用 SRS 自带 HTTP 服务，HLS 走 Nginx
}

vhost __defaultVhost__ {
    # HLS 输出（核心）
    hls {
        enabled         on;
        hls_path        /www/wwwroot/douyin/hls;       # HLS 输出根目录
        hls_m3u8_file   [app]/[stream]/index.m3u8;     # 关键：对齐现有路径 room/{id}/index.m3u8
        hls_ts_file     [app]/[stream]/[seq].ts;
        hls_fragment    2;                              # 每个分片约 2 秒
        hls_window      30;                             # 播放列表保留 30 个分片（60 秒窗口）
        hls_cleanup     on;                             # 自动清理旧分片
        hls_dispose     10;                             # 流结束 10 秒后清理文件
    }

    # RTMP 推流无需鉴权（靠防火墙限制来源 IP）
    # 如需推流鉴权，参考 SRS 文档加 security / http_hooks
}
```

> **路径对齐原理**：AI 电脑推 `rtmp://server:1935/room/5`，SRS 解析 app=`room`、stream=`5`，按 `hls_m3u8_file = [app]/[stream]/index.m3u8` 输出到 `{hls_path}/room/5/index.m3u8`，正好等于浏览器访问的 `/hls/room/5/index.m3u8`。

## 3. 目录权限

SRS 进程需对 HLS 输出目录有写权限：

```bash
mkdir -p /www/wwwroot/douyin/hls
# 查 SRS 运行用户（包管理器安装通常是 www 或 srs）
chown -R www:www /www/wwwroot/douyin/hls   # 按实际用户调整
chmod -R 755 /www/wwwroot/douyin/hls
```

> 注意 Nginx 的 `/hls/` 配置需保留 `disable_symlinks off;`（如沿用旧配置）。

## 4. 进程托管（Supervisor 或 systemd，二选一）

### 方式 A：Supervisor（项目已用 Supervisor 管其它进程，推荐）

新建 `/etc/supervisor/conf.d/srs.conf`：
```ini
[program:srs]
command=/usr/bin/srs -c /etc/srs/srs.conf
directory=/etc/srs
autostart=true
autorestart=true
startsecs=3
stdout_logfile=/var/log/srs-supervisor.log
stderr_logfile=/var/log/srs-supervisor-err.log
```

```bash
supervisorctl reread
supervisorctl update
supervisorctl start srs
supervisorctl status srs        # 应为 RUNNING
```

### 方式 B：systemd

```bash
cat > /etc/systemd/system/srs.service <<'EOF'
[Unit]
Description=SRS Media Server
After=network.target

[Service]
Type=simple
ExecStart=/usr/bin/srs -c /etc/srs/srs.conf
Restart=always
RestartSec=3
LimitNOFILE=65535

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable --now srs
systemctl status srs
```

## 5. 防火墙

| 端口 | 用途 | 开放范围 |
|---|---|---|
| 1935 | RTMP 推流（AI 电脑 → SRS） | **仅 AI 电脑 IP**（务必限制，防滥用） |
| 1985 | SRS HTTP API（后台探活） | 仅本机 + 后台服务器内网 |
| 80/443 | Nginx HLS 分发 | 公网（观众访问） |

```bash
# 假设 AI 电脑公网 IP 为 1.2.3.4
ufw allow from 1.2.3.4 to any port 1935
ufw deny 1935
# 1985 仅本机
ufw deny 1985
```

宝塔面板：在「安全」里加 1935 端口规则，限制来源 IP 为 AI 电脑 IP。

## 6. 清理旧的 ChannelWorker（重要）

服务器上不再需要 ffmpeg 编码（编码已移到 AI 电脑）。移除旧的 Supervisor 程序：

```bash
# 停止所有房间推流 worker
supervisorctl stop channel-worker-room5
supervisorctl stop channel-worker-room6
supervisorctl stop channel-worker-room7
supervisorctl stop channel-worker-room8
# ... 按实际房间号

# 移除 supervisor 配置
rm -f /etc/supervisor/conf.d/channel-worker-room*.conf
supervisorctl reread
supervisorctl update

# 确认无残留 ffmpeg（应为空）
ps aux | grep ffmpeg | grep -v grep
# 如有残留，手动 kill
```

旧 HLS 分片会被 SRS 重新生成，无需手动清理（但首次切换可清一次避免混乱）：
```bash
rm -rf /www/wwwroot/douyin/hls/room/*
```

## 7. 验证

### 7.1 SRS 服务正常
```bash
systemctl status srs        # 或 supervisorctl status srs → RUNNING
curl http://127.0.0.1:1985/api/v1/versions/    # 返回 SRS 版本 JSON
curl http://127.0.0.1:1985/api/v1/streams/     # 暂时无流时应返回 streams:[] 或空数组
```

### 7.2 AI 电脑推流后
启动一台 AI 电脑的 `start_streams.bat`（room=5），服务器上：
```bash
curl http://127.0.0.1:1985/api/v1/streams/
# 应看到 {"code":0,"streams":[{"id":1,"name":"5","app":"room",...}]}
```

### 7.3 HLS 生成
```bash
ls -lh /www/wwwroot/douyin/hls/room/5/
# 应有 index.m3u8 + 若干 [seq].ts
curl -I http://127.0.0.1/hls/room/5/index.m3u8   # 通过 Nginx → 200 OK
```

### 7.4 浏览器播放
访问直播间，hls.js 应正常播放 `/hls/room/5/index.m3u8`。

### 7.5 服务器负载
```bash
top -bn1 | head -5       # SRS 转封装 CPU 应 < 10%（对比旧 ffmpeg 编码 100%+）
```

## 8. 故障排查

| 现象 | 排查 |
|---|---|
| AI 电脑日志 `RTMP 上线等待超时` | 1) SRS 是否 RUNNING  2) 防火墙 1935 是否对 AI 电脑开放  3) `RTMP_BASE` 地址对不对 |
| `curl /api/v1/streams/` 看不到流 | 推流端 ffmpeg 是否存活；SRS `srs.log` 有没有拒绝连接的报错 |
| HLS 目录没生成 index.m3u8 | SRS 进程对 `/www/wwwroot/douyin/hls/` 没写权限（`chown`/`chmod`） |
| 浏览器 404 | Nginx `/hls/` location 的 `alias` 指向对不对；`disable_symlinks off;` |
| 切礼物时画面卡 1-2 秒 | **预期行为**：长 ffmpeg 在送礼瞬间重启换播单，断流一次。如不可接受，需另议切换方案 |
| 旧分片堆积不清理 | 检查 `hls_cleanup on` 和 `hls_dispose` 配置；SRS 进程对目录有删除权限 |

## 9. 与旧 MediaMTX 遗留代码的关系

项目历史上有 MediaMTX 的引用（`Whep.php`、`RoomService::getActiveStreamRoomIds` 原指向 `127.0.0.1:9997`），改造后：
- `RoomService::getActiveStreamRoomIds` 已改为查 **SRS API**（`127.0.0.1:1985`）
- `Whep.php`（WebRTC/WHEP 代理）目前前端 HLS 优先且 WebRTC 兜底链路本就失效，可保留不动；如不再需要 WebRTC 可后续清理
- 前端 `livePlayer.ts` HLS 优先，无需改动
