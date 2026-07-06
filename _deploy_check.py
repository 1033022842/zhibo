import paramiko
import sys

host = '192.168.1.216'
user = 'yang'
pwd = 'a@123456'

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
try:
    ssh.connect(host, username=user, password=pwd, timeout=10)
    print('=== SSH 连接成功 ===')

    def run(cmd):
        stdin, stdout, stderr = ssh.exec_command(cmd)
        out = stdout.read().decode()
        err = stderr.read().decode()
        if out:
            print(out)
        if err:
            print('[stderr]', err)

    print('--- lsblk ---')
    run('lsblk')

    print('--- df -h ---')
    run('df -h')

    print('--- sudo pvdisplay ---')
    run('echo "a@123456" | sudo -S pvdisplay 2>&1')

    print('--- sudo vgdisplay ---')
    run('echo "a@123456" | sudo -S vgdisplay 2>&1')

    print('--- sudo lvdisplay ---')
    run('echo "a@123456" | sudo -S lvdisplay 2>&1')

    print('--- sudo fdisk -l ---')
    run('echo "a@123456" | sudo -S fdisk -l 2>&1')

    ssh.close()
except Exception as e:
    print(f'连接失败: {e}')
    sys.exit(1)
