"""把 ai-girl-malaysia.com/Login.html 及其登录背景图发布到 172.81.98.55"""
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

FILES = [
    (r"d:\phpstudy_pro\WWW\douyin\ai-girl-malaysia.com\Login.html",
     "/www/wwwroot/douyin/ai-girl-malaysia.com/Login.html"),
    (r"d:\phpstudy_pro\WWW\douyin\ai-girl-malaysia.com\assets\login-bg-full.jpg",
     "/www/wwwroot/douyin/ai-girl-malaysia.com/assets/login-bg-full.jpg"),
]

c = paramiko.SSHClient()
c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
c.connect(HOST, username=USER, password=PWD, timeout=25)

def remote(cmd):
    _, out, err = c.exec_command(cmd)
    return out.read().decode("utf-8", "replace") + err.read().decode("utf-8", "replace")


if "verify" in sys.argv:
    print(remote(
        "echo '--- 站点缓存策略 ---'; nginx -T 2>/dev/null | sed -n '1024,1080p' | grep -n -A6 'location'; "
        "echo '--- served ---'; "
        "curl -s -o /dev/null -w 'LOGIN HTTP=%{http_code} SIZE=%{size_download}\\n' "
        "-H 'Host: ai-girl-malaysia.com' http://127.0.0.1:8082/Login.html; "
        "echo -n 'bg_refs='; curl -s -H 'Host: ai-girl-malaysia.com' "
        "http://127.0.0.1:8082/Login.html | grep -c 'assets/login-bg'; "
        "curl -s -H 'Host: ai-girl-malaysia.com' http://127.0.0.1:8082/Login.html "
        "| sed -n '159,180p'; "
        "curl -s -o /dev/null -w 'BG HTTP=%{http_code} SIZE=%{size_download} TYPE=%{content_type}\\n' "
        "-H 'Host: ai-girl-malaysia.com' http://127.0.0.1:8082/assets/login-bg-full.jpg; "
        "ls -l --time-style=+%F_%T /www/wwwroot/douyin/ai-girl-malaysia.com/assets/ | grep login-bg"
    ))
    c.close()
    sys.exit(0)

sftp = c.open_sftp()
stamp = datetime.datetime.now().strftime("%Y%m%d_%H%M%S")

for local, remote_path in FILES:
    with open(local, "rb") as f:
        data = f.read()
    bak = remote_path + ".bak_" + stamp
    try:
        with sftp.open(remote_path, "rb") as f:
            old = f.read()
        with sftp.open(bak, "wb") as f:
            f.write(old)
        print("backup:", bak, "size:", len(old))
    except Exception as e:
        print("backup skipped:", e)
    with sftp.open(remote_path, "wb") as f:
        f.write(data)
    print("uploaded:", remote_path, "size:", len(data))
    with sftp.open(remote_path, "rb") as f:
        chk = f.read()
    print("verify size:", len(chk), "match:", chk == data)

sftp.close()
c.close()
print("DONE")
