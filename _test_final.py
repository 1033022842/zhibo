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
    channel.recv_exit_status()
    channel.close()
    if out: print(out[:800])

IMG = '/storage/certification/20260812/4d3991ba4a42ffa7e34553a283420232ed7370f7.jpg'

print('=== Final verification ===')
exec_cmd(f"curl -s -o /dev/null -w 'Port 8082 (no host): %{{http_code}}\n' http://127.0.0.1:8082{IMG}")
exec_cmd(f"curl -s -o /dev/null -w 'Port 8082 (with host): %{{http_code}}\n' -H 'Host: 38.181.44.164:8082' http://127.0.0.1:8082{IMG}")
exec_cmd(f"curl -s -o /dev/null -w 'Port 80 (with host): %{{http_code}}\n' -H 'Host: 38.181.44.164' http://127.0.0.1:80{IMG}")
exec_cmd(f"curl -s -o /dev/null -w 'Port 80 (ext IP): %{{http_code}}\n' http://38.181.44.164:80{IMG}")

print('\nAll 200 = images work on both ports!')

transport.close()
