#!/bin/bash
# 各站定制生成任务积压 —— 走各站 /api/customVideo/stats（X-Api-Key 鉴权）
# 不用直连 MySQL：四个站的 3306 都不对公网开放。

KEY="${WORKER_KEY:-live-ai-api-key-2026}"
declare -A API=(
  [sugus]="https://sugus.ai/api"
  [heygirl]="https://heygirl.live/api"
  [kiss]="https://kisss.ai/api"
  [kelymi]="https://kelymi.autos/api"
)
ORDER="sugus heygirl kiss kelymi"

printf '%-8s %8s %8s %8s\n' SITE PENDING RUNNING STUCK
bad=0
for s in $ORDER; do
  body=$(curl -s --max-time 15 -H "X-Api-Key: $KEY" -H "X-Worker-Id: healthcheck" \
              "${API[$s]}/customVideo/stats" 2>/dev/null)
  if [ -z "$body" ]; then
    printf '%-8s  (无响应)\n' "$s"; bad=1; continue
  fi
  row=$(printf '%s' "$body" | python3 -c '
import sys,json
try:
    d=json.load(sys.stdin)
    x=d.get("data") or {}
    print(x.get("pending",0), x.get("running",0), x.get("stuck",0))
except Exception:
    print("ERR ERR ERR")
' 2>/dev/null)
  if [ -z "$row" ] || [ "${row%% *}" = "ERR" ]; then
    printf '%-8s  (接口未上线或返回异常)\n' "$s"; bad=1; continue
  fi
  read -r p r k <<< "$row"
  printf '%-8s %8s %8s %8s\n' "$s" "$p" "$r" "$k"
  if [ "${k:-0}" -gt 0 ] 2>/dev/null; then
    echo "         ⚠ $s 有 $k 个任务接单后超 90 分钟未完成，看 /opt/cv/$s/worker.log"
  fi
done
exit "$bad"
