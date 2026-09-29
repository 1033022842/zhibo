# -*- coding: utf-8 -*-
"""把全站 favicon 换成新图标并发布到 172.81.98.55

1) 上传 sugus-favicon-v2.png
2) 远端把 *.html 里的 ./sugus-favicon.png 就地替换为 ./sugus-favicon-v2.png（只做字符串替换，不覆盖远端其它差异）
3) 校验：新图可访问、页面引用已切换、旧引用清零

用法：python .trae/deploy_favicon_172.py [deploy|verify]   默认 deploy
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

NEW_PNG = (LOCAL + r"\ai-girl-malaysia.com\sugus-favicon-v2.png", SITE + "/sugus-favicon-v2.png")

OLD = "./sugus-favicon.png"
NEW = "./sugus-favicon-v2.png"

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(HOST, username=USER, password=PWD, timeout=25)


def run(cmd):
    _, out, err = client.exec_command(cmd, timeout=120)
    return out.read().decode("utf-8", "replace") + err.read().decode("utf-8", "replace")


def verify():
    print("== 图标文件 ==")
    print(run(
        "curl -s -o /dev/null -w 'favicon-v2 HTTP=%{http_code} SIZE=%{size_download} TYPE=%{content_type}\\n' "
        "-H 'Host: ai-girl-malaysia.com' http://127.0.0.1:8082/sugus-favicon-v2.png; "
        "ls -l --time-style=+%F_%T " + SITE + "/sugus-favicon*.png"
    ))
    print("== 页面引用 ==")
    print(run(
        "cd " + SITE + " && echo -n '引用新图标页数: ' && grep -l '\\./sugus-favicon-v2\\.png' *.html 2>/dev/null | wc -l && "
        "echo -n '仍引用旧图标页数: ' && grep -l '\\./sugus-favicon\\.png' *.html 2>/dev/null | wc -l && "
        "echo -n 'Login.html 里: ' && grep -o 'rel=\"icon\"[^>]*' Login.html"
    ))


if "diag" in sys.argv:
    print("== assets 目录 ==")
    print(run("ls -l --time-style=+%F_%T " + SITE + "/assets/ | grep -E 'login-bg|google-auth'"))
    print("== favicon 引用分布 ==")
    print(run(
        "cd " + SITE + " && echo '--- 仍引用 v2 的页面 ---' && grep -l '\\./sugus-favicon-v2\\.png' *.html 2>/dev/null && "
        "echo '--- 引用旧图标的页面数 ---' && grep -l '\\./sugus-favicon\\.png' *.html 2>/dev/null | wc -l && "
        "echo '--- 还带旧版 main.js 的页面 ---' && grep -l 'js/main\\.js?v=20260921b' *.html 2>/dev/null"
    ))
    print("== Login.html 关键内容 ==")
    print(run(
        "cd " + SITE + " && sed -n '1,15p' Login.html; "
        "echo '--- 图片/布局标记 ---'; "
        "grep -n 'login-bg\\|100dvh\\|height: 220px\\|aspect-ratio' Login.html"
    ))
    print("== 站点目录最近被改动的文件（按时间倒序前 15） ==")
    print(run("cd " + SITE + " && ls -lt --time-style=+%F_%T | head -15; ls -lt --time-style=+%F_%T assets/ | head -8"))
    client.close()
    sys.exit(0)

if "verify" in sys.argv:
    verify()
    client.close()
    sys.exit(0)

sftp = client.open_sftp()
stamp = datetime.datetime.now().strftime("%Y%m%d_%H%M%S")

local, remote = NEW_PNG
with open(local, "rb") as f:
    data = f.read()
try:
    with sftp.open(remote + ".bak_" + stamp, "wb") as f:
        with sftp.open(remote, "rb") as src:
            f.write(src.read())
    print("backup:", remote + ".bak_" + stamp)
except Exception as e:
    print("backup skipped:", e)
with sftp.open(remote, "wb") as f:
    f.write(data)
print("uploaded:", remote, "size:", len(data))
sftp.close()

print("== 远端 HTML 引用替换 ==")
print(run(
    "cd " + SITE + " && before=$(grep -l '" + OLD + "' *.html 2>/dev/null | wc -l) && "
    "sed -i 's#" + OLD + "#" + NEW + "#g' *.html && "
    "after=$(grep -l '" + NEW + "' *.html 2>/dev/null | wc -l) && "
    "echo \"favicon 替换前 $before 个 -> 现在 $after 个\" && "
    "mv_before=$(grep -l 'js/main\\.js?v=20260921b' *.html 2>/dev/null | wc -l) && "
    "sed -i 's#js/main\\.js?v=20260921b#js/main\\.js?v=20260928a#g' *.html && "
    "mv_after=$(grep -l 'js/main\\.js?v=20260921b' *.html 2>/dev/null | wc -l) && "
    "echo \"main.js 旧版本残留 $mv_before 个 -> 现在 $mv_after 个\""
))

verify()
client.close()
print("DONE")
