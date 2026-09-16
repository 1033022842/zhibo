"""修正 172 上线状态：
1) 用 .bak 回滚被我覆盖的 36 个静态页面（恢复 main 的 wizard.js/customVideo.js 等改动）
2) 回滚 php/route/live.php（恢复 main 的 customVideo/customRoleCreate 路由）
3) 上传应用级路由 php/app/api/route/face_swap.php（多应用模式下唯一生效的位置）
4) 在服务器上对回滚后的页面重新注入 Face Swap 菜单
5) 重载 php-fpm 并验证

用法：python .trae/fix_faceswap_172.py
"""
import io
import os

import paramiko

HOST = "172.81.98.55"
USER = "root"
PWD = "0wJfy`50tJQzG/0X"
LOCAL = r"d:\phpstudy_pro\WWW\douyin"
SITE = "/www/wwwroot/douyin/ai-girl-malaysia.com"
PHP = "/www/wwwroot/douyin/php"
LOG = os.path.join(LOCAL, ".trae", "_fix_faceswap.log")
STAMP = "20260916_133259"  # 首次部署时的备份时间戳（= 我改之前的生产内容）

RESTORE_STATIC = f"""
cd {SITE}
n=0
for f in *.html.bak_faceswap_{STAMP}; do
  [ -e "$f" ] || continue
  orig="${{f%.bak_faceswap_{STAMP}}}"
  cp "$f" "$orig"
  n=$((n+1))
done
echo "已回滚页面数: $n"
echo "--- 校验 main 改动是否已恢复 ---"
for p in characters.html chooseBodyType.html Image.html; do
  echo -n "$p: wizard=$(grep -c 'js/wizard.js' $p) customVideo=$(grep -c 'js/customVideo.js' $p)\\n"
done
"""

RESTORE_ROUTE = f"""
cp -f {PHP}/route/live.php.bak_faceswap_{STAMP} {PHP}/route/live.php
echo "--- route/live.php 恢复后 ---"
grep -c 'customVideo' {PHP}/route/live.php
grep -c 'customRoleCreate' {PHP}/route/live.php
grep -c 'faceSwap' {PHP}/route/live.php
"""

INJECT = f"""
set -e
sed -i 's#^ROOT = .*#ROOT = "{SITE}"#' /tmp/inject_faceswap_menu.py
python3 /tmp/inject_faceswap_menu.py | tail -12
echo "--- 注入结果校验 ---"
grep -c 'face_swap.html' {SITE}/Chat.html {SITE}/upload_media.html {SITE}/Image.html
"""

RELOAD = f"""
chown -R www-data:www-data {PHP}/public {PHP}/runtime
rm -f {PHP}/public/debug.log
systemctl reload php8.3-fpm
echo reloaded
"""

VERIFY = """
C="curl -s -m 25 -k --resolve sugus.it.com:443:127.0.0.1"
echo "--- 接口 ---"
echo -n "templates: "; $C 'https://sugus.it.com/api/live/faceSwapTemplates' | head -c 200; echo
echo -n "tasks(未登录): "; $C 'https://sugus.it.com/api/live/faceSwapTasks' | head -c 200; echo
echo -n "pull-face-swap: "; $C 'https://sugus.it.com/api/v1/ai/tasks/pull-face-swap?count=1' | head -c 200; echo
echo "--- 静态页 ---"
curl -s -m 15 -o /dev/null -w 'face_swap(8082)=%{http_code}\\n' http://127.0.0.1:8082/face_swap.html
curl -s -m 15 http://127.0.0.1:8082/face_swap.html | grep -c 'Face Swap'
"""

buf = io.StringIO()


def log(s):
    buf.write(s + "\n")


def flush():
    with open(LOG, "w", encoding="utf-8") as f:
        f.write(buf.getvalue())


cli = paramiko.SSHClient()
cli.set_missing_host_key_policy(paramiko.AutoAddPolicy())
cli.connect(HOST, username=USER, password=PWD, timeout=25)
sftp = cli.open_sftp()


def run(title, cmd, timeout=300):
    log(f"\n===== {title} =====")
    flush()
    try:
        _, out, err = cli.exec_command(cmd, timeout=timeout)
        o = out.read().decode("utf-8", "replace").strip()
        e = err.read().decode("utf-8", "replace").strip()
        if o:
            log(o[:5000])
        if e:
            log("[stderr] " + e[:1500])
    except Exception as ex:
        log(f"[TIMEOUT/ERR] {type(ex).__name__}: {ex}")
    flush()


# 1) 回滚静态页
run("回滚静态页面", RESTORE_STATIC)

# 2) 回滚 route/live.php + 上传新的应用级路由
run("回滚 route/live.php", RESTORE_ROUTE)

for rel, remote in [
    ("php/route/live.php", PHP + "/route/live.php"),
    ("php/app/api/route/face_swap.php", PHP + "/app/api/route/face_swap.php"),
    (".trae/inject_faceswap_menu.py", "/tmp/inject_faceswap_menu.py"),
]:
    with open(os.path.join(LOCAL, rel.replace("/", os.sep)), "rb") as f:
        data = f.read()
    with sftp.open(remote, "wb") as f:
        f.write(data)
    log(f"uploaded {rel} -> {remote} ({len(data)} bytes)")
flush()

# 3) 重新注入菜单
run("重新注入 Face Swap 菜单", INJECT)

# 4) 重载 + 验证
run("重载 php-fpm", RELOAD)
run("线上验证", VERIFY)

sftp.close()
cli.close()
log("FIX DONE")
flush()
print("FIX DONE ->", LOG)
