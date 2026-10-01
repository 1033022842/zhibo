# -*- coding: utf-8 -*-
"""头像下拉改版：安装入口移到左侧菜单栏底部 + 消息通知面板，发布到 172.81.98.55

1) 上传 js/shell-new.js
   - NAV_BOTTOM 末尾新增 Get Candy.ai app（左侧菜单栏最底部）
   - profileMenu 去掉 Get Candy.ai app，改为 Notifications（点开通知面板）
2) 远端把各页面 js/shell-new.js?v=xxx 统一改成 v=20260930i（换缓存键）
3) 校验

用法：python .trae/deploy_shell_notif_172.py [deploy|verify]   默认 deploy
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
NEW_VER = "js/shell-new.js?v=20260930i"

FILES = [
    (LOCAL + r"\ai-girl-malaysia.com\js\shell-new.js", SITE + "/js/shell-new.js"),
]

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(HOST, username=USER, password=PWD, timeout=25)


def run(cmd):
    _, out, err = client.exec_command(cmd, timeout=180)
    return out.read().decode("utf-8", "replace") + err.read().decode("utf-8", "replace")


def verify():
    print("== 脚本内容 ==")
    print(run(
        "cd " + SITE + " && "
        "echo -n '左侧菜单栏底部含 Get Candy.ai app: '; grep -c \"Become Premium', icon: 'premium' }\" js/shell-new.js; "
        "echo -n 'NAV_BOTTOM 里的安装入口: '; grep -c \"label: 'Get Candy.ai app'\" js/shell-new.js; "
        "echo -n 'profileMenu 里已移除安装入口(应为0): '; grep -c \"row('./Home.html#install-app'\" js/shell-new.js; "
        "echo -n 'profileMenu 里的通知入口: '; grep -c 'fy-notif-btn' js/shell-new.js; "
        "echo -n '通知面板函数: '; grep -c 'function openNotifs' js/shell-new.js; "
        "echo -n 'shell-new.js 新版本号引用页数: '; grep -l 'js/shell-new\\.js?v=20260930i' *.html 2>/dev/null | wc -l; "
        "echo -n '其它版本号残留页数: '; grep -lE 'js/shell-new\\.js\\?v=' *.html 2>/dev/null | "
        "while IFS= read -r f; do grep -q 'js/shell-new\\.js?v=20260930i' \"$f\" || echo \"$f\"; done | wc -l"
    ))
    print("== 线上可访问性 ==")
    print(run(
        "curl -s -o /dev/null -w 'shell-new.js HTTP=%{http_code} SIZE=%{size_download}\\n' "
        "-H 'Host: ai-girl-malaysia.com' 'http://127.0.0.1:8082/js/shell-new.js?v=20260930i'; "
        "curl -s -H 'Host: ai-girl-malaysia.com' 'http://127.0.0.1:8082/js/shell-new.js?v=20260930i' | grep -c 'fy-notif-btn'; "
        "curl -s -H 'Host: ai-girl-malaysia.com' http://127.0.0.1:8082/Home.html | grep -o 'js/shell-new.js?v=[0-9a-zA-Z]*'"
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

print("== 远端：shell-new.js 版本号统一 ==")
print(run(
    "cd " + SITE + " && before=$(grep -lE 'js/shell-new\\.js\\?v=' *.html 2>/dev/null | wc -l) && "
    "sed -i -E 's#js/shell-new\\.js\\?v=[0-9a-zA-Z]+#" + NEW_VER + "#g' *.html && "
    "after=$(grep -l '" + NEW_VER + "' *.html 2>/dev/null | wc -l) && "
    "echo \"处理 $before 个页面 -> 现在 $after 个页面指向新版本号\""
))

verify()
client.close()
print("DONE")
