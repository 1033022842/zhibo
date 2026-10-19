#!/bin/bash
# 锁定媒体源站：只允许 Bunny 边缘 + 项目机 + 本机访问 /sec/ /pub/
# 前提：CDN 已上线并验证通过（否则访客全部 403）
set -euo pipefail

SNIP=/etc/nginx/snippets/media_origin_allow.conf
BAK="${SNIP}.bak.$(date +%s)"

python3 /root/deploy/gen_media_allow.py          # 生成 ${SNIP}.new
[ -s "${SNIP}.new" ] || { echo "生成失败：${SNIP}.new 为空"; exit 1; }

cp "$SNIP" "$BAK"
mv "${SNIP}.new" "$SNIP"

if ! nginx -t; then
  echo "配置校验失败，已回滚到 ${BAK}"
  mv "$BAK" "$SNIP"
  exit 1
fi

systemctl reload nginx
echo "已锁定源站：allow $(grep -c '^allow' "$SNIP") 条；备份 ${BAK}"
