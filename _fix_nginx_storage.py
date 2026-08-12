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
    out = channel.makefile('rb').read().decode(errors='replace')
    code = channel.recv_exit_status()
    channel.close()
    if out: print(out[:2500])

# Check extension config
exec_cmd('cat /www/server/panel/vhost/nginx/extension/38.181.44.164/site_total.conf')

# Check the REST of the main config (lines 70+)
exec_cmd('sed -n "70,$ p" /www/server/panel/vhost/nginx/38.181.44.164.conf')

# The issue might be that the ^~ location is parsed wrong
# Let me test nginx location matching
exec_cmd("curl -s -I -H 'Host: 38.181.44.164' http://127.0.0.1:80/storage/certification/20260812/4d3991ba4a42ffa7e34553a283420232ed7370f7.jpg 2>&1 | head -12")

# Check if there's another server block for port 80
exec_cmd('grep -rn "listen.*80" /www/server/nginx/conf/ 2>/dev/null | head -5')
exec_cmd('ls /www/server/panel/vhost/nginx/*.conf | head -10')

# Maybe the default config is intercepting?
exec_cmd('cat /www/server/panel/vhost/nginx/0.default.conf 2>/dev/null | head -20')

transport.close()
