"""查看远端 tarball 上传进度"""
import os
import sys

import paramiko

try:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
except Exception:
    pass

OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "_upload_probe.txt")
cli = paramiko.SSHClient()
cli.set_missing_host_key_policy(paramiko.AutoAddPolicy())
cli.connect("172.81.98.55", username="root", password="0wJfy`50tJQzG/0X",
            timeout=30, banner_timeout=60, auth_timeout=60,
            allow_agent=False, look_for_keys=False)
sftp = cli.open_sftp()
lines = []
for p in ["/www/wwwroot/douyin/_extra_assets.tgz"]:
    try:
        st = sftp.stat(p)
        lines.append("%s -> %.2f MB" % (p, st.st_size / 1024 / 1024))
    except IOError as exc:
        lines.append("%s -> MISSING (%s)" % (p, exc))
_, out, _ = cli.exec_command("df -h /www | tail -1; uptime", timeout=30)
lines.append(out.read().decode("utf-8", "replace").strip())
sftp.close()
cli.close()
with open(OUT, "w", encoding="utf-8") as f:
    f.write("\n".join(lines) + "\n")
print("\n".join(lines))
