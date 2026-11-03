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
| 下载 | `https://civitai.red/api/download/models/3429412?fileId=3329966`（21.32GB） |

### ⚠️ beta7 有三个文件，选错会导致生成直接失败

**这是本次重建踩的最大的坑。** 三个文件大小接近，但内部结构不同：

| fileId | 大小 | tensors | `final_layer.video_out.bias` | 判定 |
|---|---|---|---|---|
| **3329966** | **21.32 GB** | 892 | **`[96]`** | ✅ **正确，用这个** |
| 3330999 | 37.46 GB | 532 | `[96]` | 结构也对，但 bf16 更大更慢 |
| 3346133 | 19.61 GB | 932 | `[3072]` | ❌ **head 折叠格式，ComfyUI 加载器不认** |

**用 3346133 的后果**（我一开始就是按「最接近 20GB」选了这个，错了）：

```
RuntimeError: Error(s) in loading state_dict for MiniMaxH3Model:
  size mismatch for final_layer.video_out.bias: copying a param tensor of shape
  torch.Size([3072]) from checkpoint, the shape in current model is torch.Size([96]).
  size mismatch for final_layer.audio_out.bias: ... [1024] vs [32]
```

它的 metadata 暴露了来源：

```
converted_by = QrusherZA Custom Script
pdd_head_storage = head0-full;heads1-31-offset-from-head0;fp32
qrusher_fast_h3_applied = v2_r64
block_range_replace_source = fasth3_omniref_8step_pruned_bf16.safetensors
```

即第三方把 32 个 head 折叠进 `final_layer`（96×32=3072），需要配套的加载器支持，
标准 ComfyUI 会按 96 维去加载，必然 shape mismatch。

**判断方法（不用下 20GB）**：只取文件头即可。Civitai 的下载 URL 会 307 跳到 R2，
必须跟随跳转后用 Range 取前 400KB：

```bash
KEY="0dbef5f0c3adafb4a3da6c027ae40446"
URL=$(curl -s -o /dev/null -w '%{redirect_url}'   "https://civitai.red/api/download/models/3429412?fileId=3329966" -H "Authorization: Bearer $KEY")
curl -s -L -r 0-399999 "$URL" -o hdr.bin
python3 -c "
import json,struct
b=open('hdr.bin','rb').read()
n=struct.unpack('<Q',b[:8])[0]
h=json.loads(b[8:8+n])
print('tensors', len([k for k in h if k!='__metadata__']))
print('video_out.bias', h['final_layer.video_out.bias']['shape'])  # 必须是 [96]
"
```

> 注意：直接对 `civitai.red/api/download/...` 发 `-r` 无效，CDN 会忽略 Range 返回整个流。
> 必须先拿 `redirect_url` 再对 R2 地址发 Range。

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

## 四之二、本次（2026-11-03）实测记录

重建过程完整跑通，留档供比对：

| 步骤 | 结果 |
|---|---|
| 7 个 LoRA 下载 | 全部成功，`adaln=0`，tensors/attn 数正常 |
| 基座 3346133（19.61GB） | ❌ 加载失败：`video_out.bias [3072] vs [96]` |
| 基座 3329966（21.32GB） | ✅ `892 tensors / video_out=[96] / audio_out=[32]` |
| ComfyUI 重启后可见性 | ✅ UNETLoader 列表包含该模型 |
| 工作流接线 | ✅ 8 项全过（Ref2VA / t8 / 无 first_frame / ref_images / beta57 / 640 / 3 层 LoRA） |
| 真实生成 | ✅ 出片 1.3MB，单条约 3.5 分钟（首次含模型加载） |

### 两个额外发现

**1. ComfyUI/input 缺人设图**

真实生成第一次报：

```
Invalid image file: persona_020.jpg  (node 114 LoadImage)
```

`build_workflow` 用的是 `persona_XXX.jpg` 作参考图，这些图在旧机的
`/opt/comfyui/ComfyUI/input/` 里。新机要补：

```bash
scp _persona_out/persona_*.jpg administrator@<gpu>:/opt/comfyui/ComfyUI/input/
```

生产链路（worker 的 `stage_input()`）会自己下载用户上传的图，不依赖这些，
但**跑验证脚本 / 手工测试**时需要它们。

**2. `audio_scale` 报错是瞬时的**

日志里出现过一次：

```
AttributeError: 'ModelSamplingAdvanced' object has no attribute 'audio_scale'
```

但同一份代码实测 `ModelSamplingAV.audio_scale` 存在、MRO 正确、节点里定义的
`class ModelSamplingAdvanced(ModelSamplingAV, CONST)` 也有该属性；之后连续 5 次
prompt 全部成功。T8 节点包 `sampling.py:40` 还有 `callable(getattr(base_model,
"audio_scale", None))` 的防御。

结论：**这个不是必现的阻塞问题**，多半是旧版节点包在某个路径下的残余调用。
若后续生产通道真的复现，再考虑升级节点包；本次不需要。

**3. RIFE TensorRT 首次构建很慢**

新机第一次生成会卡在 RIFE 引擎构建（日志 `Time taken to build: 325.8s`），
期间 GPU 利用率 0%，容易误判为卡死。之后复用 `.trt` 文件，不再重build。

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
