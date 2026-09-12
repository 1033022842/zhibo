import sys
import paramiko

try:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
except Exception:
    pass

HOST = "38.181.44.164"
USER = "root"
PWD = "Mr3$Ye7]Dx7|"

CMDS = [
    "cd /www/wwwroot/douyin/admin/dist && grep -o 'assets/index-[A-Za-z0-9_-]*\\.js' index.html | head -1",
    "cd /www/wwwroot/douyin/admin/dist && ls assets/index-*.js | head -5",
    "curl -s -o /dev/null -w 'admin_index=%{http_code}\\n' -H 'Host: 38.181.44.164' http://127.0.0.1/admin/",
    "curl -s -o /dev/null -w 'new_chunk=%{http_code}\\n' -H 'Host: 38.181.44.164' http://127.0.0.1/admin/assets/liveUser-BzGq-49X.js",
    "curl -s -o /dev/null -w 'api_probe=%{http_code}\\n' -H 'Host: 38.181.44.164' http://127.0.0.1/admin/user.LiveUser/index",
    "curl -s -X POST -H 'Host: 38.181.44.164' http://127.0.0.1/admin/user.LiveUser/adjustDiamond | head -c 300",
]

cli = paramiko.SSHClient()
cli.set_missing_host_key_policy(paramiko.AutoAddPolicy())
cli.connect(HOST, username=USER, password=PWD, timeout=25)
for c in CMDS:
    _, o, e = cli.exec_command(c, timeout=60)
    print("$", c)
    print(o.read().decode("utf-8", "replace").rstrip())
    er = e.read().decode("utf-8", "replace").rstrip()
    if er:
        print("[stderr]", er)
cli.close()
print("DONE")
