#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""在 sugus 上执行 aiShort 建表 SQL 并验证。

DB 凭据从 php/.env 读，不在命令行嵌密码（避免被 ps 看到）。
"""
import paramiko

JUMP = ("172.81.99.11", "root", "mw-DGckAL,61fV00")
TARGET = "172.81.98.55"
KEY = "/root/.ssh/n99_pull"
SITE = "/www/wwwroot/douyin"

j = paramiko.SSHClient()
j.set_missing_host_key_policy(paramiko.AutoAddPolicy())
j.connect(JUMP[0], 22, JUMP[1], JUMP[2], timeout=30, look_for_keys=False, allow_agent=False)
S = "ssh -i %s -o StrictHostKeyChecking=no -o BatchMode=yes root@%s" % (KEY, TARGET)


def run(inner, timeout=300):
    stdin, stdout, stderr = j.exec_command(S + " '" + inner.replace("'", "'\"'\"'") + "'", timeout=timeout)
    o = stdout.read().decode("utf-8", "replace")
    e = stderr.read().decode("utf-8", "replace")
    return o, e


# 先把 SQL 放到目标机（前面步骤已 tar 到 /tmp/upgrade_ai_short.sql）
out, err = run("ls -la /tmp/upgrade_ai_short.sql 2>&1 | head -2")
print("[sql file]", out.strip(), err.strip()[:200])

# 用 php 执行：读 .env 取库名，避免命令行明文密码
php = r'''cd /www/wwwroot/douyin/php && php -r '
$env = parse_ini_file(".env", true);
$d = $env["DATABASE"];
$pdo = new PDO("mysql:host=".$d["HOSTNAME"].";port=".$d["HOSTPORT"].";dbname=".$d["DATABASE"].";charset=utf8mb4",
               $d["USERNAME"], $d["PASSWORD"], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$sql = file_get_contents("/tmp/upgrade_ai_short.sql");
$pdo->exec($sql);
echo "EXEC_OK\n";
$c = $pdo->query("SELECT COUNT(*) FROM lp_short_preset WHERE status=1")->fetchColumn();
echo "presets=".$c."\n";
$r = $pdo->query("SELECT code,title,LEFT(prompt,50) AS p,duration_sec,cover_url FROM lp_short_preset ORDER BY id LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
foreach ($r as $x) { echo json_encode($x, JSON_UNESCAPED_UNICODE)."\n"; }
$bad = $pdo->query("SELECT COUNT(*) FROM lp_short_preset WHERE prompt=\x27\x27 OR lora_chain IS NULL")->fetchColumn();
echo "empty_prompt_or_lora=".$bad."\n";
'
'''
out, err = run(php, 600)
print("[exec sql]\n" + out)
if err.strip():
    print("ERR:", err[:1500])
j.close()
