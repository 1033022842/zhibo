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
    if out.strip():
        print(out.strip())
    if err.strip() and 'Warning' not in err:
        print('ERR:', err.strip()[:500])
    return out.strip()

try:
    ssh.connect(host, username=user, password=pwd, timeout=15)
    
    # 1. Read default.pl properly  
    print('=== BT default.pl 完整内容 ===')
    run('echo "a@123456" | sudo -S xxd /www/server/panel/default.pl 2>&1 | head -40')
    run('echo "a@123456" | sudo -S file /www/server/panel/default.pl 2>&1')
    
    # 2. Explore SQLite schema
    print('\n=== SQLite schema ===')
    run('echo "a@123456" | sudo -S sqlite3 /www/server/panel/data/default.db ".schema config" 2>&1')
    
    # 3. Try reading with proper escaping
    print('\n=== 查询config表 ===')
    run("""echo "a@123456" | sudo -S sqlite3 /www/server/panel/data/default.db 'SELECT name FROM config;' 2>&1""")
    run("""echo "a@123456" | sudo -S sqlite3 /www/server/panel/data/default.db 'SELECT * FROM config;' 2>&1 | head -50""")
    
    # 4. Use BT panel to get MySQL
    print('\n=== 使用BT面板命令获取MySQL ===')
    run('echo "a@123456" | sudo -S bt 7 2>&1')
    
    ssh.close()
except Exception as e:
    print(f'失败: {e}')
