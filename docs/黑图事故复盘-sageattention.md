# 黑图事故复盘：`--use-sage-attention` × Qwen-Image-Edit

**发生时间**：2026-10-22 13:06 ~ 14:02（UTC+8）
**影响范围**：sugus 站 `custom_image` 任务 137 / 141 / 142（3 条），用户已被扣费但拿到纯黑图
**影响面**：只影响图片（Qwen-Image-Edit），视频（MiniMax H3）正常
**状态**：已修复，3 条任务已补生成，新增产物层兜底

---

## 一、现象

用户先报「图裂了」，排查后是签名 URL 过期（已单独修复）。修完签名后用户又发来：

```
https://media.sugus.ai/sec/sugus/custom_video/20261022/ci_sugus_137...png?e=...&s=...
链接是这个  黑的
```

实测：

| 项 | 结果 |
|---|---|
| HTTP 状态 | **200**（不是 403/410） |
| 文件大小 | 5,663 B（正常应 1.3 MB 左右） |
| 尺寸 | 768×1360，RGB，PNG 合法 |
| 内容 | **非黑像素 0.00%，min/max 亮度 0/0** —— 整张纯黑 |

同时 GPU 节点上 `custom_edit/sugus/` 里的 `task_137/141/142_00001_.png` 全是 ~5.6KB 纯黑，
而 `custom_video/sugus/` 的视频（139/140）是 3.7MB / 4.9MB 正常。
→ 故障只在图片分支。

---

## 二、定位过程

### 2.1 ComfyUI 日志里的关键一行

```
/opt/comfyui/ComfyUI/nodes.py:1698: RuntimeWarning: invalid value encountered in cast
  img = Image.fromarray(np.clip(i, 0, 255).astype(np.uint8))
```

`nodes.py:1698` 是 `VAEDecode` 落 PNG 的地方。`invalid value encountered in cast` 意味着
latent 里有 **NaN**：`np.clip(NaN, 0, 255)` 仍然是 NaN，`astype(np.uint8)` 把它变成 0，
于是 RGB 全 0 → 纯黑，而且**不抛异常**，任务照常"成功"。

统计：整个日志里 cast 警告 **恰好 3 次**，对应 **恰好 3 张黑图**。一一对应。

### 2.2 时间线切片

日志里 ComfyUI 重启过 3 次（`Starting server`），每次的注意力后端不同：

| 日志行 | 启动 | 注意力后端 | 图片任务结果 |
|---|---|---|---|
| 80 | 进程 A | pytorch attention | — |
| 147 | 进程 B | `Using pytorch attention` | 冒烟图 **1,398,676 B 正常** |
| 333 | 进程 C | **`Using sage attention`** | **137/141/142 全黑（3/3）** |

进程 B 的日志里还留着 `[QwenVL] SageAttention import failed: No module named 'sageattention'`
—— 那时 sageattention 还没装。之后装上 sageattention 并往 `comfyui.service` 加了
`--use-sage-attention`，进程 C 开始出现 NaN。

### 2.3 受控 A/B（决定性证据）

同一份 `qwenedit_api.json`、同一张源图（`task_sugus_137.jpg`，1079×1920）、同一段提示词：

| 配置 | 耗时 | 产物 | cast 警告 |
|---|---|---|---|
| **sage attention ON** | 30.1 s | 5,676 B，**0.00% 非黑** | 有 |
| **sage attention OFF** | 39.1 s | 1,368,129 B，**99.31% 非黑** | 无 |

再用正式 `comfyui.service`（去掉参数后重启）：

| 配置 | 耗时 | 产物 |
|---|---|---|
| 正式实例（pytorch attention） | 96.5 s | 1,354,805 B，99.69% 非黑 |

**结论：根因是 `--use-sage-attention`。**

### 2.4 为什么 sage 只打黑图片、不打黑视频

- 视频走 MiniMax H3 的 BF16 主路径，sage 在该路径上数值稳定（迁移期实测 44.9 it/s、42.3 GB，正常出片）。
- 图片走 `qwen_image_edit_fp8_e4m3fn`（**fp8** UNET）+ `ModelSamplingAuraFlow(shift=3)` + CFGNorm。
  fp8 权重在 sage 的 int8 attention kernel 下，Blackwell（sm_120）上溢出成 NaN。
- 更关键的是 `comfy/ldm/modules/attention.py` 的 `attention_sage()` **只 catch 异常**：

```python
try:
    out = sageattn(q, k, v, **sage_kwargs)
except Exception as e:
    logging.error("Error running sage attention: {}, using pytorch attention instead.".format(e))
    exception_fallback = True
```

NaN 不是异常，它会被原样返回并一路传到 VAE 解码 → 静默黑图。

---

## 三、修复

### 3.1 主修：移除 `--use-sage-attention`

```bash
sudo cp /etc/systemd/system/comfyui.service /root/comfyui.service.bak.20261022
sudo sed -i 's| --use-sage-attention||' /etc/systemd/system/comfyui.service
sudo systemctl daemon-reload && sudo systemctl restart comfyui
```

代价：视频单条从 462.8s 回到 ≈756s（慢 60%）。**这个代价必须付**——
出正常图比出快视频重要。

### 3.2 兜底：worker v6.1 拦截全黑产物

`/opt/cv/tpl/worker_gpu.py` 新增：

- `assert_not_black(path, what)` —— 图片：缩到 64×64 灰度，`max <= 8` 即判定全黑，抛 `RuntimeError`。
- `assert_video_not_black(video, duration)` —— 视频：取 25% / 50% / 75% 三帧，**任意一帧**有内容即通过
  （只看首帧会误杀「淡入」类视频）。

失败后走 `process()` 的 except → `POST /aiTask/fail` → `AiTaskService::failTask()` →
`refundCustomTask()` **自动退款**，用户不会「生成失败还扣费」。

对真实样本的验证：

```
black   CAUGHT: 全黑（max luminance=0），判定生成失败
good    PASS
good2   PASS
```

### 3.3 补回坏图

137/141/142 用原提示词 + 原源图重跑，全部上传覆盖：

| 任务 | 新产物 | 非黑像素 | 公网 |
|---|---|---|---|
| 137 | `ci_sugus_137.pne89efa7a0e1ed402d25854feaeb5af6e4d59f312.webp` | 99.46% | 200 / 101,564 B |
| 141 | `ci_sugus_141.pn8b01f0a290b84570a77f2188c888a2d008f8c344.webp` | 100.00% | 200 / 130,950 B |
| 142 | `ci_sugus_142.pn31f1899fb986686875320186ec628633ac4c6412.webp` | 99.49% | 200 / 103,120 B |

修复后用户自己提交的 147/148/149 三张也全部正常（1.5MB / 99%+）。

---

## 四、教训

1. **"跑成功" ≠ "产物可用"**。ComfyUI 返回 success、文件是合法 PNG、HTTP 200 —— 三个都过，
   图仍然可以是全黑。生成链路必须在产物层做一次内容断言。
2. **性能优化的开关要按工作流分支验证，不能按"整机"验证**。sage 在视频上快 1.63×，
   就把它写成全局 `--use-sage-attention`，结果打穿了 fp8 的图片分支。
   当时只在视频上做过 A/B，图片只跑过一次**没装 sage 时**的冒烟 —— 那次成功给了错误的安全感。
3. **`RuntimeWarning: invalid value encountered in cast` 是 NaN 的报警器**。
   这条警告在日志里出现了，但没人看；如果健康检查里加一条
   `grep -c 'invalid value encountered' 且增量 > 0 就报警`，能在第一张黑图时就发现。
4. **只在迁移期做冒烟是不够的**。冒烟用的是 800×800 测试图 + 默认短提示词，
   生产是 1079×1920 竖图 + 长英文穿搭提示词。真正暴露问题的是**真实任务**。
   → 验收必须用真实源图 + 真实提示词各跑一次。

## 五、后续可做（未做，按需）

- `gpu-health` 里加 NaN 警告增量检测，`/var/log/gpu-alert.log` 报警。
- 若确实需要视频提速：给 Qwen 图片分支单独关掉 sage（ComfyUI 侧按 UNET 类型判断），
  或改用 `--use-sage-attention` 但把图片任务路由到第二个不带该参数的实例（需第二份显存预算）。
