import paramiko

host = "38.181.44.164"
user = "root"
pwd = "Mr3$Ye7]Dx7|"

c = paramiko.SSHClient()
c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
c.connect(host, username=user, password=pwd, timeout=60)

def run(cmd, title):
    print("===== " + title + " =====")
    stdin, stdout, stderr = c.exec_command(cmd, timeout=120)
    print(stdout.read().decode("utf-8", "replace"))
    err = stderr.read().decode("utf-8", "replace")
    if err.strip():
        print("STDERR:", err)

run("node -v; npm -v; which pnpm 2>/dev/null; ls /www/wwwroot/douyin/php/web/node_modules 2>/dev/null | head -3; echo '--- dist ---'; ls /www/wwwroot/douyin/admin/dist/index.html 2>/dev/null && echo dist_exists", "build env")
run("cat /www/wwwroot/douyin/php/web/package.json | grep -A5 '\"scripts\"'", "package scripts")

c.close()
print("DONE")
