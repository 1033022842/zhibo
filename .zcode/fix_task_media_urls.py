#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""把 lp_ai_task 里误存的「签名 URL」还原成稳定的 /storage/ 相对路径。

背景（2026-10-22）：api 应用缺 MediaInputNormalizer 中间件，worker 回调
complete 时传的签名地址（https://media.<domain>/sec/<project>/...?e=..&s=..）
被原样写进 lp_ai_task.video_url / cover_url。签名 6 小时过期后，
历史任务的图片/视频全部 410 裂图。

中间件已补（代码修复），本脚本清理历史脏数据。

规则：
  https://media.<host>/sec/<project>/<rel>?...   → /storage/<rel>
  https://media.<host>/pub/<project>/<rel>?...   → /storage/<rel>
  仅处理 task_type in (custom_video, custom_image)，其它任务类型不动。

用法：
  python .zcode/fix_task_media_urls.py --check    # 只读统计
  python .zcode/fix_task_media_urls.py --apply    # 执行（先备份到 /root/）
"""
import argparse
import sys

import paramiko

SITES = {
    "sugus":   ("172.81.98.55", "0wJfy`50tJQzG/0X", "zhibo", "zhibo", "12345678"),
    "heygirl": ("172.81.99.11", "mw-DGckAL,61fV00", "live_platform", "root", "root"),
    "kisss":   ("103.217.252.52", "Aa1033022842", "live_platform",
                "live_platform", "CTkPmtXq0EOC320xQzWs"),
    "kelymi":  ("172.81.99.42", "ffG2dAUGc{V572{j", "live_platform", "root", "root"),
}
ORDER = ["sugus", "heygirl", "kisss", "kelymi"]

# 统计：有多少行是「签名地址」形态
STAT_SQL = (
    "SELECT COUNT(*) AS total,"
    " SUM(video_url LIKE 'https://media.%') AS video_signed,"
    " SUM(cover_url LIKE 'https://media.%') AS cover_signed"
    " FROM lp_ai_task"
    " WHERE task_type IN ('custom_video','custom_image')"
    "  AND (video_url LIKE 'https://media.%' OR cover_url LIKE 'https://media.%');"
)

# 清理：用 SUBSTRING_INDEX 剥掉域名和 /sec|pub/<project>/ 前缀，再去掉查询串
#   https://media.sugus.ai/sec/sugus/custom_video/x.png?e=1&amp;s=2
#   → custom_video/x.png?e=1&amp;s=2
#   → custom_video/x.png
#   → /storage/custom_video/x.png
def clean_expr(col):
    """https://media.<host>/(sec|pub)/<project>/<rel>?query  →  /storage/<rel>

    ⚠️ 必须用 REGEXP_REPLACE：早期版本用
       CONCAT('/storage/', SUBSTRING_INDEX(SUBSTRING_INDEX(col,'/',6),'/',-1))
       误取「第6段之后最后一段」而不是「第6段之后全部」，把
       custom_video/20261022/xxx.png 压成 custom_video，导致 59 行数据被毁。
       数据已按备份恢复，这里改对。
    MySQL 8 才有 REGEXP_REPLACE（本站 MySQL 8，OK）。
    """
    return (
        "CASE WHEN {c} REGEXP '^https://media\.' THEN"
        " CONCAT('/storage/',"
        "   SUBSTRING_INDEX("
        "     REGEXP_REPLACE({c}, '^https://media\.[^/]+/(sec|pub)/[^/]+/', ''),"
        "   '?', 1))"
        " ELSE {c} END"
    ).format(c=col)


def connect(host, pw, t=30):
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect(host, 22, "root", pw, timeout=t, banner_timeout=t, auth_timeout=t,
              look_for_keys=False, allow_agent=False)
    return c


def mysql(c, db, du, dp, sql, t=180):
    inner = sql.replace("'", "'\\''")
    cmd = ("MYSQL_PWD='%s' mysql -u%s %s -N -B -e '%s' 2>&1" % (dp, du, db, inner))
    stdin, out, err = c.exec_command(cmd, timeout=t)
    o = out.read().decode("utf-8", "replace")
    e = err.read().decode("utf-8", "replace")
    return out.channel.recv_exit_status(), o.strip(), e.strip()


def check_site(name):
    host, pw, db, du, dp = SITES[name]
    c = connect(host, pw)
    try:
        rc, o, e = mysql(c, db, du, dp, STAT_SQL)
        parts = o.split()
        if len(parts) >= 3:
            def _n(x):
                try:
                    return int(x)
                except (TypeError, ValueError):
                    return 0
            return {"site": name, "total": _n(parts[0]),
                    "video": _n(parts[1]), "cover": _n(parts[2])}
        return {"site": name, "error": (e or o)[:120]}
    finally:
        c.close()


def apply_site(name):
    host, pw, db, du, dp = SITES[name]
    c = connect(host, pw)
    try:
        rc, o, e = mysql(c, db, du, dp, STAT_SQL)
        parts = o.split()
        try:
            total = int(parts[0]) if parts else 0
        except (TypeError, ValueError):
            total = 0
        print("  [%s] 待清理 %d 行" % (name, total))
        if total == 0:
            return True

        # 备份受影响行
        stamp = __import__("time").strftime("%Y%m%d%H%M%S")
        bk = "/root/task_url_bak_%s_%s.sql" % (name, stamp)
        dump = ("SELECT id, task_no, video_url, cover_url FROM lp_ai_task "
                "WHERE task_type IN ('custom_video','custom_image') "
                "AND (video_url LIKE 'https://media.%' OR cover_url LIKE 'https://media.%');")
        inner = dump.replace("'", "'\\''")
        cmd = ("MYSQL_PWD='%s' mysql -u%s %s -e '%s' > %s 2>&1; wc -l < %s"
               % (dp, du, db, inner, bk, bk))
        stdin, out, err = c.exec_command(cmd, timeout=180)
        out.read()
        print("    备份: %s" % bk)

        sql = (
            "UPDATE lp_ai_task SET "
            " video_url = %s, cover_url = %s "
            "WHERE task_type IN ('custom_video','custom_image') "
            " AND (video_url LIKE 'https://media.%%' OR cover_url LIKE 'https://media.%%');"
        ) % (clean_expr("video_url"), clean_expr("cover_url"))
        rc, o, e = mysql(c, db, du, dp, sql.replace("%%", "%"))
        print("    UPDATE rc=%d %s" % (rc, (o or e)[:120]))

        rc, o, e = mysql(c, db, du, dp, STAT_SQL)
        parts = o.split()
        try:
            left = int(parts[0]) if parts else -1
        except (TypeError, ValueError):
            left = -1
        print("    清理后剩余: %d %s" % (left, "✅" if left == 0 else "❌"))
        return left == 0
    finally:
        c.close()


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--check", action="store_true")
    ap.add_argument("--apply", action="store_true")
    ap.add_argument("--site", choices=list(SITES))
    args = ap.parse_args()
    targets = [args.site] if args.site else ORDER

    if args.apply:
        ok = True
        for s in targets:
            try:
                ok = apply_site(s) and ok
            except Exception as ex:
                print("  [%s] ❌ %s" % (s, ex))
                ok = False
        return 0 if ok else 1

    print("%-8s %8s %8s %8s" % ("SITE", "脏行", "video", "cover"))
    for s in targets:
        i = check_site(s)
        if "error" in i:
            print("%-8s  ERROR %s" % (s, i["error"]))
        else:
            print("%-8s %8d %8d %8d" % (s, i["total"], i["video"], i["cover"]))
    return 0


if __name__ == "__main__":
    sys.exit(main())
