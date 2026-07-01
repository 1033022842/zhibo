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

# Step 1: Install redis via pecl
print("=== Install redis via pecl ===")
# pecl might ask interactive questions, use yes pipe
print(run("printf '\n' | /www/server/php/83/bin/pecl install redis 2>&1 | tail -15"))

# Check if redis.so was created
print("\n=== Check redis.so ===")
print(run("ls -la /www/server/php/83/lib/php/extensions/no-debug-non-zts-20230831/redis.so 2>/dev/null || echo 'NOT FOUND'"))

# Step 2: Add redis extension to php.ini
print("\n=== Add redis to php.ini ===")
# Check if already exists
existing = run("grep 'extension=redis' /www/server/php/83/etc/php.ini")
if 'redis' not in existing:
    print(run("echo 'extension=redis.so' >> /www/server/php/83/etc/php.ini"))
print(run("grep redis /www/server/php/83/etc/php.ini"))

# Step 3: Re-enable opcache (restore ONE zend_extension line)
print("\n=== Re-enable opcache ===")
# Uncomment the full-path version on line 1929
print(run("sed -i 's|^;zend_extension=/www/server/php/83/lib/php/extensions/no-debug-non-zts-20230831/opcache.so|zend_extension=/www/server/php/83/lib/php/extensions/no-debug-non-zts-20230831/opcache.so|' /www/server/php/83/etc/php.ini"))
print(run("grep -n 'opcache' /www/server/php/83/etc/php.ini | grep -v '^#' | grep -v ';'"))

# Also fix php-cli.ini
print("\n=== Fix php-cli.ini opcache ===")
print(run("sed -i 's|^;zend_extension=/www/server/php/83/lib/php/extensions/no-debug-non-zts-20230831/opcache.so|zend_extension=/www/server/php/83/lib/php/extensions/no-debug-non-zts-20230831/opcache.so|' /www/server/php/83/etc/php-cli.ini"))

# Restart PHP-FPM
print("\n=== Restart PHP-FPM ===")
print(run("/etc/init.d/php-fpm-83 restart 2>&1"))

# Verify
print("\n=== PHP extension check ===")
print(run("php -m 2>&1 | grep -iE 'redis|opcache'"))

print("\n=== Detailed check ===")
print(run("php -r 'var_dump(extension_loaded(\"redis\")); var_dump(extension_loaded(\"Zend OPcache\"));' 2>&1"))

# Test admin
print("\n=== Admin test ===")
print(run('curl -s -o /dev/null -w "HTTP:%{http_code}" http://127.0.0.1:8081/admin/Index/index'))

ssh.close()
