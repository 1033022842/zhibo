# -*- coding: utf-8 -*-
"""登录页 × 返回来源页 + main.js 版本号统一，发布到 172.81.98.55

1) 上传 Login.html（× 改为 closeLoginPage）、js/main.js（新增 backFromLogin / window.liveBackFromLogin）
2) 远端把各页面 js/main.js?v=xxx 统一改成 v=20260930g（换缓存键，确保拿到新逻辑）
3) 校验

用法：python .trae/deploy_login_close_172.py [deploy|verify]   默认 deploy
"""
import datetime
import sys

import paramiko

try:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
except Exception:
    pass

HOST = "172.81.98.55"
USER = "root"
PWD = "0wJfy`50tJQzG/0X"

LOCAL = r"d:\phpstudy_pro\WWW\douyin"
SITE = "/www/wwwroot/douyin/ai-girl-malaysia.com"
NEW_VER = "js/main.js?v=20260930g"

FILES = [
    (LOCAL + r"\ai-girl-malaysia.com\Login.html", SITE + "/Login.html"),
    (LOCAL + r"\ai-girl-malaysia.com\js\main.js", SITE + "/js/main.js"),
]

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(HOST, username=USER, password=PWD, timeout=25)


def run(cmd):
    _, out, err = client.exec_command(cmd, timeout=120)
    return out.read().decode("utf-8", "replace") + err.read().decode("utf-8", "replace")


def verify():
    print("== 页面/脚本内容 ==")
    print(run(
        "cd " + SITE + " && "
        "echo -n 'Login.html 关闭按钮: '; grep -o 'onclick=\"closeLoginPage()\"' Login.html; "
        "echo -n 'Login.html 里有 closeLoginPage 定义: '; grep -c 'function closeLoginPage' Login.html; "
        "echo -n 'main.js 里有 liveBackFromLogin: '; grep -c 'window.liveBackFromLogin' js/main.js; "
        "echo -n 'main.js 里有 backFromLogin: '; grep -c 'function backFromLogin' js/main.js; "
        "echo -n '仍用 window.close() 的页面: '; grep -l 'window.close()' *.html 2>/dev/null | wc -l; "
        "echo -n 'main.js 新版本号引用页数: '; grep -l 'js/main\\.js?v=20260930g' *.html 2>/dev/null | wc -l; "
        "echo -n '其它版本号残留页数: '; grep -lE 'js/main\\.js\\?v=' *.html 2>/dev/null | "
        "while IFS= read -r f; do grep -q 'js/main\\.js?v=20260930g' \"$f\" || echo \"$f\"; done | wc -l"
    ))
    print("== 线上可访问性 ==")
    print(run(
        "curl -s -o /dev/null -w 'Login.html HTTP=%{http_code} SIZE=%{size_download}\\n' "
        "-H 'Host: ai-girl-malaysia.com' http://127.0.0.1:8082/Login.html; "
        "curl -s -H 'Host: ai-girl-malaysia.com' http://127.0.0.1:8082/Login.html | grep -c 'closeLoginPage'; "
        "curl -s -o /dev/null -w 'main.js HTTP=%{http_code} SIZE=%{size_download}\\n' "
        "-H 'Host: ai-girl-malaysia.com' http://127.0.0.1:8082/js/main.js; "
        "curl -s -H 'Host: ai-girl-malaysia.com' 'http://127.0.0.1:8082/js/main.js?v=20260930g' | grep -c 'backFromLogin'"
    ))


if "verify" in sys.argv:
    verify()
    client.close()
    sys.exit(0)

sftp = client.open_sftp()
stamp = datetime.datetime.now().strftime("%Y%m%d_%H%M%S")
for local, remote in FILES:
    with open(local, "rb") as f:
        data = f.read()
    try:
        with sftp.open(remote, "rb") as src, sftp.open(remote + ".bak_" + stamp, "wb") as dst:
            dst.write(src.read())
        print("backup:", remote + ".bak_" + stamp)
    except Exception as e:
        print("backup skipped:", remote, e)
    with sftp.open(remote, "wb") as f:
        f.write(data)
    print("uploaded:", remote, "size:", len(data))
sftp.close()

print("== 远端：main.js 版本号统一 ==")
print(run(
    "cd " + SITE + " && before=$(grep -lE 'js/main\\.js\\?v=' *.html 2>/dev/null | wc -l) && "
    "sed -i -E 's#js/main\\.js\\?v=[0-9a-zA-Z]+#" + NEW_VER + "#g' *.html && "
    "after=$(grep -l '" + NEW_VER + "' *.html 2>/dev/null | wc -l) && "
    "echo \"处理 $before 个页面 -> 现在 $after 个页面指向新版本号\""
))

verify()
client.close()
print("DONE")
