import paramiko

HOST = '38.181.44.164'
USER = 'root'
PASSWORD = 'Mr3$Ye7]Dx7|'

transport = paramiko.Transport((HOST, 22))
transport.start_client()
transport.auth_interactive(USER, lambda *a: [PASSWORD] if a[2] else [])

def exec_cmd(cmd):
    print(f'> {cmd}')
    channel = transport.open_session()
    channel.exec_command(cmd)
    out = channel.makefile('r').read().decode(errors='replace')
    err = channel.makefile_stderr('r').read().decode(errors='replace')
    channel.recv_exit_status()
    channel.close()
    if out: print(out[:1500])
    if err: print(f'ERR: {err[:300]}')

# Check upload controller to see returned URL format
print('=== Merchant upload controller ===')
exec_cmd('head -100 /www/wwwroot/douyin/php/app/api/controller/Merchant.php')

# Check Upload library
print('\n=== Upload library ===')
exec_cmd('head -80 /www/wwwroot/douyin/php/app/common/library/Upload.php')

# Check nginx config for ai-girl
print('\n=== AI-Girl Nginx ===')
exec_cmd('cat /www/server/panel/vhost/nginx/ai-girl-38.181.44.164.conf')

# Check main nginx for storage handling
print('\n=== Main Nginx (port 80) storage ===')
exec_cmd('grep -A5 "storage" /www/server/panel/vhost/nginx/38.181.44.164.conf 2>/dev/null || echo "No storage in main config"')

# Check the actual file permissions
print('\n=== Storage dir ===')
exec_cmd('ls -laR /www/wwwroot/douyin/php/public/storage/ 2>/dev/null')

# Test from external perspective (via host header)
print('\n=== External access test ===')
exec_cmd("curl -s -o /dev/null -w '%{http_code}' -H 'Host: 38.181.44.164:8082' http://127.0.0.1:8082/storage/certification/20260812/4d3991ba4a42ffa7e34553a283420232ed7370f7.jpg && echo ''")

transport.close()
