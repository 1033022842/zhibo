#!/bin/bash
# Build the ComfyUI runtime on the consolidated GPU node (166.124).
# Idempotent: safe to re-run; each stage logs and bails on hard failure.
# Logs: /data/build/comfy_build.log
set -uo pipefail

ROOT=/opt/comfyui
LOG=/data/build/comfy_build.log
mkdir -p /data/build "$ROOT"
log() { echo "$(date '+%F %T') $*" | tee -a "$LOG"; }

log "=== stage1: venv + torch(cu128) ==="
if [ ! -x "$ROOT/venv/bin/python" ]; then
  python3 -m venv "$ROOT/venv" || { log "FATAL venv create"; exit 1; }
fi
source "$ROOT/venv/bin/activate"
python -m pip install --upgrade pip wheel setuptools >>"$LOG" 2>&1 || log "WARN pip upgrade"
if python -c 'import torch' 2>/dev/null; then
  log "torch already present: $(python -c 'import torch;print(torch.__version__)')"
else
  log "installing torch torchvision torchaudio cu128"
  pip install torch torchvision torchaudio --index-url https://download.pytorch.org/whl/cu128 >>"$LOG" 2>&1 \
    || { log "FATAL torch install"; exit 1; }
fi
python - <<'PY' 2>&1 | tee -a "$LOG"
import torch
print("torch", torch.__version__, "cuda", torch.cuda.is_available())
if torch.cuda.is_available():
    print("device", torch.cuda.get_device_name(0), "cap", torch.cuda.get_device_capability(0))
PY

log "=== stage2: ComfyUI v0.34.2 ==="
if [ ! -d "$ROOT/ComfyUI/.git" ]; then
  rm -rf "$ROOT/ComfyUI"
  git clone https://github.com/comfyanonymous/ComfyUI.git "$ROOT/ComfyUI" >>"$LOG" 2>&1 \
    || { log "FATAL clone"; exit 1; }
fi
cd "$ROOT/ComfyUI" || exit 1
git fetch --tags >>"$LOG" 2>&1
git checkout v0.34.2 >>"$LOG" 2>&1 || log "WARN checkout v0.34.2 ($(git log --oneline -1))"
log "core version: $(grep __version__ comfyui_version.py 2>/dev/null)"
log "installing core requirements"
pip install -r requirements.txt >>"$LOG" 2>&1 || { log "FATAL requirements"; exit 1; }
mkdir -p input output temp
log "=== stage2 done: $(python -c 'import transformers;print("transformers",transformers.__version__)' 2>&1 | tail -1) ==="
log "=== BUILD OK ==="
