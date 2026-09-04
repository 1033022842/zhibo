import sys
import paramiko

HOST = "38.181.44.164"
USER = "root"
PWD = "Mr3$Ye7]Dx7|"

CMDS = [
    "echo '=== lp_gift ==='",
    "mysql -uzhibo -p12345678 -e \"SELECT id, gift_code, name, trigger_mode, trigger_duration_sec, status FROM zhibo.lp_gift ORDER BY id;\"",
    "echo '=== lp_gift_keyword ==='",
    "mysql -uzhibo -p12345678 -e \"SELECT * FROM zhibo.lp_gift_keyword ORDER BY gift_id;\" 2>&1 | head -30",
    "echo '=== lp_media_asset role count ==='",
    "mysql -uzhibo -p12345678 -e \"SELECT asset_role, COUNT(*) AS cnt FROM zhibo.lp_media_asset GROUP BY asset_role;\"",
    "echo '=== motion assets ==='",
    "mysql -uzhibo -p12345678 -e \"SELECT id, asset_code, title, keywords, persona, file_url FROM zhibo.lp_media_asset WHERE asset_role='motion' LIMIT 20;\"",
    "echo '=== motion count ==='",
    "mysql -uzhibo -p12345678 -N -e \"SELECT COUNT(*) FROM zhibo.lp_media_asset WHERE asset_role='motion';\"",
    "echo '=== video count (legacy) ==='",
    "mysql -uzhibo -p12345678 -N -e \"SELECT COUNT(*) FROM zhibo.lp_media_asset WHERE asset_role='' AND asset_type='video';\"",
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
