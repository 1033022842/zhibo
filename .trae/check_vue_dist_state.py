import sys
import paramiko

try:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
except Exception:
    pass

HOST = "38.181.44.164"
USER = "root"
PWD = "Mr3$Ye7]Dx7|"

cli = paramiko.SSHClient()
cli.set_missing_host_key_policy(paramiko.AutoAddPolicy())
cli.connect(HOST, username=USER, password=PWD, timeout=30)


def run(cmd):
    stdin, stdout, stderr = cli.exec_command(cmd, timeout=120)
    out = stdout.read().decode("utf-8", "replace")
    err = stderr.read().decode("utf-8", "replace")
    return (out + err).strip()


print("=== vue dir ===")
print(run("ls -la /www/wwwroot/douyin/vue | head -20"))
print("=== dist file count ===")
print(run("find /www/wwwroot/douyin/vue/dist -type f 2>/dev/null | wc -l"))
print("=== dist index ===")
print(run("ls /www/wwwroot/douyin/vue/dist 2>/dev/null | head -10"))
print("=== dist js ===")
print(run("ls /www/wwwroot/douyin/vue/dist/js 2>/dev/null | head -20"))

cli.close()
print("DONE")
