"""
AI 女友端 Chat 页后端补齐（线上）：
1) 数据库迁移：lp_ai_chat_message 增加 role_key 列 + idx_role_key_user 索引
2) 上传 php/app/api/controller/Live.php（支持 role_key 归档 / 自定义人设）
3) reload php-fpm 并做接口回归（POST /api/live/chat + GET /api/live/chatHistory）
用法: python .trae/deploy_chat_backend.py
"""
import sys
import datetime
import paramiko

try:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
except Exception:
    pass

HOST = "38.181.44.164"
USER = "root"
PWD = "Mr3$Ye7]Dx7|"
PHP = "/www/wwwroot/douyin/php"

SQL_LOCAL = r"d:\phpstudy_pro\WWW\douyin\php\sql\upgrade_chat_role_key.sql"
FILES = [
    (r"d:\phpstudy_pro\WWW\douyin\php\app\api\controller\Live.php", PHP + "/app/api/controller/Live.php"),
    (SQL_LOCAL, PHP + "/sql/upgrade_chat_role_key.sql"),
]

RUN_SQL = r"""
cd %s
php -r '
$c = parse_ini_file(".env", true)["DATABASE"];
$m = new mysqli($c["HOSTNAME"], $c["USERNAME"], $c["PASSWORD"], $c["DATABASE"], (int)($c["HOSTPORT"] ?? 3306));
if ($m->connect_errno) { echo "CONN ERR: ".$m->connect_error; exit(1); }
$sql = file_get_contents("sql/upgrade_chat_role_key.sql");
if (!$m->multi_query($sql)) { echo "SQL ERR: ".$m->error; exit(1); }
while ($m->more_results()) { $m->next_result(); }
$r = $m->query("SHOW COLUMNS FROM lp_ai_chat_message LIKE \"role_key\"");
echo "role_key 列: ".($r->num_rows ? "OK" : "仍缺失")."\n";
$r = $m->query("SHOW INDEX FROM lp_ai_chat_message WHERE Key_name=\"idx_role_key_user\"");
echo "idx_role_key_user: ".($r->num_rows ? "OK" : "仍缺失")."\n";
'
""" % PHP

CHAT_TEST = (
    "curl -s -m 90 -X POST -H 'Host: 38.181.44.164' -H 'Content-Type: application/json' "
    "'http://127.0.0.1:8082/api/live/chat' "
    "-d '{\"message\":\"remember me please\",\"content_id\":0,\"role_key\":\"default\","
    "\"device_id\":\"verify-chat-page-002\",\"lang\":\"en\",\"persona_name\":\"Priya\","
    "\"persona_desc\":\"Test persona\"}'"
)
HIST_TEST = (
    "curl -s -m 30 -H 'Host: 38.181.44.164' "
    "'http://127.0.0.1:8082/api/live/chatHistory?content_id=0&role_key=default&device_id=verify-chat-page-002&limit=10'"
)

cli = paramiko.SSHClient()
cli.set_missing_host_key_policy(paramiko.AutoAddPolicy())
cli.connect(HOST, username=USER, password=PWD, timeout=25)
sftp = cli.open_sftp()
stamp = datetime.datetime.now().strftime("%Y%m%d_%H%M%S")

print("=" * 12, "上传文件")
for local, remote in FILES:
    with open(local, "rb") as f:
        data = f.read()
    try:
        with sftp.open(remote, "rb") as f:
            old = f.read()
        bak = remote + ".bak_" + stamp
        with sftp.open(bak, "wb") as f:
            f.write(old)
        print("backup:", bak, "size:", len(old))
    except Exception as e:
        print("backup skipped:", e)
    with sftp.open(remote, "wb") as f:
        f.write(data)
    print("uploaded:", remote, "size:", len(data))
sftp.close()

STEPS = [
    ("数据库迁移", RUN_SQL),
    ("reload php-fpm", "systemctl reload php*-fpm 2>&1; systemctl is-active php*-fpm 2>&1 | head -3"),
    ("POST /api/live/chat (role_key)", CHAT_TEST),
    ("GET /api/live/chatHistory (role_key)", HIST_TEST),
]
for title, cmd in STEPS:
    print("=" * 12, title)
    _, out, err = cli.exec_command(cmd, timeout=180)
    print(out.read().decode("utf-8", "replace").strip()[:2000])
    e = err.read().decode("utf-8", "replace").strip()
    if e:
        print("[stderr]", e[:400])

cli.close()
print("DONE")
