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
    print(f'CMD: {cmd[:80]}...')
    if out.strip():
        print(out.strip())
    if err.strip():
        print('ERR:', err.strip()[:200])
    return out.strip()

try:
    ssh.connect(host, username=user, password=pwd, timeout=15)
    
    # 1. Stop SABnzbd to free port 8080
    print('=== 处理8080端口 ===')
    run('echo "a@123456" | sudo -S snap stop sabnzbd 2>&1')
    run('echo "a@123456" | sudo -S snap disable sabnzbd 2>&1')
    
    # 2. Find MySQL credentials from BT Panel
    print('\n=== 查找MySQL凭证 ===')
    # BT Panel default paths
    run('cat /www/server/panel/data/default.pl 2>&1 | grep -i mysql | head -5')
    
    # Try BT Panel's mysql management
    print('\n--- BT Panel MySQL配置 ---')
    run('echo "a@123456" | sudo -S cat /www/server/panel/data/default.pl 2>&1 | head -20')
    
    # Check BT Panel's panel database for MySQL info
    print('\n--- 检查宝塔面板配置 ---')
    run('echo "a@123456" | sudo -S ls /www/server/panel/class/ 2>&1 | head -20')
    
    # Try BT command
    run('echo "a@123456" | sudo -S bt default 2>&1')
    
    # Check /www/server/panel for mysql info
    print('\n--- www/server/panel ---')
    run('echo "a@123456" | sudo -S ls /www/server/panel/ 2>&1 | head -20')
    
    # Alternative: check if we can connect with BT tools
    run('echo "a@123456" | sudo -S cat /root/.my.cnf 2>&1')
    run('cat ~/.my.cnf 2>&1')
    
    ssh.close()
except Exception as e:
    print(f'失败: {e}')
