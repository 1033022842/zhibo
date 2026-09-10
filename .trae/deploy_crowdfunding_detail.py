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
    # 后端
    (r"d:\phpstudy_pro\WWW\douyin\php\app\api\controller\Crowdfunding.php",
     "/www/wwwroot/douyin/php/app/api/controller/Crowdfunding.php"),
    (r"d:\phpstudy_pro\WWW\douyin\php\app\live\service\CrowdfundingService.php",
     "/www/wwwroot/douyin/php/app/live/service/CrowdfundingService.php"),
    (r"d:\phpstudy_pro\WWW\douyin\php\app\admin\controller\live\Crowdfunding.php",
     "/www/wwwroot/douyin/php/app/admin/controller/live/Crowdfunding.php"),
    # SQL
    (r"d:\phpstudy_pro\WWW\douyin\php\sql\upgrade_crowdfunding.sql",
     "/www/wwwroot/douyin/php/sql/upgrade_crowdfunding.sql"),
    (r"d:\phpstudy_pro\WWW\douyin\php\sql\upgrade_crowdfunding_detail.sql",
     "/www/wwwroot/douyin/php/sql/upgrade_crowdfunding_detail.sql"),
    # 前端
    (r"d:\phpstudy_pro\WWW\douyin\ai-girl-malaysia.com\crowdfunding.html",
     "/www/wwwroot/douyin/ai-girl-malaysia.com/crowdfunding.html"),
    (r"d:\phpstudy_pro\WWW\douyin\ai-girl-malaysia.com\my-crowdfunding.html",
     "/www/wwwroot/douyin/ai-girl-malaysia.com/my-crowdfunding.html"),
    (r"d:\phpstudy_pro\WWW\douyin\ai-girl-malaysia.com\index.html",
     "/www/wwwroot/douyin/ai-girl-malaysia.com/index.html"),
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
    out = stdout.read().decode("utf-8", "replace")
    err = stderr.read().decode("utf-8", "replace")
    return (out + err).strip()


# 读取服务器 .env 中的数据库配置
env = run("cat /www/wwwroot/douyin/php/.env")
print("----- remote .env -----")
print(env)

db_conf = {"HOSTNAME": "127.0.0.1", "HOSTPORT": "3306", "DATABASE": "live_platform",
           "USERNAME": "root", "PASSWORD": ""}
for key in db_conf:
    for line in env.splitlines():
        line = line.strip()
        if line.startswith(key) and "=" in line:
            db_conf[key] = line.split("=", 1)[1].strip()
            break

print("----- db conf -----")
print(db_conf)

sql_cmd = (
    'mysql --host={h} --port={p} --user={u} --password="{pw}" {db} '
    '-e "source /www/wwwroot/douyin/php/sql/upgrade_crowdfunding_detail.sql"'
).format(h=db_conf["HOSTNAME"], p=db_conf["HOSTPORT"], u=db_conf["USERNAME"],
         pw=db_conf["PASSWORD"], db=db_conf["DATABASE"])
print("----- run migration -----")
print(run(sql_cmd + " 2>&1"))

print("----- verify columns -----")
verify_cmd = (
    'mysql --host={h} --port={p} --user={u} --password="{pw}" {db} '
    '-e "SHOW COLUMNS FROM lp_crowdfunding_project" 2>&1 | grep -E '
    '"tags|style|gender|age_range|language|personality|voice_style|deliverables|is_adult|highlights|reference_url"'
).format(h=db_conf["HOSTNAME"], p=db_conf["HOSTPORT"], u=db_conf["USERNAME"],
         pw=db_conf["PASSWORD"], db=db_conf["DATABASE"])
print(run(verify_cmd))

c.close()
print("DONE")
