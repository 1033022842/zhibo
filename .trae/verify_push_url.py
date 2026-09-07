import sys
import json
import paramiko

HOST = "38.181.44.164"
USER = "root"
PWD = "Mr3$Ye7]Dx7|"


def main():
    cli = paramiko.SSHClient()
    cli.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    cli.connect(HOST, username=USER, password=PWD, timeout=20)
    cmd = "curl -sk -m 20 -H 'X-Api-Key: live-ai-api-key-2026' 'https://hsl.lat/api/v1/liveportrait/config?room_id=1'"
    stdin, stdout, stderr = cli.exec_command(cmd, timeout=30)
    raw = stdout.read().decode("utf-8", "replace")
    err = stderr.read().decode("utf-8", "replace")

    try:
        data = json.loads(raw)
        stream = data.get("data", {}).get("stream", {})
        print("push_url   =", stream.get("push_url"))
        print("stream_alias=", stream.get("stream_alias"))
        print("play_hls   =", stream.get("play_hls"))
    except Exception as e:
        print("解析失败:", e)
        print("raw:", raw[:500])
        print("err:", err[:500])

    cli.close()
    return 0


if __name__ == "__main__":
    sys.exit(main())
