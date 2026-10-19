#!/bin/bash
# 解锁媒体源站（调试/CDN 异常时的应急开关）
set -e
echo "allow all;" > /etc/nginx/snippets/media_origin_allow.conf
nginx -t && systemctl reload nginx
echo "已解锁：任何 IP 均可直连 /pub/ /sec/（签名仍然有效）"
