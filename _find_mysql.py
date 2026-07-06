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
    if err.strip():
        print('ERR:', err.strip()[:500])
    return out.strip()

try:
    ssh.connect(host, username=user, password=pwd, timeout=15)
    
    # 1. Read BT Panel default.pl  
    print('=== BT default.pl ===')
    run('echo "a@123456" | sudo -S cat /www/server/panel/default.pl 2>&1')
    
    # 2. Read default.db as SQLite
    print('\n=== BT Panel default.db (config table) ===')
    run('echo "a@123456" | sudo -S sqlite3 /www/server/panel/data/default.db ".tables" 2>&1')
    run('echo "a@123456" | sudo -S sqlite3 /www/server/panel/data/default.db "SELECT * FROM config WHERE name LIKE \"%mysql%\" OR name LIKE \"%db%\" LIMIT 5;" 2>&1')
    run('echo "a@123456" | sudo -S sqlite3 /www/server/panel/data/default.db "SELECT name FROM config LIMIT 20;" 2>&1')
    
    # 3. Use BT's Python config module directly
    print('\n=== BT Python获取MySQL ===')
    cmd = """echo "a@123456" | sudo -S python3 << 'PYEOF'
import sys
sys.path.insert(0, '/www/server/panel/class')
try:
    from config import config
    c = config()
    # Try different methods
    for method in ['get_mysql_root', '_get_db', 'get_db_info']:
        if hasattr(c, method):
            try:
                result = getattr(c, method)()
                print(f"{method}: {result}")
            except Exception as e:
                print(f"{method} error: {e}")
except Exception as e:
    print(f"config import error: {e}")
PYEOF"""
    run(cmd)
    
    ssh.close()
except Exception as e:
    print(f'失败: {e}')
