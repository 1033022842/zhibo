import sys
import paramiko

HOST = "38.181.44.164"
USER = "root"
PWD = "Mr3$Ye7]Dx7|"

CMDS = [
    "echo '=== SRS 当前流 video 参数 ==='",
    "curl -s -m 10 'http://127.0.0.1:1985/api/v1/streams' | python3 -c \"import sys,json; d=json.load(sys.stdin); s=d.get('streams') or []; [print('app=',x.get('app'),'name=',x.get('name'),'video=',x.get('video'),'kbps=',x.get('kbps'),'frames=',x.get('frames')) for x in s]\"",
    "echo '=== 动作模板素材规格(前5个) ==='",
    "mysql -uzhibo -p12345678 -N -e \"SELECT CONCAT(file_url) FROM zhibo.lp_media_asset WHERE asset_role='motion' AND file_url LIKE '%.%' LIMIT 5;\" | while read f; do echo \"-- $f\"; ffprobe -v error -select_streams v:0 -show_entries stream=width,height,r_frame_rate,codec_name -show_entries format=duration -of default=noprint_wrappers=1 \"/www/wwwroot/douyin/media/\$f\" 2>/dev/null; done",
    "echo '=== 立绘素材 ==='",
    "mysql -uzhibo -p12345678 -N -e \"SELECT asset_role, file_url FROM zhibo.lp_media_asset WHERE asset_role='portrait' LIMIT 5;\"",
]


def main():
    cli = paramiko.SSHClient()
    cli.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    cli.connect(HOST, username=USER, password=PWD, timeout=20)
    for c in CMDS:
        print("$", c[:100])
        stdin, stdout, stderr = cli.exec_command(c, timeout=60)
        out = stdout.read().decode("utf-8", "replace")
        err = stderr.read().decode("utf-8", "replace")
        if out:
            print(out.rstrip())
        if err:
            print("[stderr]", err.rstrip())
        print()
    cli.close()
    return 0


if __name__ == "__main__":
    sys.exit(main())
