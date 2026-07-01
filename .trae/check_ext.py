import paramiko
import io
import sys

sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('38.181.44.164', username='root', password='Mr3$Ye7]Dx7|', timeout=15)

def run(cmd):
    stdin, stdout, stderr = ssh.exec_command(cmd)
    return stdout.read().decode() + stderr.read().decode()

print("=== Redis extension ===")
print(run("php -m 2>&1 | grep -i redis || echo 'NOT INSTALLED'"))

print("\n=== Opcache extension ===")
print(run("php -m 2>&1 | grep -i opcache || echo 'NOT INSTALLED'"))

print("\n=== php.ini redis ===")
print(run("grep -rn 'redis' /www/server/php/83/etc/ 2>/dev/null | grep -v '^#'"))

# Check if redis.so exists in ext dir
print("\n=== ext dir contents ===")
print(run("ls /www/server/php/83/lib/php/extensions/no-debug-non-zts-20230831/"))

ssh.close()
