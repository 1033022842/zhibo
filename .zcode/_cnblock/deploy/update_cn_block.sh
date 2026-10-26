#!/bin/bash
# 月度刷新大陆封锁数据源：Bunny 边缘 realip 名单 + CN v4/v6 geo 名单（APNIC）
# cron: 0 4 1 * * /root/deploy/update_cn_block.sh >> /var/log/cn_block.log 2>&1
set -e
curl -sS --max-time 60 https://api.bunny.net/system/edgeserverlist -o /etc/nginx/bunny_edges.json
python3 /root/deploy/bunny_realip.py
grep -q 'set_real_ip_from' /etc/nginx/bunny_realip.conf
curl -sS --max-time 300 https://ftp.apnic.net/stats/apnic/delegated-apnic-latest -o /tmp/apnic.delegated
python3 /root/deploy/cn_geo.py
grep -q ' 1;' /etc/nginx/cn_geo.conf
nginx -t && systemctl reload nginx
echo "$(date '+%F %T') cn-block data refreshed"
