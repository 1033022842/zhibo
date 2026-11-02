# MiniMax H3 视频生成标准配置

> 最后更新：2026-10-30
> 本文档是**唯一权威配置**，后续所有视频生成任务必须按此执行。

---

## 一、采样参数（必须严格遵守）

| 参数 | 值 | 说明 |
|---|---|---|
| `sampler` | **euler** | 原作者验证值 |
| `scheduler` | **beta57** | 见下方「beta57 是什么」 |
| `steps` | **8** | 原作者验证值 |
| `denoise` | **1.0** | |
| `cfg` | 1.0（BasicGuider，无 CFG） | H3 架构无需 CFG |

### beta57 是什么

**不需要额外插件。** beta57 就是 ComfyUI 内置的 beta 调度器，用 `alpha=0.5, beta=0.7` 调用：

```python
wf["105:9"] = {
    "class_type": "BetaSamplingScheduler",
    "inputs": {
        "model": ["105:193", 0],
        "steps": 8,
        "alpha": 0.5,    # ← 关键
        "beta": 0.7,     # ← 关键
    },
}
```

**不要**在 `BasicScheduler` 里写 `scheduler="beta"`，那走默认的 0.6/0.6，不是 beta57。

来源：https://comfy.icu/node/Beta57Scheduler
> "RES4LYF's beta57 is ComfyUI's stock beta scheduler — the same function behind the built-in BetaSamplingScheduler node — just called with alpha=0.5, beta=0.7 instead of the default 0.6/0.6."

---

## 二、模型

| 项 | 文件 |
|---|---|
| UNET | `h3ErosMax_beta7_int8.safetensors` |
| CLIP | `qwen3vl_32b_heretic_minimax_h3_nvfp4.safetensors` |
| Video VAE | `minimax_h3_video_vae_fp16.safetensors` |
| Audio VAE | `minimax_h3_audio_vae_fp32.safetensors` |
| 分辨率 | `Preview — 640×640`（→ 480×864） |
| 时长 | 10 秒 |

---

## 三、任务类型：Hybrid（关键）

双参考图必须用 **`Hybrid`**，不能用 `Ref2VA`。

### 为什么

`conditioning.py` 的 `requirements` 表：

```python
"ref2va": (False, False, True),   # 禁止 first/last frame → 接 first_frame 直接报错
"hybrid": (None, None, True),     # None = 不限制 → 允许 first_frame + ref_images
```

### 机制

| 输入 | 强度 | 负责什么 |
|---|---|---|
| `first_frame` | **100% 硬锚定**（第 0 帧原样） | 姿势、机位、构图 |
| `ref_image_0` | 软条件（`VISUAL_COND_TIMESTEP=0.999` 注入一次） | 角色身份（脸、发色） |

**单独用 ref_image 时身份传不进去**（只传空间信息）；加上 first_frame 让构图被硬锁后，ref_image 的身份信号才能生效。已实测：粉发/金发角色都能成功替换。

### 配置

```python
wf["105:104"] = {
    "class_type": "MiniMaxH3AudioConditioningT8",
    "inputs": {
        "task_type": "Hybrid",
        "first_frame": ["205", 2],              # 姿势帧 → <Picture 1>
        "ref_images.ref_image_0": ["206", 2],   # 角色图 → <Picture 2>
        "ref_image_size": "max",
        "strict_prompt_tags": True,
        ...
    },
}
```

### 已知代价

`first_frame` 意味着**第 0 帧就是姿势帧原样**（原角色的发色），1–3 秒后才过渡成目标角色。这是硬锚定的必然结果。

---

## 四、提示词结构

用 **六段式 + Reference descriptions**（DaSiWa / REF2VA 格式）：

```
subject_definitions:
<Picture 1>: the exact first frame — pose, body position, camera angle and scene composition.
<Picture 2>: the identity reference — face, hair color, hairstyle, skin tone, body proportions.

summary:
一句话总结

retention_analysis:
From <Picture 1>: retain ONLY pose/camera/framing. Do NOT retain face, hair colour or skin tone.
From <Picture 2>: retain ONLY face, hair, skin. Do NOT copy its pose or background.

detailed_description:
[Shot 1] 分镜细节（镜头运动、动作时间轴）

Reference descriptions:
<Picture 1>: 紧贴该图的空间描述
<Picture 2>: 紧贴该图的外貌描述（关键特征要短、要聚焦）

overall_soundscape: ...

non_diegetic_music: ...
```

**关键要点**：
- `retention_analysis` 里必须写 `Do NOT ...`，否则模型会把参考图的无关特征也带进来
- `Reference descriptions` 要**短**，长描述会稀释关键特征

---

## 五、LoRA

| LoRA | 强度 |
|---|---|
| `HMMisDog_H3_v1.safetensors` | 0.85 |
| `Minimax_H3_Unlocked_V2.1.safetensors` | 0.4 |
| `DR34ML4Y_AllInOne_NSFW_H3.safetensors` | 0.9 |

串联顺序：HMMisDog → NSFW Unlocked → DR34ML4Y（`LoraLoaderModelOnly` 逐层接 `105:197`）。

**不要用的**：
- `MysticXXX_MMH3-V4` — 元数据被洗，实测有害
- `HMCumshot` — 音频严重失真
- `turbo_4step_ema` — adaln 键在 pruned 基座被静默丢弃
- `motion_enhancer` — 只服务 8 步官方模型，与 Eros Max 冗余

---

## 六、参考脚本

- `.zcode/gpumig/run_beta57.py` — 双参考图 Hybrid 完整实现
- 产物目录：`/opt/cv/short/qa/beta57/`

---

## 七、验证过的负面结论（避免重复踩坑）

| 尝试 | 结果 |
|---|---|
| 双 `ref_image`（无 first_frame） | 身份传不进去，只能局部影响发色 |
| Flux Kontext 双图 | 姿势完全丢失 |
| Flux ControlNet canny | 视角漂移、画面过暗，极端机位不可控 |
| 固定 seed 20261028 | 可复现但会让多条视频长得一样 |
| 25 步高采样 | 无改善，耗时翻倍 |
| MysticXXX LoRA | 结构崩坏 |
| 提高 `visual_cond_noise_aug` 强度 | 只能衰减不能增强（strength ≤ 1） |
