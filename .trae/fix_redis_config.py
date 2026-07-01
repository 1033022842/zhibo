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

# Check php.ini for redis
print("=== php.ini redis lines ===")
print(run("grep -n redis /www/server/php/83/etc/php.ini || echo 'NOT FOUND'"))
print(run("grep -n redis /www/server/php/83/etc/php-cli.ini || echo 'NOT FOUND'"))

# Check last 5 lines of php.ini
print("\n=== php.ini tail ===")
print(run("tail -5 /www/server/php/83/etc/php.ini"))

# Add to php-cli.ini too
print("\n=== Add redis to php-cli.ini ===")
print(run("echo 'extension=redis.so' >> /www/server/php/83/etc/php-cli.ini"))

# Test CLI
print("\n=== CLI test ===")
print(run("php -r 'var_dump(extension_loaded(\"redis\"));' 2>&1"))

# Also test via web context
print("\n=== Web context test ===")
print(run("curl -s http://127.0.0.1:8081/admin/Index/index 2>&1 | head -c 200"))

ssh.close()
