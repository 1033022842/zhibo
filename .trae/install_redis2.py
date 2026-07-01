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

# Step 1: Remove the invalid redis.so line from php.ini
print("=== Remove invalid redis line ===")
print(run("sed -i '/^extension=redis.so/d' /www/server/php/83/etc/php.ini"))
print(run("grep redis /www/server/php/83/etc/php.ini || echo '(no redis lines)'"))

# Step 2: Download and compile redis manually
print("\n=== Download redis ===")
print(run("cd /tmp && curl -sL https://pecl.php.net/get/redis-6.1.0.tgz -o redis.tgz && ls -la redis.tgz 2>&1"))
print(run("cd /tmp && tar xzf redis.tgz && ls redis-*/ 2>&1 | head -5"))

# Step 3: Compile
print("\n=== phpize ===")
print(run("cd /tmp/redis-* && /www/server/php/83/bin/phpize 2>&1"))

print("\n=== configure ===")
print(run("cd /tmp/redis-* && ./configure --with-php-config=/www/server/php/83/bin/php-config 2>&1 | tail -5"))

print("\n=== make ===")
print(run("cd /tmp/redis-* && make -j$(nproc) 2>&1 | tail -5"))

print("\n=== make install ===")
print(run("cd /tmp/redis-* && make install 2>&1"))

# Step 4: Enable
print("\n=== Enable redis ===")
print(run("ls -la /www/server/php/83/lib/php/extensions/no-debug-non-zts-20230831/redis.so 2>&1"))
print(run("echo 'extension=redis.so' >> /www/server/php/83/etc/php.ini"))

# Restart
print("\n=== Restart PHP-FPM ===")
print(run("/etc/init.d/php-fpm-83 restart 2>&1"))

# Verify
print("\n=== Verify ===")
print(run("php -r 'var_dump(extension_loaded(\"redis\")); var_dump(extension_loaded(\"Zend OPcache\"));' 2>&1"))

print("\n=== Admin test ===")
print(run('curl -s -o /dev/null -w "HTTP:%{http_code}" http://127.0.0.1:8081/admin/Index/index'))

ssh.close()
