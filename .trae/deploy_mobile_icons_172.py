# -*- coding: utf-8 -*-
"""补齐手机端图标（浏览器标签 / 收藏 / 添加到主屏幕）并发布到 172.81.98.55

1) 上传 apple-touch-icon-v3.png（180x180 白底，iOS 用）、icon-192-v3.png、site.webmanifest
2) 远端：把 4 个 candy.ai 外链页面（invite/settings/taocan/tokens）的图标/苹果图标/manifest 换成本地资源
3) 远端：给所有带本地 favicon 的页面补一条 apple-touch-icon（已存在则跳过，可重复执行）
4) 校验：三个资源可访问 + 引用统计 + 残留 candy.ai 图标引用

用法：python .trae/deploy_mobile_icons_172.py [deploy|verify]   默认 deploy
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

FILES = [
    (LOCAL + r"\ai-girl-malaysia.com\apple-touch-icon-v3.png", SITE + "/apple-touch-icon-v3.png"),
    (LOCAL + r"\ai-girl-malaysia.com\icon-192-v3.png", SITE + "/icon-192-v3.png"),
    (LOCAL + r"\ai-girl-malaysia.com\site-manifest.json", SITE + "/site-manifest.json"),
]

AT_LINK = '<link rel="apple-touch-icon" sizes="180x180" href="./apple-touch-icon-v3.png">'

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(HOST, username=USER, password=PWD, timeout=25)


def run(cmd):
    _, out, err = client.exec_command(cmd, timeout=180)
    return out.read().decode("utf-8", "replace") + err.read().decode("utf-8", "replace")


def verify():
    print("== 资源可访问性 ==")
    for path, name in [("/apple-touch-icon-v3.png", "apple-touch"), ("/icon-192-v3.png", "icon-192"),
                       ("/site-manifest.json", "manifest"), ("/sugus-favicon-v3.png", "favicon-v3")]:
        cmd = ("curl -s -o /dev/null -w '" + name + " HTTP=%{http_code} SIZE=%{size_download} TYPE=%{content_type}\\n' "
               "-H 'Host: ai-girl-malaysia.com' http://127.0.0.1:8082" + path)
        print(run(cmd))
    print("== 页面引用统计 ==")
    print(run(
        "cd " + SITE + " && "
        "echo -n '带 apple-touch-icon(本地) 的页面: '; grep -l 'apple-touch-icon-v3\\.png' *.html 2>/dev/null | wc -l; "
        "echo -n '带本地 favicon(v3) 的页面: '; grep -l '\\./sugus-favicon-v3\\.png' *.html 2>/dev/null | wc -l; "
        "echo -n '仍引用 candy.ai 图标的页面: '; grep -l 'candy\\.ai/assets/favico\\|candy\\.ai/assets/pwa\\|candy\\.ai/manifest' *.html 2>/dev/null | wc -l; "
        "echo -n 'manifest 指向本地的页面: '; grep -l 'href=\"\\./site-manifest\\.json\"' *.html 2>/dev/null | wc -l; "
        "echo '--- Login.html / invite.html ---'; "
        "grep -o 'rel=\"icon\"[^>]*' Login.html; grep -o 'apple-touch-icon[^>]*' Login.html; "
        "grep -o 'rel=\"icon\"[^>]*' invite.html; grep -o 'apple-touch-icon[^>]*' invite.html; "
        "grep -o 'rel=\"manifest\"[^>]*' invite.html"
    ))


if "verify" in sys.argv:
    verify()
    client.close()
    sys.exit(0)

SED_CANDY_CMD = (
    "sed -i -e 's#https://candy\\.ai/assets/favico-[0-9a-f]*\\.png#./sugus-favicon-v3.png#g' "
    "-e 's#type=\"image/x-icon\" href=\"\\./sugus-favicon-v3\\.png\"#type=\"image/png\" href=\"./sugus-favicon-v3.png\"#g' "
    "-e 's#https://candy\\.ai/manifest\\.json#./site.webmanifest#g' "
    "-e '/candy\\.ai\\/assets\\/pwa\\/icons\\/apple\\//d'"
)
SED_INSERT_CMD = (
    "sed -i 's#^\\([[:space:]]*\\)\\(<link rel=\"icon\" type=\"image/png\" href=\"\\./sugus-favicon-v3\\.png\">\\)#\\1\\2\\n\\1"
    + AT_LINK + "#g'"
)

if "dry" in sys.argv:
    print(run(
        "rm -rf /tmp/icon_dry && mkdir -p /tmp/icon_dry && "
        "cp " + SITE + "/invite.html " + SITE + "/settings.html " + SITE + "/tokens.html "
        + SITE + "/Login.html " + SITE + "/Girls.html /tmp/icon_dry/ && "
        "cd /tmp/icon_dry && " + SED_CANDY_CMD + " *.html && "
        "for f in *.html; do grep -q 'apple-touch-icon-v3\\.png' \"$f\" || " + SED_INSERT_CMD + " \"$f\"; done; "
        "echo '=== invite.html 头部 ==='; sed -n '19,28p' invite.html; "
        "echo '=== invite.html 图标/清单行 ==='; grep -n 'apple-touch-icon\\|rel=\"icon\"\\|rel=\"manifest\"' invite.html; "
        "echo '=== Login.html 头部 ==='; sed -n '1,8p' Login.html; "
        "echo '=== Girls.html 头部 ==='; sed -n '3,8p' Girls.html; "
        "echo '=== 残留 candy.ai 图标/清单（应为 0）==='; grep -c 'candy\\.ai/assets/favico\\|candy\\.ai/manifest' invite.html tokens.html settings.html; "
        "echo '=== 各文件 apple-touch-icon 行数（应各 1）==='; for f in *.html; do printf '%s=' \"$f\"; grep -c 'apple-touch-icon' \"$f\"; done"
    ))
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

print("== 远端：4 个 candy.ai 页面换成本地图标 ==")
print(run(
    "cd " + SITE + " && before=$(grep -l 'candy\\.ai/assets/favico' *.html 2>/dev/null | wc -l) && "
    "sed -i -e 's#https://candy\\.ai/assets/favico-[0-9a-f]*\\.png#./sugus-favicon-v3.png#g' "
    "-e 's#type=\"image/x-icon\" href=\"\\./sugus-favicon-v3\\.png\"#type=\"image/png\" href=\"./sugus-favicon-v3.png\"#g' "
    "-e 's#https://candy\\.ai/manifest\\.json#./site.webmanifest#g' "
    "-e '/candy\\.ai\\/assets\\/pwa\\/icons\\/apple\\//d' *.html && "
    "after=$(grep -l 'candy\\.ai/assets/favico' *.html 2>/dev/null | wc -l) && "
    "echo \"candy.ai 图标页面 $before 个 -> 现在 $after 个\""
))

print("== 远端：给本地 favicon 页面补 apple-touch-icon ==")
print(run(
    "cd " + SITE + " && added=0; skipped=0; "
    "for f in *.html; do "
    "if grep -q 'apple-touch-icon-v3\\.png' \"$f\"; then skipped=$((skipped+1)); continue; fi; "
    "if grep -q '<link rel=\"icon\" type=\"image/png\" href=\"\\./sugus-favicon-v3\\.png\">' \"$f\"; then "
    "sed -i 's#^\\([[:space:]]*\\)\\(<link rel=\"icon\" type=\"image/png\" href=\"\\./sugus-favicon-v3\\.png\">\\)#\\1\\2\\n\\1" + AT_LINK + "#g' \"$f\"; "
    "added=$((added+1)); fi; done; "
    "echo \"新补 $added 个页面，已有跳过 $skipped 个\""
))

print("== 远端：manifest 改成 .json 后缀（nginx 才给正确 MIME） ==")
print(run(
    "cd " + SITE + " && rm -f site.webmanifest && "
    "sed -i 's#\\./site\\.webmanifest#./site-manifest.json#g' *.html && "
    "echo -n '指向 site-manifest.json 的页面: '; grep -l 'site-manifest\\.json' *.html 2>/dev/null | wc -l"
))

print("== 远端：主屏快捷方式名字（apple-mobile-web-app-title） ==")
print(run(
    "cd " + SITE + " && "
    "sed -i 's#<meta name=\"apple-mobile-web-app-title\" content=\"Candy.ai\">#"
    "<meta name=\"apple-mobile-web-app-title\" content=\"Sugus.ai\">#g' *.html && "
    "added=0; skipped=0; "
    "for f in *.html; do "
    "if grep -q 'apple-mobile-web-app-title' \"$f\"; then skipped=$((skipped+1)); continue; fi; "
    "if grep -q 'apple-touch-icon-v3\\.png' \"$f\"; then "
    "sed -i 's#^\\([[:space:]]*\\)\\(<link rel=\"apple-touch-icon\" sizes=\"180x180\" href=\"\\./apple-touch-icon-v3\\.png\">\\)#"
    "\\1\\2\\n\\1<meta name=\"apple-mobile-web-app-title\" content=\"Sugus.ai\">#g' \"$f\"; "
    "added=$((added+1)); fi; done; "
    "echo \"新补 $added 个页面，已有跳过 $skipped 个\"; "
    "echo -n '带该 meta 的页面数: '; grep -l 'apple-mobile-web-app-title' *.html 2>/dev/null | wc -l; "
    "echo -n '内容为 Candy.ai 的残留: '; grep -l 'apple-mobile-web-app-title\" content=\"Candy.ai\"' *.html 2>/dev/null | wc -l"
))

verify()
client.close()
print("DONE")
