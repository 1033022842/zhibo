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
    stdin, stdout, stderr = cli.exec_command(cmd, timeout=60)
    return (stdout.read().decode("utf-8", "replace") + stderr.read().decode("utf-8", "replace")).strip()


D = "/www/wwwroot/douyin/vue/dist/js"
print("create is_adult  :", run(f"grep -c is_adult {D}/CrowdfundingCreate-BCDTA0u_.js"))
print("create 200字     :", run(f"grep -c '不少于200字' {D}/CrowdfundingCreate-BCDTA0u_.js"))
print("detail deliverables:", run(f"grep -c deliverables {D}/CrowdfundingDetail-Cmm9dppy.js"))
print("list card-adult  :", run(f"grep -c card-adult-tag {D}/CrowdfundingList-DUoQmY3Q.js"))

cli.close()
print("DONE")
