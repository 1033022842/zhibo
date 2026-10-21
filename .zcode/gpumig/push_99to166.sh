#!/bin/bash
# 99.11 → 166.124 快速续传（在 99.11 上运行）
# 用 dd 流式 seek 续传，避免 rsync --append-verify 每次重启都全量重读校验。
# 4 路并行；结束后按大小校验，再按需 md5。
SRC=/data/modelmig
DST=/data/models
KEY=/root/.ssh/relay166
LOG=/root/push166.log
PAR=4
MB=$((1024*1024))

log(){ echo "$(date '+%F %T') $*" >> "$LOG"; }

FILES="
diffusion_models/minimax_h3_fl2va_pruned_int8_convrot.safetensors 20970379616
text_encoders/qwen3vl_32b_heretic_minimax_h3_nvfp4.safetensors 15683129587
unet/qwen_image_edit_fp8_e4m3fn.safetensors 20430635136
vae/minimax_h3_video_vae_fp16.safetensors 5207808496
vae/minimax_h3_audio_vae_fp32.safetensors 605254808
vae/qwen_image_vae.safetensors 253806246
loras/minimax-h3_fl2v_8Step_motion_enhancer.safetensors 1956171592
loras/MM-H3 - Pussy Lora.safetensors 155132912
loras/soulmate_h3_v1.safetensors 155095800
loras/Qwen-Image-Edit-Remove-Clothes_V.1.safetensors 472047184
loras/cum_facial_000005400.safetensors 310168760
"

rsh(){ ssh -i $KEY -o StrictHostKeyChecking=no -o BatchMode=yes -o ConnectTimeout=20 \
       -o ServerAliveInterval=20 -o ServerAliveCountMax=6 administrator@38.247.166.124 "$@" 2>/dev/null; }

push_one(){
  rel="$1"; want="$2"
  cur=$(rsh "stat -c%s '$DST/$rel' 2>/dev/null || echo 0")
  cur=${cur:-0}
  if [ "$cur" = "$want" ]; then log "SKIP $rel ($want)"; return; fi
  if [ "$cur" -gt "$want" ]; then log "TRUNC $rel $cur>$want"; rsh "rm -f '$DST/$rel'"; cur=0; fi
  off=$(( cur / MB ))
  seek=$(( off * MB ))
  for att in 1 2 3 4 5 6 7 8; do
    log "PUSH[$att] $rel from $seek/$want"
    rsh "mkdir -p '$(dirname "$DST/$rel")'"
    # dd 在源端 seek 后流式发送，目标端 seek 写入（不截断已有前缀）
    dd if="$SRC/$rel" bs=1M skip=$off status=none \
      | rsh "dd of='$DST/$rel' bs=1M seek=$off conv=notrunc status=none"
    cur=$(rsh "stat -c%s '$DST/$rel' 2>/dev/null || echo 0"); cur=${cur:-0}
    if [ "$cur" = "$want" ]; then log "DONE $rel ($want)"; return; fi
    off=$(( cur / MB )); seek=$(( off * MB ))
    log "SHORT $rel $cur/$want, retry"
    sleep 10
  done
  log "GIVEUP $rel $cur/$want"
}
export -f push_one rsh log
export SRC DST KEY LOG MB

log "=== push start (par=$PAR) ==="
printf '%s\n' "$FILES" | grep -v '^$' | tr '\t' ' ' | \
  xargs -d '\n' -P $PAR -I{} bash -c 'push_one $1 "$2"' _ {}
log "=== push end ==="
rsh "ls -l $DST/*/* 2>/dev/null" >> "$LOG" 2>&1
