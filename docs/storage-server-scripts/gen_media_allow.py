import re
import subprocess

# Bunny 边缘 IP（来自 update_bunny_realip.sh 生成的 realip 清单）
src = open('/etc/nginx/bunny_realip.conf').read()
ips = re.findall(r'set_real_ip_from\s+([^;]+);', src)

# 本机公网 IP：让自己也能打自己的 public IP（健康检查/自测方便）
try:
    own = subprocess.run(
        ['curl', '-s', '--max-time', '5', 'https://api.ipify.org'],
        capture_output=True, text=True, check=True).stdout.strip()
except Exception:
    own = ''

lines = ['# 自动生成：Bunny 边缘 + 项目机 + 本机。解锁执行 unlock_media_origin.sh']
lines += ['allow %s;' % ip for ip in ips]
lines += ['allow 172.81.98.55;', 'allow 127.0.0.1;', 'allow ::1;']
if own and own not in ips:
    lines.append('allow %s;' % own)
lines.append('deny all;')

with open('/etc/nginx/snippets/media_origin_allow.conf.new', 'w') as f:
    f.write(chr(10).join(lines) + chr(10))
print('allow lines:', len(lines) - 2)
