import json, sys
ips = json.load(open('/etc/nginx/bunny_edges.json'))
if len(ips) < 100:
    sys.exit('edge list too short: %d' % len(ips))
lines = ['set_real_ip_from %s;' % i for i in ips]
open('/etc/nginx/bunny_realip.conf','w').write(chr(10).join(lines) + chr(10))
print('bunny edges:', len(ips))
