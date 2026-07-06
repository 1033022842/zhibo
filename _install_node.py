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
    stdin, stdout, stderr = ssh.exec_command(full_cmd, get_pty=True)
    out = stdout.read().decode()
    err = stderr.read().decode()
    if out.strip():
        print(out.strip())
    if err.strip():
        print(err.strip())
    return out

try:
    ssh.connect(host, username=user, password=pwd, timeout=15)
    print('=== 安装 Node.js 20.x ===')
    
    # Install Node.js
    run('curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -S -E bash -', '下载NodeSource脚本')
    run('echo "a@123456" | sudo -S apt-get install -y nodejs', '安装Node.js')
    run('node -v', '验证Node.js')
    run('npm -v', '验证npm')
    
    ssh.close()
except Exception as e:
    print(f'失败: {e}')
    import traceback
    traceback.print_exc()
