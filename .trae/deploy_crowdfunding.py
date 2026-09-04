import sys
import datetime
import paramiko

try:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
except Exception:
    pass

host = "38.181.44.164"
user = "root"
pwd = "Mr3$Ye7]Dx7|"

files = [
    (r"d:\phpstudy_pro\WWW\douyin\php\app\live\service\CrowdfundingService.php",
     "/www/wwwroot/douyin/php/app/live/service/CrowdfundingService.php"),
    (r"d:\phpstudy_pro\WWW\douyin\ai-girl-malaysia.com\crowdfunding.html",
     "/www/wwwroot/douyin/ai-girl-malaysia.com/crowdfunding.html"),
]

c = paramiko.SSHClient()
c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
c.connect(host, username=user, password=pwd, timeout=25)

sftp = c.open_sftp()
stamp = datetime.datetime.now().strftime("%Y%m%d_%H%M%S")

for local, remote in files:
    with open(local, "rb") as f:
        data = f.read()

    # backup remote
    bak = remote + ".bak_" + stamp
    try:
        with sftp.open(remote, "rb") as f:
            old = f.read()
        with sftp.open(bak, "wb") as f:
            f.write(old)
        print("backup:", bak, "size:", len(old))
    except Exception as e:
        print("backup skipped for", remote, "->", e)

    with sftp.open(remote, "wb") as f:
        f.write(data)
    print("uploaded:", remote, "size:", len(data))

sftp.close()
c.close()
print("DONE")
