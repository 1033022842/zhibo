import paramiko

HOST = '38.181.44.164'
USER = 'root'
PASSWORD = 'Mr3$Ye7]Dx7|'
IMG = '/storage/certification/20260812/4d3991ba4a42ffa7e34553a283420232ed7370f7.jpg'

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
    if out: print(out[:1200])
    return out

# Test with headers only
exec_cmd(f"curl -s -I -H 'Host: 38.181.44.164:8082' http://127.0.0.1:8082{IMG} 2>&1 | head -15")
exec_cmd(f"curl -s -w 'HTTP_CODE: %{{http_code}}\nSIZE: %{{size_download}}\n' -o /dev/null http://38.181.44.164:8082{IMG}")
exec_cmd(f"curl -s -w 'HTTP_CODE: %{{http_code}}\n' -o /dev/null -H 'Host: 38.181.44.164:8082' http://127.0.0.1:8082{IMG}")

# Check nginx access log
exec_cmd("tail -5 /www/wwwlogs/ai-girl-38.181.44.164.log | grep storage")
exec_cmd("tail -5 /www/wwwlogs/ai-girl-38.181.44.164.error.log")

# Also check: maybe the issue is a Cloudflare/CDN fronting the domain?
# Check if the domain resolves
exec_cmd("nslookup ai-girl-malaysia.com 2>/dev/null || echo 'No DNS'")

transport.close()
