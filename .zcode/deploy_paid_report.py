#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""把「付费用户 / 用户流水」功能部署到 4 站：
   1) 推 Analysis.php 控制器
   2) 跑幂等 SQL（补菜单按钮）
   3) reload php-fpm
   4) 推 admin/dist（前端报表页）
"""
import os
import sys

import paramiko

REPO = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
PHP_ROOT = "/www/wwwroot/douyin/php"

CTRL = "php/app/admin/controller/track/Analysis.php"
SQL = "php/sql/upgrade_track_report.sql"
DIST_DIR = "php/web/dist"

REMOTE_CTRL = PHP_ROOT + "/app/admin/controller/track/Analysis.php"
REMOTE_SQL = PHP_ROOT + "/sql/upgrade_track_report.sql"
REMOTE_DIST = "/www/wwwroot/douyin/admin/dist"

SITES = {
    "heygirl": dict(host="172.81.99.11", port=22, password="***REMOVED-SSH-PW***"),
    "kelymi":  dict(host="172.81.99.42", port=22, password="***REMOVED-SSH-PW***"),
    "kiss":    dict(host="111.90.150.230", port=20203, password="***REMOVED-SSH-PW***"),
}
SUGUS = dict(jump_host="172.81.99.11", jump_pw="***REMOVED-SSH-PW***",
             host="172.81.98.55", key="/root/.ssh/n99_pull")


def run(cli, cmd, t=240):
    _, out, err = cli.exec_command(cmd, timeout=t)
    return out.read().decode("utf-8", "replace").strip(), err.read().decode("utf-8", "replace").strip()


def deploy_direct(name, cfg):
    print(f"\n===== {name} ({cfg['host']}) =====", flush=True)
    cli = paramiko.SSHClient()
    cli.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    cli.connect(cfg["host"], cfg["port"], "root", cfg["password"],
                timeout=30, look_for_keys=False, allow_agent=False)

    # 1) 控制器
    run(cli, f"cp -a {REMOTE_CTRL} {REMOTE_CTRL}.bak.paid")
    sftp = cli.open_sftp()
    sftp.put(os.path.join(REPO, CTRL.replace("/", os.sep)), REMOTE_CTRL)
    sftp.put(os.path.join(REPO, SQL.replace("/", os.sep)), REMOTE_SQL)
    sftp.close()
    o, _ = run(cli, f"php -l {REMOTE_CTRL} 2>&1 | tail -1")
    print(f"  lint: {o[:80]}")
    if "No syntax errors" not in o:
        run(cli, f"cp -a {REMOTE_CTRL}.bak.paid {REMOTE_CTRL}")
        print("  !! 语法错，已回滚")
        cli.close()
        return False

    # 2) SQL
    o, e = run(cli, f"php -r 'define(\"ROOT\",\"{PHP_ROOT}\"); require \"{PHP_ROOT}/vendor/autoload.php\";"
                    f" $a=new think\\App(\"{PHP_ROOT}\"); $a->initialize();"
                    f" $db=think\\facade\\Db::connect(\"live_mysql\");"
                    f" $s=preg_replace(\"/^\\s*--.*$/m\",\"\",file_get_contents(\"{REMOTE_SQL}\"));"
                    f" $n=0; foreach(array_filter(array_map(\"trim\",explode(\";\",$s))) as $q){{ if($q===\"\")continue; $db->execute($q); $n++; }}"
                    f" echo \"SQL_OK stmts=$n\";' 2>&1", t=300)
    line = (o or e).strip().splitlines()[-1] if (o or e) else ""
    print(f"  SQL: {line[:100]}")

    # 3) reload
    o, _ = run(cli, "systemctl reload php*-fpm 2>/dev/null || systemctl reload php8.3-fpm; echo RC=$?")
    print(f"  php-fpm: {o[:40]}")

    # 4) 校验菜单
    o, _ = run(cli, f"php -r 'define(\"ROOT\",\"{PHP_ROOT}\"); require \"{PHP_ROOT}/vendor/autoload.php\";"
                    f" $a=new think\\App(\"{PHP_ROOT}\"); $a->initialize();"
                    f" $r=think\\facade\\Db::connect(\"live_mysql\")->query(\"SELECT COUNT(*) c FROM ba_admin_rule WHERE name LIKE \\\"track/analysis%\\\"\");"
                    f" echo $r[0][\"c\"];' 2>&1")
    print(f"  菜单规则: {o.strip()} 条")

    cli.close()
    return True


def deploy_sugus():
    print(f"\n===== sugus (via jump) =====", flush=True)
    j = paramiko.SSHClient()
    j.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    j.connect(SUGUS["jump_host"], 22, "root", SUGUS["jump_pw"],
              timeout=30, look_for_keys=False, allow_agent=False)
    sftp = j.open_sftp()
    sftp.put(os.path.join(REPO, CTRL.replace("/", os.sep)), "/tmp/Analysis_new.php")
    sftp.put(os.path.join(REPO, SQL.replace("/", os.sep)), "/tmp/upgrade_track.sql")
    sftp.close()

    inner = (
        f"cp -a {REMOTE_CTRL} {REMOTE_CTRL}.bak.paid; "
        f"cp /tmp/Analysis_new.php {REMOTE_CTRL}; "
        f"cp /tmp/upgrade_track.sql {REMOTE_SQL}; "
        f"php -l {REMOTE_CTRL} 2>&1 | tail -1; "
        "systemctl reload php*-fpm 2>/dev/null || systemctl reload php8.3-fpm; echo RELOAD_RC=$?"
    )
    cmd = ("scp -i {key} -o StrictHostKeyChecking=no /tmp/Analysis_new.php "
           "{user}@{host}:/tmp/Analysis_new.php && "
           "scp -i {key} -o StrictHostKeyChecking=no /tmp/upgrade_track.sql "
           "{user}@{host}:/tmp/upgrade_track.sql && "
           'ssh -i {key} -o StrictHostKeyChecking=no {user}@{host} ').format(
        key=SUGUS["key"], user="root", host=SUGUS["host"]) + "'" + inner + "'"
    o, e = run(j, cmd, t=300)
    print("  " + (o or e)[:400].replace("\n", "\n  "))

    # SQL（远端执行）
    inner2 = (
        f"php -r 'define(\"ROOT\",\"{PHP_ROOT}\"); require \"{PHP_ROOT}/vendor/autoload.php\";"
        f" $a=new think\\App(\"{PHP_ROOT}\"); $a->initialize();"
        f" $db=think\\facade\\Db::connect(\"live_mysql\");"
        f" $s=preg_replace(\"/^\\s*--.*$/m\",\"\",file_get_contents(\"{REMOTE_SQL}\"));"
        f" $n=0; foreach(array_filter(array_map(\"trim\",explode(\";\",$s))) as $q){{ if($q===\"\")continue; $db->execute($q); $n++; }}"
        f" echo \"SQL_OK stmts=$n\";'"
    )
    cmd2 = ("ssh -i {key} -o StrictHostKeyChecking=no {user}@{host} ").format(
        key=SUGUS["key"], user="root", host=SUGUS["host"]) + '"' + inner2 + '"'
    o2, e2 = run(j, cmd2, t=300)
    print("  SQL: " + (o2 or e2)[:150].replace("\n", " "))
    j.close()
    return True


def main():
    targets = sys.argv[1:] or ["heygirl", "kelymi", "kiss", "sugus"]
    for n in targets:
        if n == "sugus":
            deploy_sugus()
        elif n in SITES:
            deploy_direct(n, SITES[n])
    print("\n完成")


if __name__ == "__main__":
    main()
