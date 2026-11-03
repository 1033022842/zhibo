#!/bin/bash
# 从 Civitai 恢复 AI 工作室卡片所需模型（GPU 过期后重建用）
# 用法：bash restore_models.sh [lora|unet|all]
set -u
KEY="0dbef5f0c3adafb4a3da6c027ae40446"
PY=/opt/comfyui/venv/bin/python3
LORA_DIR=/data/models/loras
UNET_DIR=/data/models/diffusion_models
LOG=/tmp/restore_models.log

log(){ echo "$(date '+%F %T') $*" | tee -a $LOG; }

dl_lora(){
  local url="$1" out="$2"
  if [ -f "$LORA_DIR/$out" ] && [ "$(stat -c%s "$LORA_DIR/$out")" -gt 1000000 ]; then
    log "跳过已存在 $out"; return 0
  fi
  log "下载 $out"
  curl -sL --max-time 3600 -o "$LORA_DIR/$out" "$url" -H "Authorization: Bearer $KEY"
  local sz=$(stat -c%s "$LORA_DIR/$out" 2>/dev/null || echo 0)
  if [ "$sz" -lt 1000000 ]; then log "!! 失败 $out ($sz B)"; rm -f "$LORA_DIR/$out"; return 1; fi
  # 校验 safetensors 头 + 统计 adaln/attn 键（adaln 非 0 说明与 pruned 基座不兼容）
  $PY - "$LORA_DIR/$out" <<'PYEOF'
import json, os, struct, sys
p = sys.argv[1]
try:
    fh = open(p, 'rb'); n = struct.unpack('<Q', fh.read(8))[0]
    h = json.loads(fh.read(n))
    ks = [k for k in h if k != '__metadata__']
    adaln = sum(1 for k in ks if 'adaln' in k)
    attn = sum(1 for k in ks if 'attn' in k)
    print('   OK %.0f MB tensors=%d adaln=%d attn=%d' % (os.path.getsize(p)/1048576, len(ks), adaln, attn))
except Exception as e:
    print('   !! 文件无效:', e); sys.exit(1)
PYEOF
}

case "${1:-all}" in
  lora|all)
    mkdir -p "$LORA_DIR"
    # 文件名 = 卡片 lora_chain 里引用的名字，必须一致
    dl_lora "https://civitai.red/api/download/models/3374369?fileId=3262606" "MMH3_DeepThroat_v0.1.safetensors"
    dl_lora "https://civitai.red/api/download/models/3405852?fileId=3297235" "DR34ML4Y_AllInOne_NSFW_H3.safetensors"
    dl_lora "https://civitai.red/api/download/models/3308736?fileId=3193894" "HMMisDog_H3_v1.safetensors"
    # 下面 3 个 model id 来自旧机 _dl_loras.sh
    for spec in "2893742 HMMasturbation_v2.safetensors" "2846342 HMPussy_V2.safetensors" "2776852 zy_AmateurPov_K2_v1.safetensors"; do
      set -- $spec
      mid=$1; out=$2
      vid=$(curl -s --max-time 40 "https://civitai.red/api/v1/models/$mid" -H "Authorization: Bearer $KEY" \
            | $PY -c "import json,sys; d=json.load(sys.stdin); print(d['modelVersions'][0]['id'])" 2>/dev/null)
      if [ -z "$vid" ]; then log "!! 取不到 $out 的 version id (model $mid)"; continue; fi
      dl_lora "https://civitai.red/api/download/models/$vid" "$out"
    done
    ;;
esac
log "=== 完成，清单 ==="
ls -la "$LORA_DIR" | tail -20 | tee -a $LOG
