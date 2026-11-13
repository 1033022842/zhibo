import base64
import sys

sys.path.insert(0, r"D:\ever\douyin\douyin\.zcode")
import gpu166  # noqa: E402

CONF = r"""map $http_upgrade $connection_upgrade {
    default upgrade;
    ''      close;
}

server {
    listen 8188;
    listen [::]:8188;
    server_name _;

    client_max_body_size 500m;
    client_body_timeout 300s;
    proxy_read_timeout 600s;
    proxy_send_timeout 600s;

    auth_basic "ComfyUI";
    auth_basic_user_file /etc/nginx/.htpasswd;

    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection $connection_upgrade;
        proxy_buffering off;
    }
}
"""

import os, sys as _s
_s.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import deploy_creds as creds  # noqa: E402
PW = creds.get("gpu166")["password"]
cli, _ = gpu166.connect()


def run(cmd, t=120):
    _, o, e = cli.exec_command(cmd, timeout=t)
    code = o.channel.recv_exit_status()
    return code, o.read().decode("utf-8", "replace"), e.read().decode("utf-8", "replace")


b64 = base64.b64encode(CONF.encode()).decode()
step = (
    "echo %s | sudo -S -p '' bash -c '"
    "echo %s | base64 -d > /etc/nginx/sites-available/comfy && "
    "ln -sf /etc/nginx/sites-available/comfy /etc/nginx/sites-enabled/comfy && "
    "rm -f /etc/nginx/sites-enabled/default && nginx -t'" % (PW, b64)
)
print("== write conf ==")
print(run(step))

print("== htpasswd ==")
print(run("echo %s | sudo -S -p '' bash -c 'htpasswd -bc /etc/nginx/.htpasswd comfy H3comfy2026x && chmod 640 /etc/nginx/.htpasswd && ls -l /etc/nginx/.htpasswd'" % PW))

print("== start nginx ==")
print(run("echo %s | sudo -S -p '' bash -c 'systemctl enable --now nginx && systemctl is-active nginx && ss -lntp | grep 8188'" % PW))

cli.close()
