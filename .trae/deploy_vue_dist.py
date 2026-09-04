import sys
import paramiko

HOST = "38.181.44.164"
USER = "root"
PWD = "Mr3$Ye7]Dx7|"

LOCAL_ZIP = r"d:\phpstudy_pro\WWW\douyin\vue-dist.zip"
REMOTE_ZIP = "/tmp/vue_dist.zip"


def main():
    cli = paramiko.SSHClient()
    cli.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    cli.connect(HOST, username=USER, password=PWD, timeout=30)

    sftp = cli.open_sftp()
    sftp.put(LOCAL_ZIP, REMOTE_ZIP)
    sftp.close()
    print("[upload] vue-dist.zip")

    cmd = (
        "cd /www/wwwroot/douyin/vue && "
        "mv dist dist_backup_$(date +%Y%m%d%H%M%S) && "
        "mkdir dist && "
        "unzip -o /tmp/vue_dist.zip -d dist >/dev/null && "
        "echo DEPLOY_OK && "
        "ls dist | head -5"
    )
    stdin, stdout, stderr = cli.exec_command(cmd, timeout=120)
    out = stdout.read().decode("utf-8", "replace")
    err = stderr.read().decode("utf-8", "replace")
    print(out)
    if err:
        print("[stderr]", err)

    cli.close()
    return 0


if __name__ == "__main__":
    sys.exit(main())
