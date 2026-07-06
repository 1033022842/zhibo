import paramiko
import sys
import time

host = '192.168.1.216'
user = 'yang'
pwd = 'a@123456'

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())

def run_sudo(cmd, desc=""):
    """Run a command with sudo, print output in real-time"""
    print(f"\n>>> [{desc}] {cmd}")
    full_cmd = f'echo "a@123456" | sudo -S {cmd} 2>&1'
    stdin, stdout, stderr = ssh.exec_command(full_cmd)
    out = stdout.read().decode()
    err = stderr.read().decode()
    if out:
        # Filter out the sudo password prompt
        lines = out.split('\n')
        for line in lines:
            if 'password for' not in line and line.strip():
                print(line)
    if err and 'password for' not in err:
        print('STDERR:', err.strip())
    return out

try:
    ssh.connect(host, username=user, password=pwd, timeout=10)
    print('=== SSH 连接成功 ===\n')

    # Step 1: Fix GPT backup table
    print("=" * 50)
    print("Step 1: 修复 GPT 备份表")
    print("=" * 50)
    run_sudo("sgdisk -e /dev/sda", "修复 GPT 备份表")

    # Step 2: Check current partition layout
    print("\n" + "=" * 50)
    print("Step 2: 扩展分区 /dev/sda3 到磁盘末尾")
    print("=" * 50)
    
    # Use growpart to resize partition 3 on sda
    run_sudo("growpart /dev/sda 3", "扩展分区 sda3")
    
    # If growpart fails, try parted
    print("\n--- 当前分区状态 ---")
    run_sudo("parted /dev/sda print free", "查看分区")

    # Step 3: Resize the PV
    print("\n" + "=" * 50)
    print("Step 3: 扩展 PV")
    print("=" * 50)
    run_sudo("pvresize /dev/sda3", "扩展 PV")

    # Step 4: Extend the LV to use all free space
    print("\n" + "=" * 50)
    print("Step 4: 扩展 LV 到最大")
    print("=" * 50)
    run_sudo("lvextend -l +100%FREE /dev/ubuntu-vg/ubuntu-lv", "扩展 LV")
    
    # Step 5: Resize the filesystem
    print("\n" + "=" * 50)
    print("Step 5: 扩展文件系统")
    print("=" * 50)
    run_sudo("resize2fs /dev/ubuntu-vg/ubuntu-lv", "扩展文件系统")

    # Step 6: Verify
    print("\n" + "=" * 50)
    print("验证结果")
    print("=" * 50)
    print("\n--- df -h ---")
    run_sudo("df -h /", "磁盘使用")
    
    print("\n--- lsblk ---")
    run_sudo("lsblk /dev/sda", "块设备")
    
    print("\n--- PV 状态 ---")
    run_sudo("pvdisplay /dev/sda3", "PV")
    
    print("\n--- VG 状态 ---")
    run_sudo("vgdisplay ubuntu-vg", "VG")
    
    print("\n--- LV 状态 ---")
    run_sudo("lvdisplay /dev/ubuntu-vg/ubuntu-lv", "LV")
    
    print("\n=== 磁盘扩展完成 ===")

    ssh.close()
except Exception as e:
    print(f'失败: {e}')
    import traceback
    traceback.print_exc()
    sys.exit(1)
