#!/bin/bash
# 等 QA 跑完 50 条，然后同步产物 + 生成报告
cd /d/ever/douyin/douyin
for i in $(seq 1 200); do
  n=$(python .zcode/gpu.py "wc -l < /opt/cv/short/qa/results.jsonl 2>/dev/null || echo 0" 200 2>/dev/null | tr -d '[:space:]')
  echo "[$(date '+%H:%M:%S')] 已完成 ${n}/50"
  if [ "$n" -ge 50 ] 2>/dev/null; then
    echo "全部完成，开始同步"
    break
  fi
  # 进程还在吗
  alive=$(python .zcode/gpu.py "pgrep -c -f qa_50.py 2>/dev/null || echo 0" 200 2>/dev/null | tr -d '[:space:]')
  if [ "$alive" = "0" ] && [ "$n" -lt 50 ]; then
    echo "QA 进程已退出但只有 ${n} 条，停止等待"
    break
  fi
  sleep 240
done
python .zcode/gpumig/qa_fetch.py
echo "FETCH_DONE"
