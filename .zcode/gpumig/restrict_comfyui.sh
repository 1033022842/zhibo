#!/bin/bash
# ============================================================
# ComfyUI 8000 端口访问控制（默认拒绝 + 白名单）
#
# 背景：2026-11-05 发现 ComfyUI 以 --listen 0.0.0.0 裸奔在公网，
# 被 155.69.183.11 / 171.83.49.40 持续渗透（pickle RCE、表达式注入、
# 路径穿越），每次尝试都会 Global interrupt 掉正在跑的采样，
# 导致用户视频生成失败。虽然攻击未成功，但必须封住入口。
#
# 设计：
#   1. 只对 8000 端口设规则，不碰其它端口（22 / 9100 / 9835 保持原状）
#   2. 默认 DROP，白名单 ACCEPT —— 新加的 IP 只需追加一条 ACCEPT 并前移
#   3. 幂等：重复执行不会堆叠重复规则
#   4. 规则持久化到 netfilter-persistent，重启不丢
#
# 用法：
#   bash restrict_comfyui.sh                 # 应用默认策略（仅放行本机）
#   bash restrict_comfyui.sh 1.2.3.4 5.6.7.8 # 额外放行这些 IP
#   bash restrict_comfyui.sh --status        # 查看当前规则
#   bash restrict_comfyui.sh --flush         # 清空本脚本加的规则（应急回滚）
# ============================================================
set -u

CHAIN="COMFY_ALLOW"
PORT=8000

if [ "${1:-}" = "--status" ]; then
    echo "=== INPUT 跳转 ==="
    iptables -L INPUT -n --line-numbers | grep -E "$CHAIN|8000" || echo "（未配置）"
    echo "=== $CHAIN 规则 ==="
    iptables -L "$CHAIN" -n --line-numbers 2>/dev/null || echo "（链不存在）"
    echo "=== 当前 8000 连接 ==="
    ss -tn state established "( sport = :$PORT )" 2>/dev/null
    exit 0
fi

if [ "${1:-}" = "--flush" ]; then
    while iptables -C INPUT -p tcp --dport $PORT -j "$CHAIN" 2>/dev/null; do
        iptables -D INPUT -p tcp --dport $PORT -j "$CHAIN"
    done
    iptables -F "$CHAIN" 2>/dev/null
    iptables -X "$CHAIN" 2>/dev/null
    echo "已清空 $CHAIN 规则（8000 恢复为无限制）"
    netfilter-persistent save >/dev/null 2>&1 || true
    exit 0
fi

# ── 1. 建链（幂等）
iptables -N "$CHAIN" 2>/dev/null || iptables -F "$CHAIN"

# ── 2. 白名单：本机永远放行
iptables -A "$CHAIN" -i lo -j ACCEPT
iptables -A "$CHAIN" -s 127.0.0.1 -j ACCEPT
iptables -A "$CHAIN" -s 38.247.166.124 -j ACCEPT

# ── 3. 额外放行的 IP（命令行传入）
for ip in "$@"; do
    case "$ip" in
        --*) continue ;;
    esac
    if ! echo "$ip" | grep -qE '^[0-9]{1,3}(\.[0-9]{1,3}){3}$'; then
        echo "!! 跳过非法 IP: $ip"
        continue
    fi
    iptables -A "$CHAIN" -s "$ip" -j ACCEPT
    echo "放行 $ip"
done

# ── 4. 其余全部拒绝
iptables -A "$CHAIN" -j DROP

# ── 5. INPUT 里挂上跳转（只对 8000/tcp，幂等）
if ! iptables -C INPUT -p tcp --dport $PORT -j "$CHAIN" 2>/dev/null; then
    iptables -I INPUT 1 -p tcp --dport $PORT -j "$CHAIN"
fi

# ── 6. 持久化
if command -v netfilter-persistent >/dev/null 2>&1; then
    netfilter-persistent save >/dev/null 2>&1 && echo "规则已持久化（重启不丢）"
else
    mkdir -p /etc/iptables
    iptables-save > /etc/iptables/rules.v4 2>/dev/null && echo "规则已写入 /etc/iptables/rules.v4"
fi

echo
echo "=== 结果 ==="
iptables -L "$CHAIN" -n --line-numbers
