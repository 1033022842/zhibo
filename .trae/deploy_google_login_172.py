# -*- coding: utf-8 -*-
"""把「Google 登录」发布到 172.81.98.55：

1) 上传后端 3 个文件（UserService / api\Live / route\live）+ 前端 js/main.js + assets/google-auth.png
2) 远端把 39 个页面里 js/main.js 的版本号 20260921b → 20260928a（只做字符串替换，避免覆盖远端其它差异）
3) 远端 php/.env 补 [GOOGLE] CLIENT_ID 配置位（已存在则不动）
4) 校验：接口探活 + 出网到 Google 的连通性 + 页内引用检查

用法：python .trae/deploy_google_login_172.py [deploy|verify]   默认 deploy
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
REMOTE = "/www/wwwroot/douyin"
SITE = REMOTE + "/ai-girl-malaysia.com"

FILES = [
    (LOCAL + r"\php\app\live\service\UserService.php", REMOTE + "/php/app/live/service/UserService.php"),
    (LOCAL + r"\php\app\api\controller\Live.php",       REMOTE + "/php/app/api/controller/Live.php"),
    (LOCAL + r"\php\route\live.php",                    REMOTE + "/php/route/live.php"),
    (LOCAL + r"\ai-girl-malaysia.com\js\main.js",       SITE + "/js/main.js"),
    (LOCAL + r"\ai-girl-malaysia.com\assets\google-auth.png", SITE + "/assets/google-auth.png"),
]

ENV_FILE = REMOTE + "/php/.env"
ENV_BLOCK = "\n[GOOGLE]\nCLIENT_ID = \n"
OLD_VER = "js/main.js?v=20260921b"
NEW_VER = "js/main.js?v=20260928a"

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(HOST, username=USER, password=PWD, timeout=25)


def run(cmd):
    _, out, err = client.exec_command(cmd, timeout=90)
    return out.read().decode("utf-8", "replace") + err.read().decode("utf-8", "replace")


def verify():
    print("== 接口探活 ==")
    print(run(
        "echo -n 'googleClientId: '; curl -s -H 'Host: ai-girl-malaysia.com' "
        "http://127.0.0.1:8082/api/live/googleClientId; echo; "
        "echo -n 'googleLogin(空参): '; curl -s -X POST -H 'Host: ai-girl-malaysia.com' "
        "http://127.0.0.1:8082/api/live/googleLogin; echo; "
        "echo -n 'googleLogin(假token): '; curl -s -X POST -d 'access_token=bogus' "
        "-H 'Host: ai-girl-malaysia.com' http://127.0.0.1:8082/api/live/googleLogin; echo"
    ))

    print("== 出网到 Google（期望拿到 HTTP 400，说明能连通） ==")
    print(run(
        "curl -s -o /dev/null -w 'tokeninfo HTTP=%{http_code} time=%{time_total}s\\n' "
        "--max-time 15 'https://oauth2.googleapis.com/tokeninfo?access_token=bogus'; "
        "curl -s -o /dev/null -w 'gsi-js HTTP=%{http_code}\\n' --max-time 15 "
        "'https://accounts.google.com/gsi/client'"
    ))

    print("== 静态引用检查 ==")
    print(run(
        "grep -c 'googleLogin\\|googleClientId' " + REMOTE + "/php/route/live.php; "
        "grep -c 'loginWithGoogle' " + REMOTE + "/php/app/live/service/UserService.php; "
        "grep -c 'googleLogin' " + SITE + "/js/main.js; "
        "echo -n 'main.js 新版本引用页数: '; grep -l 'js/main.js?v=20260928a' " + SITE + "/*.html | wc -l; "
        "echo -n 'main.js 旧版本残留页数: '; grep -l 'js/main.js?v=20260921b' " + SITE + "/*.html | wc -l; "
        "echo -n '.env GOOGLE 配置: '; grep -A1 '\\[GOOGLE\\]' " + ENV_FILE + " | tr '\\n' ' '; echo; "
        "ls -l --time-style=+%F_%T " + SITE + "/js/main.js " + SITE + "/assets/google-auth.png"
    ))


if "verify" in sys.argv:
    verify()
    client.close()
    sys.exit(0)

if "get" in sys.argv:
    # 下载远端备份，用于和本地对比（确认上传前远端 main.js 没有独有改动）
    sftp = client.open_sftp()
    src = SITE + "/js/main.js.bak_" + sys.argv[sys.argv.index("get") + 1]
    dst = LOCAL + r"\.trae\_remote_mainjs_bak.js"
    sftp.get(src, dst)
    print("downloaded:", src, "->", dst)
    sftp.close()
    client.close()
    sys.exit(0)

sftp = client.open_sftp()
stamp = datetime.datetime.now().strftime("%Y%m%d_%H%M%S")

for local, remote in FILES:
    with open(local, "rb") as f:
        data = f.read()
    bak = remote + ".bak_" + stamp
    try:
        with sftp.open(remote, "rb") as f:
            old = f.read()
        with sftp.open(bak, "wb") as f:
            f.write(old)
        print("backup:", bak, "size:", len(old))
    except Exception as e:
        print("backup skipped:", e)
    with sftp.open(remote, "wb") as f:
        f.write(data)
    print("uploaded:", remote, "size:", len(data))

sftp.close()

print("== 远端 HTML 版本号替换 ==")
print(run(
    "cd " + SITE + " && before=$(grep -l '" + OLD_VER + "' *.html 2>/dev/null | wc -l) && "
    "sed -i 's#" + OLD_VER + "#" + NEW_VER + "#g' *.html && "
    "after=$(grep -l '" + NEW_VER + "' *.html 2>/dev/null | wc -l) && "
    "echo \"替换前 $before 个 -> 替换后 $after 个\""
))

print("== 远端 .env 补配置位 ==")
print(run(
    "if grep -q '\\[GOOGLE\\]' " + ENV_FILE + "; then echo '.env 已有 [GOOGLE]，跳过'; "
    "else cp " + ENV_FILE + " " + ENV_FILE + ".bak_" + stamp + " && "
    "printf '\\n[GOOGLE]\\nCLIENT_ID = \\n' >> " + ENV_FILE + " && echo '.env 已追加 [GOOGLE] 段'; fi; "
    "grep -A1 '\\[GOOGLE\\]' " + ENV_FILE
))

verify()
client.close()
print("DONE")
