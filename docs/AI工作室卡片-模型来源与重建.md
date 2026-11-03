# AI 工作室卡片 — 模型来源与重建手册

> 2026-11-03 编写。旧 GPU 机过期后，卡片功能因缺模型而中断；本文记录**每个文件的确切来源**，
> 下次换机按此重下即可，不必再翻历史。
> 验证脚本：`.zcode/gpumig/verify_cards_after_restore.py`

---

## 一、为什么需要这份文档

卡片功能的基座 `h3ErosMax_beta7_int8.safetensors` 和 7 个 LoRA **不在**常规四站部署的模型清单里
（`verify_models.sh` 只覆盖官方 H3 通道用的那几个）。旧机一过期，这些文件就没了，
卡片全部跑不起来——工作室页面能开、点生成报模型缺失。

---

## 二、UNET 基座（最关键，约 20GB）

| 项 | 值 |
|---|---|
| 使用名 | `h3ErosMax_beta7_int8.safetensors` |
| 放在 | `/data/models/diffusion_models/` |
| Civitai 模型 | **2851079**「H3 Eros Max」（作者页面：civitai.red/models/2851079） |
| 版本 | beta7（version id **3429412**） |
| 下载 | `https://civitai.red/api/download/models/3429412?fileId=3346133` |

### beta7 有三个文件，必须选对

| fileId | 大小 | 精度 | 说明 |
|---|---|---|---|
| 3329966 | 21.32 GB | int8 | 也可用，但比 3346133 旧 |
| 3330999 | 37.46 GB | bf16 | 太大，且精度更高未必更好 |
| **3346133** | **19.61 GB** | **int8** | ✅ **当时用的就是这个**（fileId 最大 = 最新） |

判断依据：
1. 用户记录写「约 20GB」，19.61GB 最贴合；21.32GB / 37.46GB 都不符
2. worker / 卡片 SQL 里写死的 `unet_name` 就是 `h3ErosMax_beta7_int8.safetensors`，精度必须是 int8
3. beta7 描述：「Fast-Omni hybrid timestep with no gates, still 90% sparsity and 12/3 shift.
   Complex turbo merge for fairly decent 8 step quality. Much more simple and pruned-down
   MLP-layers merge.」— 这正是 8 步出图的依据

### 下载命令

```bash
KEY="0dbef5f0c3adafb4a3da6c027ae40446"   # Civitai token（读写均可）
cd /data/models/diffusion_models
curl -sL --max-time 7200 -o h3ErosMax_beta7_int8.safetensors.part \
  "https://civitai.red/api/download/models/3429412?fileId=3346133" \
  -H "Authorization: Bearer $KEY"
mv h3ErosMax_beta7_int8.safetensors.part h3ErosMax_beta7_int8.safetensors
```

> 用 `civitai.red`（不是 civitai.com，后者对 NSFW 内容区域封锁）。
> GPU 机可以直接连，不需要代理。

---

## 三、7 个卡片 LoRA

全部放 `/data/models/loras/`。**文件名必须与下表一致**——卡片 SQL 的 `lora_chain`
里写死了这些名字，改名会导致 worker 找不到。

| 使用名（必须一致） | Civitai 模型 | version / fileId | 大小 |
|---|---|---|---|
| `MMH3_DeepThroat_v0.1.safetensors` | 2023407 Minimax H3（作者名 v0.1） | 3374369 / 3262606 | 284.4 MB |
| `DR34ML4Y_AllInOne_NSFW_H3.safetensors` | 1811313 DR34ML4Y_H3_P1 | 3405852 / 3297235 | 564.0 MB |
| `Minimax_H3_Unlocked_V2.1.safetensors` | **2925727**「MiniMax H3 NSFW Unlocked」 | 3413468 / 3305979 | 284.4 MB |
| `zy_AmateurPov_K2_v1.safetensors` | 2776852 | 取 `modelVersions[0].id` | 218.0 MB |
| `HMMisDog_H3_v1.safetensors` | 2924146（v1.0） | 3308736 / 3193894 | 295.8 MB |
| `HMMasturbation_v2.safetensors` | 2893742 | 取 `modelVersions[0].id` | 295.8 MB |
| `HMPussy_V2.safetensors` | 2846342 | 取 `modelVersions[0].id` | 74.0 MB |

### 几个容易踩的坑

- **下载名 ≠ 使用名**。Civitai 上 DeepThroat 的实际文件名是
  `MMH3_AllInOne-000004.comfy.safetensors`，我们重命名成了 `MMH3_DeepThroat_v0.1.safetensors`。
  所以**不能靠文件名搜模型**，要用 model id。
- **HMPussy_V2 只有 74MB**，比其它 284~296MB 小很多，这是正常的（rank 更低），不是下载不全。
- **HMMisDog 和 HMMasturbation 字节数完全相同**（都是 310168344），但**内容不同**——
  已用 tensor 偏移抽样 md5 验证。不要因为大小一样就以为下重了。
- **Unlocked 全站搜 "unlocked" 有 15 个结果，14 个是 Pony/Flux 的**，只有
  2925727 是 MiniMax H3 基座。用 `nsfw=true` 参数才会出现。

### 批量下载脚本

见 `.zcode/gpumig/restore_models.sh`（本次新建），跑 `bash restore_models.sh lora` 即可。
脚本会：跳过已存在 → 下载 → 校验 safetensors 头 → 统计 `adaln` / `attn` 键数。

### 兼容性判据（重要）

**`adaln` 必须为 0**。旧机实测结论：

> `turbo_4step_ema` 这类带 102 个 `adaln_*` 键（2688 维）的 LoRA，在 pruned 基座（8 维 AdaLN）上
> 会被**静默丢弃**，不报错但完全不起作用。

本文 7 个 LoRA 实测全部 `adaln=0`，可安全用于 pruned 基座。换新 LoRA 时务必先验这一项。

---

## 四、其它依赖（新机已具备，此处备查）

| 项 | 路径 | 说明 |
|---|---|---|
| CLIP | `/data/models/text_encoders/qwen3vl_32b_heretic_minimax_h3_nvfp4.safetensors` | 14.6 GB |
| Video VAE | `/data/models/vae/minimax_h3_video_vae_fp16.safetensors` | 4.85 GB |
| Audio VAE | `/data/models/vae/minimax_h3_audio_vae_fp32.safetensors` | 577 MB |
| 工作流模板 | `/opt/cv/short/{sugus,heygirl}/short_base.json` | 7069 B（legacy 版） |
| T8 节点包 | `/opt/comfyui/ComfyUI/custom_nodes/comfyui-minimax-h3-audio-T8/` | 2026-10-23 版 |
| TimelineDirector | `/opt/comfyui/ComfyUI/custom_nodes/ComfyUI-MiniMaxH3-TimelineDirector/` | 2026-10-23 版 |

> `short_base.json` 是 legacy（`MiniMaxH3ImageToVideo`）模板，但 worker 的 `build_workflow()`
> 在 `pipeline=t8` 时会把它动态替换成 `MiniMaxH3AudioConditioningT8` 节点，所以模板本身是
> legacy 版不影响卡片。它只提供 UNETLoader / VAE / CLIP / 采样器等骨架节点。

---

## 五、恢复后的验证步骤

```bash
# 传到 GPU
scp .zcode/gpumig/verify_cards_after_restore.py administrator@<gpu>:/opt/cv/short/

# 只检查文件/接线（不生成，秒级）
ssh administrator@<gpu> "/opt/comfyui/venv/bin/python3 /opt/cv/short/verify_cards_after_restore.py"

# 连真实生成一起验（约 3 分钟）
ssh administrator@<gpu> "/opt/comfyui/venv/bin/python3 /opt/cv/short/verify_cards_after_restore.py --generate"
```

检查四层：

1. **模型文件** — UNET + 7 LoRA + CLIP/VAE 存在且大小合理
2. **ComfyUI 可见性** — `/object_info` 里能查到这些模型名、T8 节点已注册
3. **工作流接线** — Ref2VA / t8 / 无 first_frame / 有 ref_images / beta57 / 3 层 LoRA
4. **真实生成** — 提交一条 Ref2VA，产物非空

### 重启 worker

模型下载完**必须重启 worker**，否则 ComfyUI 的模型缓存可能还是旧的：

```bash
sudo systemctl restart comfyui
sudo systemctl restart custom-short-worker@sugus custom-short-worker@heygirl
```

---

## 六、历史背景

- 2026-10-28~30：在旧 GPU 机上做 LoRA 隔离实验，确定「每多叠一层 LoRA，细节量继续下降」，
  最终收敛为 36 张卡片、每张 1~3 层 LoRA。
- 2026-10-31：卡片功能上线 sugus，随后同步 heygirl（同客户）。共 369 条样片。
- 2026-11-01~02：旧 GPU 机到期失联。
- 2026-11-03：新机 `38.247.166.124` 重建，按本文档重下模型。

### 当时的环境参数（供比对）

| 参数 | 值 |
|---|---|
| 采样器 | euler |
| 调度器 | beta57 = `BetaSamplingScheduler(alpha=0.5, beta=0.7)` |
| 步数 | 8 |
| denoise | 1.0 |
| 分辨率 | `Preview — 640×640`（输出 480×864） |
| 时长 | 10 秒 |
| 任务类型 | I2VA（A 组）/ Ref2VA（B、C、d11 组） |
| pipeline | t8（全部，走 `MiniMaxH3AudioConditioningT8`） |

各组 LoRA 组合见 `lp_short_preset` 表的 `lora_chain` 字段，或 `php/sql/_cards21_insert.sql`、
`php/sql/_cards_d11_insert.sql`。
