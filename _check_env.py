import paramiko
import sys

host = '192.168.1.216'
user = 'yang'
pwd = 'a@123456'

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())

def run(cmd, desc=""):
    if desc:
        print(f"\n--- {desc} ---")
    stdin, stdout, stderr = ssh.exec_command(cmd)
    out = stdout.read().decode()
    err = stderr.read().decode()
    if out.strip():
        print(out.strip())
    if err.strip():
        print('STDERR:', err.strip())

try:
    ssh.connect(host, username=user, password=pwd, timeout=10)
    print('=== 服务器环境检查 ===')

    run('uname -a', '操作系统')
    run('cat /etc/os-release | head -5', 'OS版本')
    run('php -v 2>&1 | head -3', 'PHP版本')
    run('php -m 2>&1 | grep -iE "pdo|mysql|redis|curl|json|mbstring|fileinfo|gd|openssl|swoole|sockets"', 'PHP关键扩展')
    run('node -v 2>&1', 'Node.js')
    run('npm -v 2>&1', 'npm')
    run('which pnpm 2>&1; pnpm -v 2>&1', 'pnpm')
    run('which composer 2>&1; composer --version 2>&1', 'Composer')
    run('mysql --version 2>&1', 'MySQL客户端')
    run('redis-cli --version 2>&1', 'Redis客户端')
    run('echo "a@123456" | sudo -S systemctl status mysql 2>&1 | head -5', 'MySQL服务')
    run('echo "a@123456" | sudo -S systemctl status redis 2>&1 | head -5', 'Redis服务')
    run('which npx 2>&1', 'npx')
    run('which serve 2>&1; serve --version 2>&1', 'serve')
    run('which git 2>&1', 'git')
    run('echo "a@123456" | sudo -S ufw status 2>&1', '防火墙')
    run('echo "a@123456" | sudo -S ss -tlnp 2>&1', '监听端口')
    run('cat /etc/ssh/sshd_config 2>&1 | grep -i "^Port"', 'SSH端口')
    
    ssh.close()
except Exception as e:
    print(f'失败: {e}')
    sys.exit(1)
