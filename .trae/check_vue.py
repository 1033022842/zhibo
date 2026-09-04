import sys
import paramiko

try:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
except Exception:
    pass

c = paramiko.SSHClient()
c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
c.connect("38.181.44.164", username="root", password="Mr3$Ye7]Dx7|", timeout=25)

def run(cmd):
    stdin, stdout, stderr = c.exec_command(cmd, timeout=60)
    return stdout.read().decode("utf-8", "replace") + stderr.read().decode("utf-8", "replace")

print("=== /www/wwwroot/douyin/vue/dist ===")
print(run("ls -la /www/wwwroot/douyin/vue/dist 2>&1 | head -40"))
print("=== nginx vue root ===")
print(run("grep -rn 'vue/dist' /www/server/panel/vhost/nginx/ 2>/dev/null | head -20"))
print("=== /www/wwwroot/douyin 顶层 ===")
print(run("ls -la /www/wwwroot/douyin 2>&1 | head -40"))

c.close()
