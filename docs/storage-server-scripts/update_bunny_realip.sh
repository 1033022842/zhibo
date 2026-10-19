#!/bin/bash
set -e
curl -sS --max-time 30 https://api.bunny.net/system/edgeserverlist -o /etc/nginx/bunny_edges.json
python3 /root/deploy/bunny_realip.py
grep -q 'set_real_ip_from' /etc/nginx/bunny_realip.conf
nginx -t && systemctl reload nginx
