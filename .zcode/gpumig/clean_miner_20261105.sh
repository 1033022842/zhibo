#!/bin/bash
# ============================================================
# 清除 sys-reviver 挖矿木马（2026-11-05）
#
# 恶意组件：
#   C2:      http://84.21.173.223/2134789/sdf34/1.sh   (worker: prl1365)
#   主目录:  ~/.cache/sys-gpu/                (systemd-gpu-mgr 43MB + kworker-watchdog)
#   备份1:   ~/.local/share/.sys-gpu-bk1/
#   备份2:   ~/.config/.sys-gpu-bk2/
#   复活器:  ~/.local/share/.sys-reviver-bk/sys-reviver  (cron 每分钟)
#
# 清除顺序（关键）：
#   1. 先删 crontab —— 否则删完 60 秒内被拉活
#   2. 杀 reviver + watchdog + 主进程
#   3. chattr -i 解除不可变，再删三处目录
#   4. 删除 SSH/input/output 里的渗透探测文件
#   5. 复核：进程、cron、文件、外连全部为空
# ============================================================
set -u
HOME_DIR="/home/administrator"
OUT="$HOME_DIR/reviver_forensics_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$OUT"

echo "===== 0. 取证快照（保留证据，便于事后分析）====="
crontab -l > "$OUT/crontab.before" 2>/dev/null
ps aux | grep -E 'systemd-gpu-mgr|kworker-watchdog|sys-reviver' | grep -v grep > "$OUT/processes.before"
cp -a "$HOME_DIR/.cache/sys-gpu/sys-gpu.conf" "$OUT/" 2>/dev/null
cp -a "$HOME_DIR/.cache/sys-gpu/.env" "$OUT/env.before" 2>/dev/null
cp -a "$HOME_DIR/.local/share/.sys-reviver-bk/sys-reviver" "$OUT/" 2>/dev/null
ss -tnp > "$OUT/sockets.before" 2>/dev/null
echo "取证已存到 $OUT"
echo

echo "===== 1. 清空 crontab（断复活源，必须最先）====="
crontab -r 2>/dev/null
echo "crontab 现有内容: $(crontab -l 2>/dev/null | wc -l) 行（应为 0）"
echo

echo "===== 2. 杀进程 ====="
for pat in sys-reviver kworker-watchdog systemd-gpu-mgr; do
    pids=$(pgrep -f "$pat" 2>/dev/null)
    if [ -n "$pids" ]; then
        # 先 TERM
        kill -TERM $pids 2>/dev/null
        sleep 2
        # 残留的 KILL
        pids2=$(pgrep -f "$pat" 2>/dev/null)
        if [ -n "$pids2" ]; then
            kill -KILL $pids2 2>/dev/null
        fi
        echo "已终止 $pat: $pids"
    else
        echo "$pat 未运行"
    fi
done
sleep 2
echo "剩余相关进程: $(ps aux | grep -E 'systemd-gpu-mgr|kworker-watchdog|sys-reviver' | grep -v grep | wc -l) 个（应为 0）"
echo

echo "===== 3. 删落地目录（先解 chattr）====="
for d in \
    "$HOME_DIR/.cache/sys-gpu" \
    "$HOME_DIR/.local/share/.sys-gpu-bk1" \
    "$HOME_DIR/.config/.sys-gpu-bk2" \
    "$HOME_DIR/.local/share/.sys-reviver-bk" ; do
    if [ -e "$d" ]; then
        chattr -R -i "$d" 2>/dev/null
        rm -rf "$d" && echo "已删除 $d" || echo "!! 删除失败 $d"
    fi
done
echo

echo "===== 4. 清理渗透探测文件 ====="
BASE=/opt/comfyui/ComfyUI

# 4a. output/ 里的路径穿越目录、命令注入目录名
find "$BASE/output" -maxdepth 1 -type d \
    \( -name '*%2*' -o -name '*\\*' -o -name '*;*' -o -name '....' \) \
    -exec rm -rf {} + 2>/dev/null
echo "output 穿越/注入目录已清"

# 4b. output/ 里的 i* 探测文件与目录
find "$BASE/output" -maxdepth 1 \
    \( -name 'i[0-9]*' -o -name 'm[0-9a-f][0-9a-f]*' -o -name '*probe*' -o -name '*_rce*' -o -name 'zz[0-9a-f]*_*' \) \
    -exec rm -rf {} + 2>/dev/null
echo "output i*/probe 已清"

# 4c. input/ 里的恶意 .so / pwned 脚本 / 探测
find "$BASE/input" -maxdepth 1 -type f \
    \( -name '*.so' -o -name 'pwned*' -o -name 'i[0-9]*' -o -name 'zz[0-9a-f]*' \) \
    -exec rm -f {} + 2>/dev/null
find "$BASE/input" -maxdepth 1 -type d \
    \( -name '*%2*' -o -name 'i[0-9]*' \) -exec rm -rf {} + 2>/dev/null
echo "input 恶意 so/py/探测 已清"

# 4d. ~/.ssh 里的探测文件（保留私钥与 known_hosts）
rm -f "$HOME_DIR/.ssh/"*_00000.png "$HOME_DIR/.ssh/"*_00000.txt 2>/dev/null
# 确认没有攻击者写入的 authorized_keys
if [ -f "$HOME_DIR/.ssh/authorized_keys" ]; then
    echo "!! 警告：authorized_keys 存在，内容需人工核对"
    cat "$HOME_DIR/.ssh/authorized_keys"
else
    echo "authorized_keys 不存在（未被攻破）"
fi

# 4e. /tmp 下的探测标记
rm -f /tmp/zz[0-9a-f]*.txt /tmp/pwned*.txt 2>/dev/null
echo

echo "===== 5. 复核 ====="
echo "--- cron（应为空）"
crontab -l 2>/dev/null || echo "（无）"
echo "--- 进程（应为空）"
ps aux | grep -E 'systemd-gpu-mgr|kworker-watchdog|sys-reviver' | grep -v grep || echo "（无）"
echo "--- 落地目录（应为空）"
for d in "$HOME_DIR/.cache/sys-gpu" "$HOME_DIR/.local/share/.sys-gpu-bk1" "$HOME_DIR/.config/.sys-gpu-bk2" "$HOME_DIR/.local/share/.sys-reviver-bk"; do
    [ -e "$d" ] && echo "!! 仍存在 $d" || echo "ok 已删 $d"
done
echo "--- 可疑外连"
ss -tnp state established 2>/dev/null | grep -vE ':8000|:22 |:9100|:9835' | head -5 || true
echo "--- output 残留"
ls "$BASE/output" 2>/dev/null | grep -cE '%2|;\s|\.\.\.\.' || echo 0
echo
echo "===== 完成。取证快照在 $OUT ====="
