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
    (r"d:\phpstudy_pro\WWW\douyin\php\app\api\controller\Live.php",
     "/www/wwwroot/douyin/php/app/api/controller/Live.php"),
    (r"d:\phpstudy_pro\WWW\douyin\php\sql\upgrade_media_asset_detail.sql",
     "/www/wwwroot/douyin/php/sql/upgrade_media_asset_detail.sql"),
    (r"d:\phpstudy_pro\WWW\douyin\ai-girl-malaysia.com\upload_media.html",
     "/www/wwwroot/douyin/ai-girl-malaysia.com/upload_media.html"),
    (r"d:\phpstudy_pro\WWW\douyin\ai-girl-malaysia.com\js\upload_media.js",
     "/www/wwwroot/douyin/ai-girl-malaysia.com/js/upload_media.js"),
]

c = paramiko.SSHClient()
c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
c.connect(host, username=user, password=pwd, timeout=25)

sftp = c.open_sftp()
stamp = datetime.datetime.now().strftime("%Y%m%d_%H%M%S")

for local, remote in files:
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
        print("backup skipped for", remote, "->", e)

    with sftp.open(remote, "wb") as f:
        f.write(data)
    print("uploaded:", remote, "size:", len(data))

sftp.close()


def run(cmd):
    stdin, stdout, stderr = c.exec_command(cmd, timeout=120)
    return (stdout.read().decode("utf-8", "replace") + stderr.read().decode("utf-8", "replace")).strip()


env = run("cat /www/wwwroot/douyin/php/.env")
db_conf = {"HOSTNAME": "127.0.0.1", "HOSTPORT": "3306", "DATABASE": "zhibo",
           "USERNAME": "root", "PASSWORD": ""}
for key in db_conf:
    for line in env.splitlines():
        line = line.strip()
        if line.startswith(key) and "=" in line:
            db_conf[key] = line.split("=", 1)[1].strip()
            break

mysql_base = 'mysql --host={h} --port={p} --user={u} --password="{pw}" {db}'.format(
    h=db_conf["HOSTNAME"], p=db_conf["HOSTPORT"], u=db_conf["USERNAME"],
    pw=db_conf["PASSWORD"], db=db_conf["DATABASE"])

print("----- run migration -----")
print(run(mysql_base + ' -e "source /www/wwwroot/douyin/php/sql/upgrade_media_asset_detail.sql" 2>&1'))

print("----- verify columns -----")
print(run(mysql_base + ' -e "SHOW COLUMNS FROM lp_media_asset" 2>&1 | grep -E '
          '"description|cover_url|tags|style|mood|resolution|is_adult"'))

print("----- php syntax -----")
print(run("php -l /www/wwwroot/douyin/php/app/api/controller/Live.php 2>&1"))

c.close()
print("DONE")
