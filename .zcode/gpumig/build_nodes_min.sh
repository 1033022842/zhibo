#!/bin/bash
# 只为「四站 AI 生成」需要的节点装依赖。
# 其余 20 多个节点（impact-pack / controlnet_aux / wanvideo / was-node-suite ...）
# 先把目录挪到 custom_nodes_disabled，避免启动时拖进几百 MB 依赖与 sam2 源码编译。
set -uo pipefail
ROOT=/opt/comfyui
CN=$ROOT/ComfyUI/custom_nodes
DIS=$ROOT/ComfyUI/custom_nodes_disabled
LOG=/data/build/nodes_min.log
source $ROOT/venv/bin/activate
log(){ echo "$(date '+%F %T') $*" | tee -a "$LOG"; }

# 工作流真正用到的节点（class_type 反查得出）
NEED="ComfyUI-MiniMaxH3-TimelineDirector ComfyUI-PerfectVideoResolution ComfyUI-sol-attn comfyui-videohelpersuite Auto-TensorRT-RIFE qweneditutils ComfyUI-QwenVL-Mod comfyui-custom-scripts"
mkdir -p "$DIS"

log "=== 移出不需要的节点 ==="
for d in "$CN"/*/; do
  n=$(basename "$d")
  keep=0
  for k in $NEED; do [ "$n" = "$k" ] && keep=1; done
  [ "$n" = "__pycache__" ] && continue
  if [ "$keep" = "0" ] && [ ! -e "$DIS/$n" ]; then
    mv "$d" "$DIS/$n" && log "  moved out: $n"
  fi
done

log "=== 装依赖（只装需要的节点）==="
for n in $NEED; do
  d="$CN/$n"
  [ -d "$d" ] || { log "MISSING $n"; continue; }
  [ -f "$d/requirements.txt" ] || { log "no reqs: $n"; continue; }
  log "--- $n ---"
  pip install -r "$d/requirements.txt" >>"$LOG" 2>&1 || log "WARN $n requirements failed"
done

log "=== RIFE/TensorRT (cu12 与 torch cu128 匹配) ==="
pip install einops colored "polygraphy==0.49.26" cuda-python requests tqdm >>"$LOG" 2>&1 || log "WARN base deps"
pip install tensorrt-cu12==10.13.3.9 tensorrt-cu12-bindings==10.13.3.9 tensorrt-cu12-libs==10.13.3.9 >>"$LOG" 2>&1 \
  || log "WARN tensorrt"

log "=== 验证 ==="
python - <<'PY' 2>&1 | tee -a "$LOG"
import importlib
for m in ["torch","transformers","safetensors","einops","kornia","torchsde","spandrel","numpy"]:
    try:
        mod=importlib.import_module(m); print("OK  ",m,getattr(mod,"__version__",""))
    except Exception as e: print("MISS",m,e)
try:
    import tensorrt; print("OK   tensorrt",tensorrt.__version__)
except Exception as e: print("MISS tensorrt",e)
PY
log "=== NODES MIN BUILD DONE ==="
