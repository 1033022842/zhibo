# 修复方案 A：禁用 Auto-TensorRT-RIFE 的 CUDA Graph 自动启用

> 执行环境：AI 电脑（192.168.31.225，Windows）
> 编写日期：2026-09-20
> 交付方式：本文档自包含，直接按步骤执行即可

## 背景（一句话）

`Auto-TensorRT-RIFE` 节点检测到 CUDA 13.0 时自动启用 CUDA Graph；TensorRT 引擎跨任务复用时，下一次任务在 `allocate_buffers` 里销毁旧 graph 后立即分配显存，触发 `CUDA error: invalid argument`，导致**隔次任务必失败**（成功→失败→成功→失败交替，已实测复现：任务 17 成功/18 失败同图同 prompt，19 成功/20 失败）。禁用 CUDA Graph 即可根治，代价仅是 RIFE 插帧略慢。

## 故障证据（供参考，无需操作）

- 报错堆栈：`Auto-TensorRT-RIFE\__init__.py:650 vfi` → `trt_utilities.py:479 allocate_buffers` → `.to(device)` 抛 `torch.AcceleratorError: CUDA error: invalid argument`
- H3 主生成（8/8 步）始终正常，错误全部发生在 RIFE 后处理阶段
- 同图同 prompt 的任务 17 成功、18 失败 —— 成败只取决于引擎调用次序（奇偶），与输入无关
- 相关故障：任务 12 曾在同一节点的 `trt_utilities.py:319 reset()` 处发生 `Fatal Python error: Aborted` 进程级崩溃，同族问题

## 目标文件

```
D:\Comfy-Desktop\ComfyUI-Installs\H3\ComfyUI\custom_nodes\Auto-TensorRT-RIFE\__init__.py
```

## 改动位置

`vfi` 函数内，约 **586–597 行**，搜关键字 `Auto-detect CUDA graph usage` 定位。

## 改前（原代码）

```python
            # Auto-detect CUDA graph usage based on CUDA version
            use_cuda_graph = False
            try:
                import torch
                cuda_version = torch.version.cuda
                if cuda_version and cuda_version.startswith('13'):
                    use_cuda_graph = True
                    print(f"✅ CUDA {cuda_version} detected - enabling CUDA graph for better performance")
                else:
                    use_cuda_graph = False
                    print(f"⚠️  CUDA {cuda_version} detected - disabling CUDA graph for stability")
            except:
                use_cuda_graph = False
                print("⚠️  Could not detect CUDA version - disabling CUDA graph for safety")
```

## 改后（整段替换为）

```python
            # 2026-09-20 禁用 CUDA graph：跨任务复用 TRT 引擎时，allocate_buffers 销毁旧 graph
            # 后立即分配显存会报 "CUDA error: invalid argument"，导致隔次任务必失败。
            # 详见 trt_utilities.py allocate_buffers() 开头的 cudaGraphDestroy 逻辑。
            use_cuda_graph = False
            try:
                import torch
                cuda_version = torch.version.cuda
                print(f"⚠️  CUDA {cuda_version} detected - CUDA graph force-disabled (cross-task graph reuse bug)")
            except:
                print("⚠️  Could not detect CUDA version - CUDA graph force-disabled")
```

**注意：只改这一处。** 同文件 626/639 行附近还有 `use_cuda_graph = False`（tiling 分支，本来就是 False），不要动；`trt_utilities.py` 不要动。

## 执行步骤

1. **备份原文件**：

   ```powershell
   Copy-Item "D:\Comfy-Desktop\ComfyUI-Installs\H3\ComfyUI\custom_nodes\Auto-TensorRT-RIFE\__init__.py" "D:\Comfy-Desktop\ComfyUI-Installs\H3\ComfyUI\custom_nodes\Auto-TensorRT-RIFE\__init__.py.bak.20260920"
   ```

2. **按上述 diff 修改 `__init__.py`**（保持 UTF-8 编码、换行符与原文件一致）

3. **语法校验**（必须通过）：

   ```powershell
   D:\Comfy-Desktop\ComfyUI-Installs\H3\ComfyUI\.venv\Scripts\python.exe -m py_compile "D:\Comfy-Desktop\ComfyUI-Installs\H3\ComfyUI\custom_nodes\Auto-TensorRT-RIFE\__init__.py"
   ```

4. **重启 ComfyUI**（改的是节点源码，必须重启加载）：
   - 先确认没有正在跑的任务：查 `http://127.0.0.1:8188/queue` 的 `queue_running` 是否为空；若有任务在跑，等它结束再执行
   - 杀掉 ComfyUI python 进程（命令行含 `ComfyUI\main.py` 的），约 1 分钟内 watchdog 会自动拉起；也可手动：

     ```powershell
     schtasks /run /tn SugusComfy
     ```

   - 等待 `http://127.0.0.1:8188/system_stats` 可访问（首次加载模型约 2–3 分钟）

## 验收标准

1. ComfyUI 日志（`C:\Users\Administrator\AppData\Roaming\Comfy Desktop\logs\app.log` 尾部）出现**新的一次**：

   ```
   ⚠️ CUDA 13.0 detected - CUDA graph force-disabled (cross-task graph reuse bug)
   ```

   且**不再出现** `✅ ... enabling CUDA graph`。

2. **连续跑 3 个视频生成任务全部成功**（关键验收 —— 原故障是隔次失败，跑 2 个不够，跑 3 个才能证明交替消失）。可在 https://sugus.ai/ai/Image.html 连续提交，或看 `D:\custom_video\worker.log` 连续 3 条 `完成 ->` 无 `失败: CUDA error`。

3. RIFE 仍正常工作：日志中 `⚡ [Rife Tensorrt] - done! - 123 new frames generated` 照常出现（只是不再走 graph 加速）。

## 回滚

```powershell
Copy-Item "D:\Comfy-Desktop\ComfyUI-Installs\H3\ComfyUI\custom_nodes\Auto-TensorRT-RIFE\__init__.py.bak.20260920" "D:\Comfy-Desktop\ComfyUI-Installs\H3\ComfyUI\custom_nodes\Auto-TensorRT-RIFE\__init__.py" -Force
```

再按「执行步骤」第 4 步重启 ComfyUI。

## 注意事项

- 重启 ComfyUI 会丢掉当前队列里正在跑的任务（等效取消，worker 会上报失败）—— 动手前务必确认 queue 为空
- 连续提交 3 个验证任务会消耗 demo 账号（user id=16）当日配额（DAILY_LIMIT=3 正好用完），属预期；如需再测，重置额度即可
