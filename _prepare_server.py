import paramiko

host = '192.168.1.216'
user = 'yang'
pwd = 'a@123456'

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())

def run(cmd):
    stdin, stdout, stderr = ssh.exec_command(cmd)
    out = stdout.read().decode()
    err = stderr.read().decode()
    return out.strip(), err.strip()

try:
    ssh.connect(host, username=user, password=pwd, timeout=15)
    
    # 1. Check what's on port 8080
    print('=== 检查8080端口 ===')
    out, err = run('echo "a@123456" | sudo -S lsof -i :8080 2>&1')
    print(out)
    
    out, err = run('echo "a@123456" | sudo -S ps aux | grep 1216 | grep -v grep')
    print(out)
    
    # 2. Open firewall ports
    print('\n=== 开放防火墙端口 ===')
    ports = [8000, 8788, 3000, 8080]
    for port in ports:
        out, err = run(f'echo "a@123456" | sudo -S ufw allow {port}/tcp 2>&1')
        print(f'Port {port}: {out}')
    
    # 3. Check MySQL status
    print('\n=== MySQL状态 ===')
    out, err = run('echo "a@123456" | sudo -S systemctl status mysql 2>&1 | head -8')
    print(out)
    
    # 4. Check if MySQL has live_platform database
    print('\n=== 检查数据库 ===')
    out, err = run('echo "a@123456" | sudo -S mysql -e "SHOW DATABASES;" 2>&1')
    print(out)
    
    # 5. Check PHP extensions for ThinkPHP
    print('\n=== PHP关键扩展 ===')
    out, err = run('php -m 2>&1 | grep -iE "pdo|mysql|redis|curl|json|mbstring|fileinfo|gd|openssl|xml|tokenizer|ctype|session"')
    print(out)
    
    ssh.close()
except Exception as e:
    print(f'失败: {e}')
