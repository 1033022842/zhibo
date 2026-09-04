import sys
import paramiko

HOST = "38.181.44.164"
USER = "root"
PWD = "Mr3$Ye7]Dx7|"

LOCAL_ZIP = r"d:\phpstudy_pro\WWW\douyin\liveportrait-worker-ai-pc.zip"
REMOTE_ZIP = "/www/wwwroot/douyin/liveportrait-worker-ai-pc.zip"


def main():
    cli = paramiko.SSHClient()
    cli.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    cli.connect(HOST, username=USER, password=PWD, timeout=20)
    sftp = cli.open_sftp()
    sftp.put(LOCAL_ZIP, REMOTE_ZIP)
    sftp.close()

    stdin, stdout, stderr = cli.exec_command(f"ls -la {REMOTE_ZIP}")
    print(stdout.read().decode())

    cli.close()
    print("UPLOAD_OK")
    return 0


if __name__ == "__main__":
    sys.exit(main())
