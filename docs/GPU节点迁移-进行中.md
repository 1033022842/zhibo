# GPU 节点迁移（三项目）— 已作废，见「单GPU四站部署」

> **2026-10-21 更新：本方案已废弃。**
>
> 两台 5090 节点（`38.247.190.236` / `204.12.218.174`）**欠费失联**（ICMP 100% 丢包、
> 22/80/443/8000/8188 全超时，从本机与从 166.124 双向都试过），模型无法从它们拷出。
>
> 现行方案：**四站（sugus / heygirl / kisss / kelymi）共用一台 `38.247.166.124`
> （RTX PRO 6000 96G）**，见 [`docs/单GPU四站部署.md`](单GPU四站部署.md)。
>
> 下文保留作为历史记录（5090 32G 的时长/分辨率实测数据仍然有效，可作画质参数参考）。

> 更新：2026-10-14

## 视频时长/分辨率上限实测（sugus 5090 32G @ 38.247.190.236）

生产工作流 MiniMax-H3 I2VA（I2V+音频，8 步，出片 48fps=24fps 基础帧 ×RIFE 2×）。
输入 800×800 → PerfectVideoResolution 的 MiniMax H3 模式按「目标像素档」自适应到近方图。

| 目标档(像素) | 实际分辨率 | 时长 | 出片帧数(48fps) | 公推耗时 | 峰值显存 | 结果 |
|---|---|---|---|---|---|---|
| Native 0.92MP | 960×960 | **8s** | 383 | ~5.5 min | ~30 GB | ✅ 现网 |
| Native | 960×960 | 10s | 485 | 7.7 min | 30.5 GB | ✅ |
| Native | 960×960 | 14s | 689 | 13 min | 30.8 GB | ✅ |
| Native | 960×960 | **16s** | 791 | 16 min | 30.8 GB | ✅ **960² 上限** |
| Native | 960×960 | 18s | — | 17s | 32.1 GB | ❌ OOM(初始化即爆) |
| Native | 960×960 | 20s | — | 12s | 32.1 GB | ❌ OOM |
| High Detail 0.59MP | 768×768 | 20s | 961 | 11 min | 31.7 GB | ✅ |
| Preview 0.41MP | 640×640 | 24s | 1165 | 8.5 min | 31.6 GB | ✅ |
| Preview 0.41MP | 640×640 | **32s** | 1539 | 13 min | 32.1 GB | ✅ **640² 上限** |

**结论**：5090 32G 上「时长×分辨率²」近似恒定（≈ 25 秒·MP 的预算）。
- 要 960² 原画质：**最长 16s**（≥18s 直接 OOM）。
- 降到 640²：**可到 32s**；768² 约 20s。
- 耗时随时长**超线性**增长（8s→16s 帧数 2×，耗时 3×）——即「10s 效率明显低」的实测原因，主因是注意力 token 数平方级。
- 显存墙是**硬墙**（peak 32.1/32.6GB 时直接 OOM），靠 `--lowvram`/steps↓/关 RIFE 能再挤一点，但会掉画质或丢音频。

**想同时要「长 + 高清」** → 换更大显存卡：heygirl 那台 `38.247.166.124`（RTX PRO 6000 **96G**）才是上限天花板（预计 960² 可到 40s+）。

> 目标：把 AI 生成（VIP 定制视频/图片，ComfyUI H3）从 AI 电脑迁到各自独立的 GPU 节点。
> 三项目独立（各自 服务器 + GPU + CDN）。换脸 face_swap 缓期（生产 0 任务、AI 电脑无对应 worker）。

## 三台 GPU 节点

| 项目 | 节点 IP | GPU | 后端 | 状态 |
|---|---|---|---|---|
| **heygirl** | `38.247.166.124` | RTX PRO 6000 96G | `https://heygirl.live/api` | ✅ **完成并端到端验收** |
| **sugus.ai** | `38.247.190.236` | RTX 5090 32G | `https://sugus.ai/api` | 软件栈✓；等 node3 推送模型 |
| **第三个项目** | `204.12.218.174` | RTX 5090 32G | **待确认** | 软件栈✓；拉模型中 |

- 三台凭据：`administrator` + 各自密码（166.124=`LtKxQj(SFR%un)4H`；190.236=`BbAkc^R#iyu8@Rcn`；204.174=`D69cY^hJ7Wy@VruG`），sudo 同密码
- 三台镜像一致：Ubuntu 24.04 / 32核 / 82G / 355G(`/`)+934G(`/data`) / 均有 `/opt/pytorch-env`

## 软件栈（三台均已就位并验证）

| 项 | 值 |
|---|---|
| ComfyUI | `/opt/comfyui/ComfyUI` v0.34.2 |
| venv | `/opt/comfyui/venv`，torch **2.11+cu128**，cap **(12,0)** Blackwell |
| custom_nodes | 30 个（MiniMaxH3-TimelineDirector / Auto-TensorRT-RIFE / sol-attn …）|
| TensorRT | **10.13.3.9 (cu12)** |
| 工作流节点 | 全部注册（MiniMaxH3ImageToVideo / SaveVideo / LoadImage / RandomNoise / AutoLoadRifeTensorrtModel / LoraLoaderModelOnly / UNETLoader / VAELoader / CLIPLoader）|
| ComfyUI 服务 | `comfyui.service` :8000（仅 127.0.0.1），开机自启 |
| 模型 | 15 个 / ~71GB（heygirl 已齐；另两台分发中）|

## 模型分发链路（关键）

AI 电脑 → 38 与 99.11 的跨境线路**被间歇封锁**，scp/SFTP 只有 0.3-0.5MB/s。最终方案：

```
AI电脑 --(FlClash代理:7890, dd流式, ~3MB/s)--> 99.11 暂存(/data/modelmig)
                                                    |
                                          (relay_99to38.sh, .ok标记) --> heygirl(166.124)  ← 模型源
                                                                            |
                                                              GPU↔GPU 同机房(12-28ms, ~52MB/s)
                                                                            |
                                        node3(204.174) <---- pull (robust_pull.sh, 自愈重试)
                                                                            |
                                        sugus(190.236) <---- push (push_to_sugus.sh; 因sugus出站被封)
```

- 关键：**166.124（heygirl）作模型源**，另两台从它拷，不再挤 AI 电脑慢线
- **sugus 出站 TCP 当前全封**（连 pypi 都不通），但入站正常 → 由 node3 反向推送
- 所有传输脚本幂等、断点续传、`.ok` 标记、**禁 rsync --inplace**

## ⚠️ 跨境线路注意事项

- 本机(Windows) → 166.124:22 **被封锁** → 用 99.11 跳板：`.zcode/ssh_38_jump.py`
- 本机 → 190.236 / 204.174:22 **可直连**。若某台被暂时封（ICMP 通但 TCP 超时），改走跳板或等待自愈
- 三台海外节点 ↔ 东京机（166.124/99.11/172）**时通时断** → 传输脚本必须带重试/续传

## 脚本位置（`.zcode/`）

| 脚本 | 用途 |
|---|---|
| `ssh_38_jump.py` | 经 99.11 跳板连 166.124 |
| `ssh_sugus_gpu.py` / `ssh_gpu3.py` | 直连 190.236 / 204.174 |
| `gpumig/fast_upload.py` | AI电脑→99.11 流式上传（走代理，续传）|
| `relay_99to38.sh`（99.11 上）| 99.11→166.124 中转 |
| `gpumig/robust_pull.sh` | 备机从 166.124 自愈拉取 |
| `gpumig/push_to_sugus.sh` | node3 反向推给 sugus |
| `gpumig/comfyui.service` / `custom-video-worker{,-sugus}.service` | systemd 单元 |
| `comfy_stage1/2_safe/3.sh` / `install_trt.sh` | ComfyUI 构建阶段 |

## ComfyUI 网页入口（已开通 ✅）

三台均装 nginx 反代 `127.0.0.1:8000` → 对外 `:8188`，HTTP Basic Auth，开机自启。

| 项目 | 访问地址 | 用户名 | 密码 |
|---|---|---|---|
| heygirl | `http://38.247.166.124:8188/` | `comfy` | `Oa5xRHX0oFHfrG` |
| sugus.ai | `http://38.247.190.236:8188/` | `comfy` | `aLN9cjk1rkNGQS` |
| 第三个项目 | `http://204.12.218.174:8188/` | `comfy` | `qjQpNHLC4hTgwD` |

- 配置：`/etc/nginx/sites-available/comfy`（`listen 8188`，WebSocket 升级、300m 上传、关闭 buffering）
- 凭据文件：`/etc/nginx/.htpasswd`
- 验证：无认证 → 401，带认证 → 200（`<title>ComfyUI</title>`）
- 改密码：`openssl passwd -apr1 '新密码'` 后替换 htpasswd 行，`systemctl reload nginx`

⚠️ **风险提示**：目前是 **HTTP 明文**，密码以 Base64 传输（Basic Auth 特性），且 ComfyUI 无多用户/权限隔离——拿到账号即可跑任意工作流、看模型、占满 GPU。若要更安全建议：加 IP 白名单，或改走域名+HTTPS。三台密码已设为**各不相同**。

## heygirl 端到端验收（已通过 ✅）

- worker `/opt/custom_video/worker.py`（worker_linux.py），`WORKER_API_BASE=https://heygirl.live/api`，`WORKER_ID=heygirl-h3`
- **视频**：任务 56 → `completed`，产出 `/storage/custom_video/20261010/cv_*.mp4`（3.2MB，公网 200 可播放）
- **图片**：任务 57 → `completed`，产出 `ci_*.webp`
- 首次运行自动构建 RIFE TensorRT 引擎（一次性），单条视频约 11 分钟（含首次）

## 待办

1. **确认第三个项目的后端 API 地址**（域名/服务器），才能配它的 worker
2. 备机模型齐后：配 worker + 端到端验收（sugus 和 第三项目）
3. sugus 的 `custom-video-worker.service` 已放 `/tmp`（WORKER_API_BASE=https://sugus.ai/api），待模型齐后启用
4. Phase 5/6：模板化脚本 + 健康检查 + 文档 + **口令轮换**（`.zcode/media_upload_daemon.py` 等内嵌明文口令）
5. 清理：heygirl 验证时插入的测试任务 57（可选）
