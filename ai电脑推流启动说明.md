# AI 电脑推流启动说明（已过时）

> ⚠️ **本文档已过时（2026-09-12）**：推流已迁至 172.81.98.55（东京）的 11 路 worker，
> AI 电脑不再承担任何推流任务。当前架构与运维见《docs/172推流服务器运维说明.md》。


> 适用机器：AI 电脑（局域网 192.168.31.225，用户 administrator）
> 更新日期：2026-09-06（支付资金闭环 + LivePortrait 方案回退后）

---

## 一、架构一句话

AI 电脑 `worker.py` 把本地视频目录按播单顺序切成 TS 流喂给 ffmpeg，NVENC 编码推 RTMP 到服务器 SRS，观众通过 HLS 观看；送礼时服务器往 Redis 推关键词，worker 收到后随机插入对应分类的视频。

```
AI 电脑 worker.py（单 ffmpeg 常驻，读 127.0.0.1:8123/stream）
   │  按播单顺序吐 TS 文件（礼物关键词优先插队）
   ▼
ffmpeg NVENC 编码 ──RTMP──► 服务器 SRS(38.181.44.164:1935)
                              │ 转 HLS
                              ▼
                    /hls/room/1/index.m3u8 ◄── 观众播放器
```

---

## 二、日常启动 / 停止

### 启动推流（标准方式）

```cmd
schtasks /run /tn AIWorkerRoom1
```

计划任务会执行 `D:\ever\douyin\_run_worker_detached.bat`，该 bat 设置环境变量后运行 `python worker.py --room=1`，日志追加写入 `D:\ever\douyin\worker.log`。

### 确认启动成功（等 30 秒后）

```cmd
:: 1. 看 ffmpeg 进程在跑（约 200MB 内存）
tasklist | findstr /i ffmpeg

:: 2. 看日志尾部出现这几行
powershell -Command "Get-Content D:\ever\douyin\worker.log -Tail 300 -Encoding Default | Select-String '就绪|RTMP|Redis' | Select-Object -Last 3"
```

正常应看到：
```
[启动] 默认播单 23 个 TS 就绪
[预转] 礼物素材后台预转开始：44 个
[RTMP] 已上线: room/1
Redis connected
```

### 停止推流

```cmd
:: 找到 python.exe（非 ComfyUI 的那个）和 ffmpeg.exe 的 PID
tasklist | findstr /i "python ffmpeg"
taskkill /f /pid <python的PID> /pid <ffmpeg的PID>
```

### 重启（改了配置/素材后必须重启）

```cmd
taskkill /f /pid <旧PID> ... && schtasks /run /tn AIWorkerRoom1
```

---

## 三、关键文件清单

| 文件/目录 | 作用 | 能不能改 |
|---|---|---|
| `D:\ever\douyin\worker.py` | 推流主程序（含礼物预转/NVENC/Redis消费） | 改前先备份，改后需语法检查 |
| `D:\ever\douyin\start_streams.bat` | 环境变量定义（服务器IP/数据库/Redis密码/媒体目录） | 改 `MEDIA_BASE_DIR` 换素材库 |
| `D:\ever\douyin\_run_worker_detached.bat` | 计划任务实际执行的入口（同上变量） | 与 start_streams.bat 保持一致 |
| `D:\ever\douyin\worker.log` | 运行日志（追加模式，会变大） | 可删，重启后自动重建 |
| `D:\拍摄视频\白毛女分类3\` | 当前素材库（MEDIA_BASE_DIR 指向这里） | 换素材见第五节 |
| `D:\ever\runtime\ts_cache\` | TS 预转缓存（按文件路径+规格哈希命名） | 换规格/素材后可整目录删除重建 |

### 环境变量速查（两个 bat 里）

```bat
MEDIA_BASE_DIR=D:\拍摄视频\白毛女分类3   ← 素材库根目录
REDIS_PASSWORD=redis4live2026            ← 服务器 Redis 密码
RTMP_BASE=rtmp://38.181.44.164:1935      ← 推流地址
DB_HOST=38.181.44.164 / DB_NAME=zhibo    ← 播单与素材数据
TS_SCALE=540:960 / TS_FPS=30             ← 统一转码规格（勿随意改）
```

---

## 四、素材与礼物分类

### 目录结构（白毛女分类3）

```
白毛女分类3\
├── 播单\        8月20日 (1).mp4 ... (22).mp4   ← 默认播单（顺序播放）
├── 玫瑰\        玫瑰1-9.mp4                     ← 礼物「玫瑰」触发
├── 傲娇\        傲娇1-8.mp4                     ← 礼物「嘉年华」触发
├── 害羞\        害羞1-8.mp4                     ← 礼物「火箭」触发
├── 撩发\        撩发1-10.mp4                    ← 礼物「超级跑车」触发
└── 飞吻\        飞吻1-9.mp4                     ← 礼物「爱心」触发
```

### 礼物 → 视频分类映射（存在服务器数据库 lp_gift_keyword）

| 礼物 | 触发视频文件夹 | 说明 |
|---|---|---|
| 玫瑰(10钻) | 玫瑰 | |
| 嘉年华(20钻) | 傲娇 | |
| 火箭(50钻) | 害羞 | |
| 超级跑车(80钻) | 撩发 | |
| 爱心(100钻) | 飞吻 | |

改映射在管理后台「礼物管理」里改关键词即可，**即时生效**（worker 已开 autocommit，能看到实时数据）。

### 播单绑定

房间1 → 播单模板「白毛女播单v3(8月20日)」（数据库 `lp_room_binding`）。后台改播单后 worker **每分钟自动刷新**，无需重启。

---

## 五、更换素材流程

1. 把新素材按分类放好目录（如 `D:\拍摄视频\白毛女分类4\播单\xxx.mp4`）
2. 把新目录复制到服务器数据库：
   - 旧素材下架：`UPDATE lp_media_asset SET status=0 WHERE machine_id='room1' AND status=1;`
   - 新素材入库（参考仓库 `php/sql/` 里的 upgrade 脚本格式，asset_code 换新前缀）
   - 新建播单模板 + `lp_room_binding` 绑定
3. 改两个 bat 的 `MEDIA_BASE_DIR` 指向新目录（**必须 CRLF 编码 GBK，用记事本改**）
4. 重启 worker（第二节）
5. 看日志确认「默认播单 N 个 TS 就绪」+「礼物素材后台预转完成 N/N」

---

## 六、常见故障排查

### 1. 直播间黑屏/没画面

按顺序检查：

```cmd
:: ① AI 电脑 worker 活着吗
tasklist | findstr /i "python ffmpeg"
:: 没有 → schtasks /run /tn AIWorkerRoom1

:: ② 推流正常吗（看日志最后是否还在输出 frame= 行）
powershell -Command "Get-Content D:\ever\douyin\worker.log -Tail 5"

:: ③ 服务器 HLS 分片新鲜吗（时间戳应是几分钟内）
ssh root@38.181.44.164 "ls -l /www/wwwroot/douyin/hls/room/1/index.m3u8"
```

### 2. 送礼没反应（视频不切换）

```bash
# 服务器上查 Redis 队列积压
redis-cli -a 'redis4live2026' llen 'list:keyword:room:1'
# >0 说明 worker 没消费 → 看 worker 日志 Redis connected 有没有报错
```

### 3. worker 启动就退出

看 `worker.log` 尾部报错。历史踩坑：
- **数据库连不上**：检查 bat 里 DB_HOST/密码
- **Redis HELLO 报错**：REDIS_PASSWORD 没配或配错
- **预转 0/N**：MEDIA_BASE_DIR 路径写错（文件找不到）

### 4. worker 被莫名杀掉

历史发生过（ffmpeg 24 分钟例行重启后整个 python 收到 signal 15）。**彻底解法是加看门狗任务**（见第八节）。

### 5. 播单里视频顺序不对

播单顺序 = `lp_playlist_template_item.seq` 字段，在数据库里改，worker 下次刷新自动生效。

---

## 七、注意事项（重要）

1. **计划任务不会开机自启**：`AIWorkerRoom1` 是手动触发型任务，**AI 电脑重启后必须手动 `schtasks /run /tn AIWorkerRoom1`**（或按第八节加开机任务）
2. **不要用 stop_streams.bat**：它是旧版按窗口标题找进程的，对当前启动方式无效，直接 taskkill PID
3. **bat 文件编码是 GBK + CRLF**：用记事本改，别用 VSCode（默认 UTF-8 会把中文路径改坏——历史上踩过）
4. **worker.log 会一直增长**：每 1-2 周删一次（推流中断一下自动重建）
5. **TS 缓存目录可以随时删**：worker 会自动重新预转（首次启动会慢 1-2 分钟）
6. **别动 D:\ever\douyin\ 下的测试脚本**（test_*.py / probe.py 等是联调工具）
7. **LivePortrait 目录（D:\LivePortrait）是新方案残留**，当前推流不用它，别启动
8. **服务器 crontab 有两个每分钟任务**（充值扫描/众筹结算），别误删
9. **换素材目录后 ts_cache 要清**，否则哈希键对不上会重新转（不致命但占空间）
10. **多人操作 AI 电脑时先看进程**：`tasklist | findstr python` 确认没有别人在跑东西再 taskkill，避免误杀 ComfyUI

---

## 八、建议加固（可选）

### 1. worker 开机自启 + 看门狗

```cmd
:: 开机自启
schtasks /create /tn AIWorkerBoot /tr "D:\ever\douyin\_run_worker_detached.bat" /sc onstart /ru SYSTEM /rl HIGHEST /f

:: 看门狗：每 5 分钟检查，worker 不在就拉起
schtasks /create /tn AIWorkerWatchdog /tr "powershell -Command \"if (!(Get-CimInstance Win32_Process -Filter \\\"name='python.exe'\\\" | Where-Object {$_.CommandLine -match 'worker.py'})) { schtasks /run /tn AIWorkerRoom1 }\"" /sc minute /mo 5 /ru SYSTEM /f
```

### 2. 日志轮转

worker.log 超过 100MB 自动删旧的（可用计划任务跑 robocopy 或 PowerShell 脚本）。

---

## 九、服务器侧依赖速查（出问题先看这边）

| 服务 | 检查命令（服务器上） |
|---|---|
| SRS 推流接收 | `ps aux \| grep srs` / `curl 127.0.0.1:1985/api/v1/streams/` |
| HLS 输出 | `ls -l /www/wwwroot/douyin/hls/room/1/` |
| nginx | `systemctl status nginx` |
| MySQL/Redis | `systemctl status mysql mysqld` / `redis-cli -a redis4live2026 ping` |
| 众筹结算 cron | `tail -3 /root/cron_crowdfunding.log` |
| 充值扫描 cron | `tail -3 /root/cron_recharge.log` |
| ws-webman(礼物) | `ps aux \| grep ws-webman` |
