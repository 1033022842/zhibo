import sys
import paramiko

BAK = "/root/nginx_bak/cnblock_20261025"

CLIENTS = {
    "heygirl": {
        "host": "172.81.99.11", "pw": "mw-DGckAL,61fV00",
        "site_confs": ["/etc/nginx/sites-enabled/zhibo.conf", "/etc/nginx/sites-enabled/heygirl-cdn-origin.conf"],
        "local_confs": ["heygirl_zhibo.conf", "heygirl_cdn.conf"],
        "cron_mode": "add",
    },
    "kelymi": {
        "host": "172.81.99.42", "pw": "ffG2dAUGc{V572{j",
        "site_confs": ["/etc/nginx/sites-enabled/zhibo.conf", "/etc/nginx/sites-enabled/kelymi-cdn-origin.conf"],
        "local_confs": ["kelymi_zhibo.conf", "kelymi_cdn.conf"],
        "cron_mode": "add",
    },
    "kiss": {
        "host": "103.217.252.52", "pw": "Aa1033022842",
        "site_confs": ["/etc/nginx/sites-enabled/kiss.conf", "/etc/nginx/sites-enabled/kiss-cdn-origin.conf"],
        "local_confs": ["kiss.conf", "kiss_cdn.conf"],
        "cron_mode": "replace",   # 已有 update_bunny_realip.sh 月度 cron → 换成统一脚本
    },
}

FILES = {
    "00-cn-geo.conf": "/etc/nginx/conf.d/00-cn-geo.conf",
    "cn_geo.conf": "/etc/nginx/cn_geo.conf",
    "bunny_realip.conf": "/etc/nginx/bunny_realip.conf",
    "snippets/cn-block-site.conf": "/etc/nginx/snippets/cn-block-site.conf",
    "snippets/cn-block-full.conf": "/etc/nginx/snippets/cn-block-full.conf",
    "cn_geo.py": "/root/deploy/cn_geo.py",
    "deploy/bunny_realip.py": "/root/deploy/bunny_realip.py",
    "deploy/update_cn_block.sh": "/root/deploy/update_cn_block.sh",
}

name = sys.argv[1]
cfg = CLIENTS[name]
c = paramiko.SSHClient()
c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
c.connect(cfg["host"], 22, "root", cfg["pw"], timeout=30, look_for_keys=False, allow_agent=False)

# 1) 备份
_, o, e = c.exec_command(
    f"mkdir -p {BAK} /etc/nginx/snippets /root/deploy && "
    + " ".join(f"cp {p} {BAK}/{p.split('/')[-1]}.old;" for p in cfg["site_confs"])
    + f" cp /etc/nginx/bunny_realip.conf {BAK}/bunny_realip.conf.old 2>/dev/null; echo BAK_OK",
    timeout=60)
print(o.read().decode(), e.read().decode()[:200])

# 2) 上传
s = c.open_sftp()
for local, remote in FILES.items():
    s.put(local, remote)
for local, remote in zip(cfg["local_confs"], cfg["site_confs"]):
    s.put(local, remote)
s.chmod("/root/deploy/update_cn_block.sh", 0o755)
s.close()
print("upload done")

# 3) 语法检查 + reload + cron
cron_cmd = {
    "add": "crontab -l 2>/dev/null | grep -q update_cn_block || "
           "(crontab -l 2>/dev/null; echo '0 4 1 * * /root/deploy/update_cn_block.sh >> /var/log/cn_block.log 2>&1') | crontab -",
    "replace": "crontab -l 2>/dev/null | grep -v update_bunny_realip | "
               "{ cat; echo '0 4 1 * * /root/deploy/update_cn_block.sh >> /var/log/cn_block.log 2>&1'; } | crontab -",
}[cfg["cron_mode"]]

_, o, e = c.exec_command(
    "nginx -t 2>&1 && systemctl reload nginx && echo RELOADED; " + cron_cmd + "; echo --- crontab:; crontab -l | grep -E 'cn_block|bunny'",
    timeout=120)
print(o.read().decode())
err = e.read().decode()
if err.strip():
    print("ERR:", err[:600])
c.close()
