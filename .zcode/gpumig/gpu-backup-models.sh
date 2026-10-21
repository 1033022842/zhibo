#!/bin/bash
# 模型备份：/data/models -> /data/backup/models-<date>（幂等，可重复跑）
# 模型不可再生（heretic / 私有 LoRA 网上都下不到），这是唯一保险。
SRC=/data/models
DST=/data/backup/models-$(date +%Y%m%d)
mkdir -p "$DST"
rsync -a --delete "$SRC/" "$DST/"
echo "backup done: $DST"
du -sh "$DST"
