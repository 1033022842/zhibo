import sys
import paramiko

HOST = "38.181.44.164"
USER = "root"
PWD = "Mr3$Ye7]Dx7|"

KEYWORDS = "玫瑰,比心,飞吻,撩发,害羞,傲娇,待机,专注,日常,投喂,安慰,诱惑,侧颜,感谢,话术"

CMDS = [
    # 给所有动作模板填充关键词（先跑通：任何礼物关键词都能匹配到动作模板）
    f"mysql -uzhibo -p12345678 -e \"UPDATE zhibo.lp_media_asset SET keywords='{KEYWORDS}' WHERE asset_role='motion';\"",
    "echo '--- 校验 motion keywords ---'",
    "mysql -uzhibo -p12345678 -N -e \"SELECT COUNT(*) AS total, SUM(keywords<>'') AS has_kw FROM zhibo.lp_media_asset WHERE asset_role='motion';\"",
    "mysql -uzhibo -p12345678 -e \"SELECT id, title, keywords FROM zhibo.lp_media_asset WHERE asset_role='motion' LIMIT 3;\"",
]


def main():
    cli = paramiko.SSHClient()
    cli.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    cli.connect(HOST, username=USER, password=PWD, timeout=20)
    for c in CMDS:
        print("$", c)
        stdin, stdout, stderr = cli.exec_command(c, timeout=60)
        out = stdout.read().decode("utf-8", "replace")
        err = stderr.read().decode("utf-8", "replace")
        if out:
            print(out.rstrip())
        if err and "Warning" not in err:
            print("[stderr]", err.rstrip())
        print()
    cli.close()
    return 0


if __name__ == "__main__":
    sys.exit(main())
