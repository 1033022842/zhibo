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

# Check redis source in PHP ext dir
print("=== Check redis source ===")
print(run("ls /www/server/php/83/src/ext/redis/config.m4 2>/dev/null && echo 'FOUND IN PHP SRC' || echo 'NOT IN PHP SRC'"))

# Check PHP pecl
print("=== Check pecl ===")
print(run("which pecl 2>/dev/null && pecl version 2>/dev/null || echo 'NO PECL'"))

print("=== Check phpize ===")
print(run("ls /www/server/php/83/bin/phpize 2>/dev/null && echo 'OK'"))

# Check php-config
print("=== php-config ===")
print(run("ls /www/server/php/83/bin/php-config 2>/dev/null && echo 'OK'"))

# Check if redis-server is installed
print("=== Redis server ===")
print(run("redis-cli ping 2>&1"))

ssh.close()
