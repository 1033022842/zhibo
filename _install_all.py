import paramiko

host = '192.168.1.216'
user = 'yang'
pwd = 'a@123456'

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())

def run_install(cmd):
    stdin, stdout, stderr = ssh.exec_command(cmd)
    out = stdout.read().decode()
    err = stderr.read().decode()
    return out, err

try:
    ssh.connect(host, username=user, password=pwd, timeout=15)
    print('开始安装...')

    # Install everything with apt
    full_cmd = '''
echo "a@123456" | sudo -S bash -c '
# Install NodeSource
curl -fsSL https://deb.nodesource.com/setup_20.x | bash - 2>/tmp/ns_err.log
apt-get install -y nodejs 2>>/tmp/ns_err.log

# Install Composer
php -r "copy(\"https://getcomposer.org/installer\", \"composer-setup.php\");" 2>/tmp/cp_err.log
php composer-setup.php --install-dir=/usr/local/bin --filename=composer 2>>/tmp/cp_err.log
rm -f composer-setup.php

# Install pnpm and serve
npm install -g pnpm serve 2>/tmp/npm_err.log

# Install Redis
apt-get install -y redis-server 2>/tmp/redis_err.log
systemctl enable redis-server
systemctl start redis-server

# Verify
echo "=== VERIFY ===" > /tmp/install_result.txt
node -v >> /tmp/install_result.txt 2>&1
npm -v >> /tmp/install_result.txt 2>&1
pnpm -v >> /tmp/install_result.txt 2>&1
composer --version >> /tmp/install_result.txt 2>&1
redis-cli ping >> /tmp/install_result.txt 2>&1
serve --version >> /tmp/install_result.txt 2>&1
echo "=== DONE ===" >> /tmp/install_result.txt
'
'''

    stdin, stdout, stderr = ssh.exec_command(full_cmd)
    out = stdout.read().decode()
    err = stderr.read().decode()
    if out:
        print(out)
    if err:
        print('STDERR:', err[:500])

    # Read result
    stdin, stdout, stderr = ssh.exec_command('cat /tmp/install_result.txt')
    print('\n=== 安装验证结果 ===')
    print(stdout.read().decode())

    ssh.close()
except Exception as e:
    print(f'失败: {e}')
