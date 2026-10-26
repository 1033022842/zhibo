# 单台 GPU 承载四站 AI 生成（38.247.166.124）

> 更新：2026-10-21
> 起因：原来的两台 GPU 节点（`38.247.190.236` / `204.12.218.174`，各 5090 32G）**欠费失联**，
> 而 AI 电脑只有一台机器、又只有局域网 —— 于是把四站（sugus / heygirl / kisss / kelymi）
> 的 AI 视频与图片生成全部收到一台高配机上。

## 一、机器与账号

| 项 | 值 |
|---|---|
| 公网 IP | `38.247.166.124` |
| 账号 | `administrator` / `WB*!uZfGJa9K7eG@`（sudo 同密码） |
| 系统 | Ubuntu 24.04 / kernel 6.8 / 32 核 / 82G 内存 / 16G swap |
| 磁盘 | `/` 398G（系统）、`/data` 984G（**模型与备份都放这里**） |
| GPU | NVIDIA RTX PRO 6000 Blackwell Server Edition，**97887 MiB**，驱动 595.58.03 |
| 旧角色 | 这台机早先跑过 heygirl 的并行 PHP/MySQL/SRS。**现在只当纯 GPU 机用**，那些服务未启动 |

> ⚠️ 本机 → `166.124:22` 经常被跨境线路封锁（ICMP 通、TCP 超时）。
> 控制面一律走东京 `172.81.99.11` 跳板：`.zcode/gpu.py`（`--sudo` / `--put` / `--get`）。

## 二、架构

```
              ┌────────────── 38.247.166.124 · RTX PRO 6000 96G ──────────────┐
              │                                                              │
              │  comfyui.service ── 127.0.0.1:8000（唯一实例，串行队列）        │
              │        ▲                                                     │
              │        │ 4 个 worker 共用一个 ComfyUI，任务自然排队              │
              │  /opt/cv/tpl/worker_gpu.py（唯一真本，v6.0）                    │
              │        │                                                     │
              │  custom-video-worker@sugus    WORKER_TAG=sugus               │
              │  custom-video-worker@heygirl  WORKER_TAG=heygirl             │
              │  custom-video-worker@kiss     WORKER_TAG=kiss                │
              │  custom-video-worker@kelymi   WORKER_TAG=kelymi              │
              └───────┬──────────────┬───────────────┬──────────────┬────────┘
                  https           https           https          https
              sugus.ai/api   heygirl.live/api   kisss.ai/api  kelymi.autos/api
```

**ComfyUI 不对外暴露**。要看界面就在本机做 SSH 隧道（经跳板）：

```bash
ssh -L 8188:127.0.0.1:8000 administrator@38.247.166.124   # 需先能直连
# 直连被封时：ssh -J root@172.81.99.11 -L 8188:127.0.0.1:8000 administrator@38.247.166.124
# 然后浏览器打开 http://127.0.0.1:8188
```

## 三、目录结构

```
/data/models/             15 个模型（70.7 GiB），ComfyUI 通过 extra_model_paths 指向这里
/data/backup/             模型备份（**欠费事故的教训：模型不可再生，必须留底**）
/data/build/              构建日志、comfyui.log
/opt/comfyui/ComfyUI      ComfyUI v0.34.2（core）
/opt/comfyui/venv         Python venv（torch 2.11+cu128）
/opt/comfyui/ComfyUI/custom_nodes             8 个生产必需节点
/opt/comfyui/ComfyUI/custom_nodes_disabled    其余 22 个（不影响生产，需要时 mv 回来）
/opt/cv/tpl/worker_gpu.py worker 唯一真本
/opt/cv/<site>/           env + 4 个工作流 JSON + wf_prompts.json + tmp/ + worker.log
```

## 四、四个 worker 的差异（只有这几个环境变量）

`/opt/cv/<site>/env`：

| 站点 | `WORKER_API_BASE` | `WORKER_ID` | `WORKER_TAG` |
|---|---|---|---|
| sugus | `https://sugus.ai/api` | `sugus-gpu-h3` | `sugus` |
| heygirl | `https://heygirl.live/api` | `heygirl-h3` | `heygirl` |
| kiss | `https://kisss.ai/api` | `kiss-gpu-h3` | `kiss` |
| kelymi | `https://kelymi.autos/api` | `kelymi-gpu-h3` | `kelymi` |

公共：`WORKER_KEY=live-ai-api-key-2026`、`COMFY_BASE=/opt/comfyui/ComfyUI`、
`WORKER_ROOT=/opt/cv/<site>`、`WORKER_GEN_TIMEOUT=3600`（含四站排队，给足 1 小时）。

轮询周期 = `15 + 站点偏移`（sugus 15s / heygirl 17s / kiss 19s / kelymi 21s），
避免 4 个进程同一秒抢队列。

## 五、worker v6.0 相对旧版改了什么（四站共用必须改）

| # | 旧版行为 | 四站共用后的后果 | v6.0 改法 |
|---|---|---|---|
| 1 | 参考图写 `input/task_{id}.jpg`，输出 `custom_video/task_{id}` | 各站 task_id 都从 1 开始，**A 站的 12 会覆盖 B 站的 12**，而且 ComfyUI 排队执行时才读文件 → 生成出别人的图 | 文件名与输出前缀都加站点标签：`task_sugus_12.jpg`、`custom_video/sugus/task_12` |
| 2 | `accept` 只看 HTTP 状态码 | 后端失败时返回 **HTTP 200 + code≠00000**，worker 当成接单成功 → 重复生成、成品丢弃 | 显式判 `code == "00000"`，否则放弃该任务 |
| 3 | API 调用无重试 | 跨境抖一下就调 `/aiTask/fail`，把已经生成好的视频扔掉 | `progress`/`uploadVideo`/`complete` 加指数退避重试（2~5 次） |
| 4 | 只有 15% / 35% 两个进度点 | 大任务在 96G 卡上跑很久，前端看不到心跳 | 生成期间每 60s 上报一次 progress（40→90%） |

## 六、日常运维

```bash
# 在 GPU 节点上（建议经 .zcode/gpu.py 执行）
gpu-health          # GPU/ComfyUI/4 个 worker/4 个后端 API/各站积压，一键体检
gpu-backlog         # 只看各站 pending / running / stuck

systemctl status comfyui
systemctl restart custom-video-worker@sugus
tail -f /opt/cv/heygirl/worker.log
journalctl -u custom-video-worker@kiss -n 50
tail -f /data/build/comfyui.log
```

本机侧：

```bash
python .zcode/gpu.py "gpu-health"            # 经跳板执行
python .zcode/gpu.py --sudo "systemctl restart comfyui"
python .zcode/gpu.py --put 本地文件 /data/xxx
```

### AI 电脑旧 worker（必须保持停止）

AI 电脑（`192.168.31.225`）上还留着 v5.0 的 `worker_v50.py`。**它必须停着**，否则会和 GPU 节点抢单：

- v5.0 不校验 `accept` 的返回值（后端失败是 HTTP 200 + `code!=00000`），抢单失败照样跑 →
  2026-10-22 出现过任务 139 被两台机器各生成一份、DB 只留后者的情况；
- v5.0 没有产物黑图兜底、没有站点隔离。

停止（2026-10-22 已执行，PID 28944/38216）：

```powershell
Get-CimInstance Win32_Process -Filter "Name='python.exe'" |
  Where-Object { $_.CommandLine -match 'worker_v50' } |
  ForEach-Object { Stop-Process -Id $_.ProcessId -Force }
```

`sugus` 的 `SugusWorker` 计划任务本来就是 `Disabled`，不会自动拉起。
确认没有抢单：`SELECT worker_id, COUNT(*) FROM lp_ai_task WHERE created_at >= NOW()-INTERVAL 1 DAY GROUP BY 1;`
应只出现 `sugus-gpu-h3` / `heygirl-h3` / `kiss-gpu-h3` / `kelymi-gpu-h3`。

要把它当冷备重新启用：手动起 `worker_v50.py` 即可，但**必须先给 v5.0 补上 accept 校验**，
否则重复生成会立刻回来。

## 六·五、性能实测（2026-10-21）

冒烟用工作流 = 生产 `h3_fingering_api.json`，输入 800×800 → 输出 **960×960 / 10.1s / 48fps / 带 AAC 音频**：

| 配置 | 单条耗时 | 稳态 it/s | 显存峰值 | 备注 |
|---|---|---|---|---|
| pytorch attention（首次含 RIFE TRT 建引擎 341s） | 1097 s | 51.5 s/it | 60.8 GB | `smoke/vidsmoke_*` |
| pytorch attention（扣掉建引擎） | ≈756 s | 51.5 s/it | 60.8 GB | — |
| **sage attention（仅视频）** | **462.8 s** | **44.9 s/it** | 42.3 GB | `smoke/vidsage_*`，**快 1.63×** |

- **视频走 sage attention，图片必须走 pytorch attention**（2026-10-22 起）。
  `--use-sage-attention` 是全局开关，会同时作用于 Qwen-Image-Edit 的 fp8 UNET，
  在 sm_120(Blackwell) 上产生 NaN → 纯黑图（详见 §七 第一条与
  `docs/黑图事故复盘-sageattention.md`）。因此该参数**已从 `comfyui.service` 移除**，
  视频单条回到 ≈756s。若要重新打开，必须先确认 Qwen 图片分支不会命中 sage 路径。
- `sageattn` 本身在 Blackwell 上跑视频是正常的（上表实测），问题只出在图片分支。
- RIFE TensorRT 引擎**首次**跑视频时构建（341s，一次性），之后命中
  `models/tensorrt/rife/*.trt` 直接加载。
- 图片工作流（Qwen-Image-Edit）单条 **30~40s**（pytorch attention，2026-10-22 实测）。
- **生产真实任务**（heygirl 站，7s 片长）：458s / 条；同时排队的第 2 条 924s（含等待）。
  即**四站串行，第 N 个用户等待 ≈ N × 单条耗时**。

> `ComfyUI-QwenVL-Mod` 报 `sageattn_qk_int8_pv_fp16_cuda` 导入失败 —— 那是该节点的
> 可选优化，生产工作流不走它，忽略即可。`llama_cpp` 缺失同理。

## 七、故障排查

| 现象 | 原因 | 处理 |
|---|---|---|
| **生成的图片是纯黑**（~5.6KB PNG，0% 非黑像素） | `--use-sage-attention` 在 sm_120 上跑 Qwen-Image-Edit fp8 产生 NaN，VAEDecode 静默截断成 0 | 从 `comfyui.service` 去掉该参数并重启；`grep 'Using sage attention' /data/build/comfyui.log` 出现即中招。worker v6.1 会主动拦截全黑产物 |
| 单条视频突然变慢 60% | `comfyui.service` 当前**故意**没开 `--use-sage-attention`（图片会黑）。要提速必须先解决图片分支 | 见上一条，不要盲目加回来 |
| `gpu-health` 报 ComfyUI 不通 | 节点依赖或模型路径问题 | `tail -50 /data/build/comfyui.log`，看 `IMPORT FAILED` |
| 4 个 worker 全 inactive | 极少见（各自独立单元） | `journalctl -u custom-video-worker@sugus -n 50` |
| 某站任务一直 pending | 该站 `.env` 的 `[AI] api_key` 与 `live-ai-api-key-2026` 不一致 | 改 `.env`，reload php-fpm |
| 任务一直 generating 不结束 | worker 崩在 accept 之后 | 每分钟的 `ai:task:cron` 会在 90 分钟后重试一次、再失败则退款；也可 `systemctl restart custom-video-worker@<site>` |
| 生成出来的图和上传的不一样 | **串台**（v6.0 之前的老问题） | 确认 `/opt/cv/tpl/worker_gpu.py` 是 v6.0+，且 `WORKER_TAG` 各站不同 |
| 视频回传 500 | 站点 `upload_max_filesize` 太小 | 四个站现已统一 512M；新站要同步改 |
| 显存被占满 | 模型常驻（正常），或上一个任务没退 | `nvidia-smi`；`systemctl restart comfyui` 释放 |

## 八、模型备份（重要）

**模型不可再生**：`qwen3vl_32b_heretic_*`、`MMH3_Perfect_fingering_*`、`MysticXXX_MMH3-V4`、
`cum_facial_*` 这些 LoRA 网上都下不到，唯一来源是 AI 电脑的磁盘。

```bash
# 备份（/data 有 934G 空闲，模型 70.7G，直接复制一份最省事）
sudo rsync -a /data/models/ /data/backup/models-$(date +%Y%m%d)/

# 恢复
sudo rsync -a /data/backup/models-YYYYMMDD/ /data/models/
systemctl restart comfyui
```

校验清单见 `docs/单GPU四站部署.md` 附录 A（逐文件精确字节数）。

## 九、如何再加第五个站

1. `sudo mkdir -p /opt/cv/<newsite>/{tmp}`，`chown administrator:administrator`
2. `cp /opt/cv/sugus/*.json /opt/cv/<newsite>/`
3. 写 `/opt/cv/<newsite>/env`（只改 4 个变量，`WORKER_TAG` 用新名字）
4. 在 `worker_gpu.py` 的 `POLL_OFFSET` 里加一项（避免同秒抢队列）
5. `sudo systemctl enable --now custom-video-worker@<newsite>`
6. 该站 `.env` 确认 `[AI] api_key = live-ai-api-key-2026`、PHP 上传 512M
7. 该站 `crontab`（`/etc/cron.d/douyin`）加 `ai:task:cron` 每分钟

## 附录 A · 模型清单（逐文件精确字节数）

| 目录 | 文件名 | 字节 |
|---|---|---|
| diffusion_models | minimax_h3_fl2va_pruned_int8_convrot.safetensors | 20970379616 |
| text_encoders | qwen3vl_32b_heretic_minimax_h3_nvfp4.safetensors | 15683129587 |
| unet | qwen_image_edit_fp8_e4m3fn.safetensors | 20430635136 |
| clip | qwen_2.5_vl_7b_fp8_scaled.safetensors | 9384670680 |
| vae | minimax_h3_video_vae_fp16.safetensors | 5207808496 |
| vae | minimax_h3_audio_vae_fp32.safetensors | 605254808 |
| vae | qwen_image_vae.safetensors | 253806246 |
| loras | minimax-h3_fl2v_8Step_motion_enhancer.safetensors | 1956171592 |
| loras | MMH3_Perfect_fingering_v1_000002000.safetensors | 155110392 |
| loras | MysticXXX_MMH3-V4.safetensors | 155095800 |
| loras | MM-H3 - Pussy Lora.safetensors | 155132912 |
| loras | soulmate_h3_v1.safetensors | 155095800 |
| loras | Qwen-Image-Edit-Remove-Clothes_V.1.safetensors | 472047184 |
| loras | cum_facial_000005400.safetensors | 310168760 |
| onnx | rife425_ensemble_False_scale_1_sim.onnx | 22777748 |

合计 **75,917,284,757 B = 70.7 GiB**。

## 附录 B · 从零重装这台 GPU 机

```bash
# 1) 基础包 + swap + 目录（swap 必须有：82G 内存加载 70G 模型容易触发 OOM killer）
sudo apt-get install -y ffmpeg git python3-venv rsync
sudo fallocate -l 16G /swapfile && sudo chmod 600 /swapfile && sudo mkswap /swapfile && sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
sudo mkdir -p /opt/comfyui /opt/cv/{sugus,heygirl,kiss,kelymi,tpl} /data/{models,backup,build}
sudo chown -R administrator:administrator /opt/comfyui /opt/cv /data

# 2) ComfyUI + torch
bash .zcode/gpumig/build_comfy_166.sh       # 需先上传到 ~/

# 3) 模型（唯一来源 AI 电脑）
#    AI 电脑 → 99.11 暂存 → 166（本机直传只有 0.2MB/s，不可用）
python .zcode/gpumig/model_pull_166.py      # 在 166 上跑，4 路并行 rsync

# 4) 节点依赖 + TensorRT（只装生产必需的 8 个节点）
bash .zcode/gpumig/build_nodes_min.sh

# 5) worker + 单元
python .zcode/gpumig/deploy_worker_stack.py

# 6) PHP 侧加固（deadline / 卡死回收 / 退款 / stats）
python .zcode/gpumig/deploy_php.py --apply

# 7) 体检
gpu-health
```
