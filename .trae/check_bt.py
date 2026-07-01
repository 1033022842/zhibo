import paramiko
import sys
import io

sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')

host = "45.194.18.42"
username = "root"
password = "@#22879#$%AQ"

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(host, username=username, password=password, timeout=15)
print("[OK] Connected!")

cmds = [
    ("BT installed?", "ls /www/server/panel/ 2>/dev/null | head -5; echo '---'; ls /etc/init.d/bt 2>/dev/null"),
    ("BT process?", "ps aux | grep -E 'BT|bt-panel|Bt' | grep -v grep"),
    ("BT default?", "/etc/init.d/bt default 2>&1"),
    ("Recent install log?", "ls -lt /tmp/bt* 2>/dev/null | head -3; tail -30 /tmp/bt_install.log 2>/dev/null || echo 'no log'"),
]

for label, cmd in cmds:
    print(f"\n--- {label} ---")
    stdin, stdout, stderr = ssh.exec_command(cmd)
    out = stdout.read().decode("utf-8", errors="replace").strip()
    if out:
        print(out)
    else:
        print("(empty)")

ssh.close()
