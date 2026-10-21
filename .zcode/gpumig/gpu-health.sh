#!/bin/bash
# 单 GPU 四站健康检查 —— 在 GPU 节点上直接跑（/usr/local/bin/gpu-health）
# 检查项：GPU / ComfyUI / 4 个 worker / 4 个后端 API / 各站任务积压
# 退出码：0 全绿；1 有异常

SITES="sugus heygirl kiss kelymi"
FAIL=0
ok()   { printf '  \033[32mOK\033[0m   %s\n' "$*"; }
bad()  { printf '  \033[31mFAIL\033[0m %s\n' "$*"; FAIL=1; }
warn() { printf '  \033[33mWARN\033[0m %s\n' "$*"; }

echo "== GPU =="
if command -v nvidia-smi >/dev/null; then
  nvidia-smi --query-gpu=name,utilization.gpu,memory.used,memory.total,temperature.gpu \
             --format=csv,noheader | sed 's/^/  /'
  # 显存占用 > 90% 只告警，因为模型常驻是正常的
  used=$(nvidia-smi --query-gpu=memory.used --format=csv,noheader,nounits | tr -d ' ')
  total=$(nvidia-smi --query-gpu=memory.total --format=csv,noheader,nounits | tr -d ' ')
  if [ -n "$used" ] && [ -n "$total" ] && [ "$total" -gt 0 ]; then
    pct=$((used * 100 / total))
    [ "$pct" -gt 95 ] && warn "显存使用 ${pct}%（模型常驻时正常）"
  fi
else
  bad "nvidia-smi 不存在"
fi

echo "== swap =="
swapon --show | sed 's/^/  /'
[ -z "$(swapon --show)" ] && warn "无 swap（ComfyUI 加载大模型时可能触发 OOM）"

echo "== ComfyUI =="
if curl -sf --max-time 8 http://127.0.0.1:8000/system_stats >/dev/null; then
  ok "127.0.0.1:8000 可访问"
  q=$(curl -sf --max-time 8 http://127.0.0.1:8000/queue 2>/dev/null)
  if [ -n "$q" ]; then
    echo "$q" | python3 -c 'import sys,json;d=json.load(sys.stdin);print("  队列: %d 运行 / %d 排队" % (len(d.get("queue_running") or []), len(d.get("queue_pending") or [])))' 2>/dev/null
  fi
else
  bad "ComfyUI 8000 不可访问: $(systemctl is-active comfyui 2>/dev/null)"
fi

echo "== worker =="
for s in $SITES; do
  st=$(systemctl is-active "custom-video-worker@$s" 2>/dev/null)
  if [ "$st" = "active" ]; then
    # 最近 10 分钟是否有日志活动
    last=$(tail -1 "/opt/cv/$s/worker.log" 2>/dev/null | cut -c1-19)
    ok "worker@$s active  ${last:-（无日志）}"
  else
    bad "worker@$s $st"
  fi
done

echo "== 后端 API =="
declare -A API=(
  [sugus]="https://sugus.ai/api/customVideo/pending?count=1"
  [heygirl]="https://heygirl.live/api/customVideo/pending?count=1"
  [kiss]="https://kisss.ai/api/customVideo/pending?count=1"
  [kelymi]="https://kelymi.autos/api/customVideo/pending?count=1"
)
for s in $SITES; do
  code=$(curl -s -o /dev/null -w '%{http_code}' --max-time 12 \
         -H 'X-Api-Key: live-ai-api-key-2026' -H "X-Worker-Id: healthcheck" "${API[$s]}")
  if [ "$code" = "200" ]; then ok "$s 200"; else bad "$s HTTP $code"; fi
done

echo "== 各站任务积压（pending / accepted）=="
if [ -x /usr/local/bin/gpu-backlog ]; then
  gpu-backlog
else
  echo "  （未安装 gpu-backlog，跳过；见 docs/单GPU四站部署.md）"
fi

echo
if [ "$FAIL" = "0" ]; then
  printf '\033[32mALL OK\033[0m\n'
else
  printf '\033[31m有异常，见上面 FAIL 行\033[0m\n'
fi
exit "$FAIL"
