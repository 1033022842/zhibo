#!/bin/bash
# Install custom_nodes dependencies + TensorRT(cu12) on the consolidated GPU node.
# Uses the same venv as ComfyUI. Idempotent; logs to /data/build/nodes_build.log.
set -uo pipefail
ROOT=/opt/comfyui
CN=$ROOT/ComfyUI/custom_nodes
LOG=/data/build/nodes_build.log
source $ROOT/venv/bin/activate
log(){ echo "$(date '+%F %T') $*" | tee -a "$LOG"; }

log "=== installing per-node requirements ==="
for d in "$CN"/*/; do
  name=$(basename "$d")
  req=""
  [ -f "$d/requirements.txt" ] && req="$d/requirements.txt"
  [ -z "$req" ] && continue
  log "--- $name ---"
  pip install -r "$req" >>"$LOG" 2>&1 || log "WARN $name requirements failed"
done

log "=== RIFE / TensorRT deps (cu12, must match torch cu128) ==="
pip install einops colored "polygraphy==0.49.26" cuda-python requests tqdm >>"$LOG" 2>&1 || log "WARN base deps"
pip install tensorrt-cu12==10.13.3.9 tensorrt-cu12-bindings==10.13.3.9 tensorrt-cu12-libs==10.13.3.9 >>"$LOG" 2>&1 \
  || log "WARN tensorrt install"

log "=== verify ==="
python - <<'PY' 2>&1 | tee -a "$LOG"
import importlib, sys
mods = ["torch", "transformers", "safetensors", "einops", "kornia", "torchsde", "spandrel"]
for m in mods:
    try:
        mod = importlib.import_module(m)
        print("OK  ", m, getattr(mod, "__version__", ""))
    except Exception as e:
        print("MISS", m, e)
try:
    import tensorrt
    print("OK   tensorrt", tensorrt.__version__)
except Exception as e:
    print("MISS tensorrt", e)
import torch
print("torch", torch.__version__, "cuda", torch.cuda.is_available())
PY
log "=== NODES BUILD DONE ==="
