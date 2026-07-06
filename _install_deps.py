import paramiko
import sys

host = '192.168.1.216'
user = 'yang'
pwd = 'a@123456'

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())

def run(cmd, desc=""):
    if desc:
        print(f"\n=== {desc} ===")
    full_cmd = cmd
    stdin, stdout, stderr = ssh.exec_command(full_cmd)
    out = stdout.read().decode()
    err = stderr.read().decode()
    if out.strip():
        print(out.strip())
    if err.strip():
        # Filter out common warnings
        for line in err.strip().split('\n'):
            if 'WARNING' not in line and 'Warning' not in line:
                print('STDERR:', line)
    return out

try:
    ssh.connect(host, username=user, password=pwd, timeout=15)
    print('=== 安装缺失依赖 ===')

    # 1. Install Node.js 20.x (LTS)
    print("\n>>> 1. 安装 Node.js 20.x")
    run('curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -S -E bash - 2>&1')
    run('echo "a@123456" | sudo -S apt-get install -y nodejs 2>&1')
    
    # 2. Verify Node.js
    run('node -v', 'Node.js版本验证')
    run('npm -v', 'npm版本验证')
    
    # 3. Install pnpm
    print("\n>>> 2. 安装 pnpm")
    run('echo "a@123456" | sudo -S npm install -g pnpm 2>&1')
    run('pnpm -v', 'pnpm版本验证')
    
    # 4. Install Composer
    print("\n>>> 3. 安装 Composer")
    run('php -r "copy(\'https://getcomposer.org/installer\', \'composer-setup.php\');" 2>&1')
    run('php composer-setup.php --quiet 2>&1')
    run('echo "a@123456" | sudo -S mv composer.phar /usr/local/bin/composer 2>&1')
    run('rm -f composer-setup.php 2>&1')
    run('composer --version', 'Composer版本验证')
    
    # 5. Install Redis
    print("\n>>> 4. 安装 Redis")
    run('echo "a@123456" | sudo -S apt-get install -y redis-server 2>&1')
    run('echo "a@123456" | sudo -S systemctl enable redis-server 2>&1')
    run('echo "a@123456" | sudo -S systemctl start redis-server 2>&1')
    run('redis-cli ping 2>&1', 'Redis验证')
    
    # 6. Install npx serve
    print("\n>>> 5. 安装 serve")
    run('echo "a@123456" | sudo -S npm install -g serve 2>&1')
    
    # 7. Check what's on port 8080
    print("\n>>> 6. 检查8080端口占用")
    run('echo "a@123456" | sudo -S ps aux | grep 1216 | head -5')
    run('echo "a@123456" | sudo -S lsof -i :8080 2>&1')
    
    ssh.close()
    print('\n=== 依赖安装完成 ===')
except Exception as e:
    print(f'失败: {e}')
    import traceback
    traceback.print_exc()
    sys.exit(1)
