import paramiko

JUMP = dict(host="172.81.99.11", pw="mw-DGckAL,61fV00")
KEY = "/root/.ssh/n99_pull"

def ssh(j, cmd, timeout=180):
    _, o, e = j.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", "replace")
    err = e.read().decode("utf-8", "replace")
    return out, err

j = paramiko.SSHClient()
j.set_missing_host_key_policy(paramiko.AutoAddPolicy())
j.connect(JUMP["host"], 22, "root", JUMP["pw"], timeout=30, look_for_keys=False, allow_agent=False)

S = f"ssh -i {KEY} -o StrictHostKeyChecking=no -o BatchMode=yes root@172.81.98.55"

out, err = ssh(j, S + " 'mkdir -p /tmp/cnblock /root/deploy /root/nginx_bak/cnblock_20261025'")
print(out, err)

# tar 流：跳板 /tmp/cnblock → 98.55
out, err = ssh(j, f"cd /tmp/cnblock && tar -cf - . | {S} 'tar -xf - -C /tmp/cnblock' && echo TAR_OK")
print(out, err)
if "TAR_OK" not in out:
    raise SystemExit("tar 流失败")

apply = (
    S + " '\nset -e\n"
    "cp /etc/nginx/conf.d/00-cn-block.conf /root/nginx_bak/cnblock_20261025/00-cn-block.conf.old\n"
    "cp /etc/nginx/bunny_realip.conf /root/nginx_bak/cnblock_20261025/bunny_realip.conf.old 2>/dev/null || true\n"
    "cp /tmp/cnblock/00-cn-block.conf  /etc/nginx/conf.d/00-cn-block.conf\n"
    "cp /tmp/cnblock/cn_geo.conf       /etc/nginx/cn_geo.conf\n"
    "cp /tmp/cnblock/bunny_realip.conf /etc/nginx/bunny_realip.conf\n"
    "cp /tmp/cnblock/cn_geo.py /tmp/cnblock/bunny_realip.py /tmp/cnblock/update_cn_block.sh /root/deploy/\n"
    "chmod +x /root/deploy/update_cn_block.sh\n"
    "nginx -t 2>&1 && systemctl reload nginx && echo SUGUS_RELOADED\n"
    "grep -q update_cn_block /var/spool/cron/crontabs/root 2>/dev/null || "
    "echo \"0 4 1 * * /root/deploy/update_cn_block.sh >> /var/log/cn_block.log 2>&1\" >> /var/spool/cron/crontabs/root\n"
    "echo --- crontab:; crontab -l | grep cn_block\n'"
)
out, err = ssh(j, apply)
print(out)
if err.strip():
    print("ERR:", err[:600])
j.close()
