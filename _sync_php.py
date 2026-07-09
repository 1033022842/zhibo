#!/usr/bin/env python3
"""同步 PHP 后端修改文件到服务器"""
import paramiko
import tarfile
import os, io, time

host = "38.181.44.164"
user = "root"
password = "Mr3$Ye7]Dx7|"
php_dir = r"d:\ever\douyin\douyin\php"
tar_file = r"d:\ever\douyin\douyin\_php_sync.tar"

# AI 项目相关的 PHP 文件（相对于 php/ 目录）
files_to_sync = [
    "app/api/controller/Live.php",
    "app/live/service/UserService.php",
    "app/live/service/PersonaService.php",
    "app/live/validate/UserValidate.php",
    "app/live/middleware.php",
    "app/live/middleware/Auth.php",
    "app/room/model/Persona.php",
    "app/room/model/Room.php",
    "app/room/service/RoomService.php",
    "app/api/controller/Room.php",
    "app/api/controller/RoomSwitch.php",
    "app/api/controller/AiTask.php",
    "app/api/controller/LiveUser.php",
    "app/ai/service/AiTaskService.php",
    "app/ai/middleware/AiAuth.php",
    "app/ai/model/AiTask.php",
    "app/ai/model/AiTaskLog.php",
    "app/common/web/ResultCode.php",
    "app/common/service/TgService.php",
    "app/common/middleware/AllowCrossDomain.php",
    "app/live/controller/UserController.php",
    "app/admin/controller/live/Persona.php",
    "app/admin/model/live/Persona.php",
    "app/admin/controller/user/LiveUser.php",
    "app/admin/controller/live/Room.php",
    "app/admin/controller/live/Revenue.php",
    "app/admin/controller/live/ReplayClip.php",
    "app/admin/controller/live/MaintenanceConfig.php",
    "app/admin/controller/live/Leaderboard.php",
    "app/admin/controller/live/MerchantCertification.php",
    "app/admin/model/live/Room.php",
    "app/admin/model/live/ReplayClip.php",
    "config/ai.php",
    "route/live.php",
    "app/live/route.php",
]

# 加上 app/api/config/route.php (api 路由)
extra_configs = [
    "app/api/config/route.php",
    "app/api/route.php",
    "app/api/config/middleware.php",
    "app/api/middleware.php",
]

all_files = files_to_sync + extra_configs

print(f"[1/4] 打包 {len(all_files)} 个文件...")
with tarfile.open(tar_file, 'w') as tar:
    for f in all_files:
        full = os.path.join(php_dir, f)
        if os.path.isfile(full):
            tar.add(full, arcname=f)
        else:
            print(f"  SKIP (not found): {f}")

size_mb = os.path.getsize(tar_file) / 1024 / 1024
print(f"  打包完成: {size_mb:.2f} MB")

# SFTP 上传
print("[2/4] 上传...")
ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(host, username=user, password=password, timeout=30)
sftp = ssh.open_sftp()

remote_tar = "/tmp/php_sync.tar"
sftp.put(tar_file, remote_tar)
sftp.close()
print("  上传完成")

# 备份 + 解压
print("[3/4] 备份旧文件并解压新文件...")
stdin, stdout, stderr = ssh.exec_command(f"""
    cd /www/wwwroot/douyin/php/
    
    # 备份（首次同步）
    if [ ! -d /tmp/php_backup ]; then
        mkdir -p /tmp/php_backup
        for f in {' '.join(all_files)}; do
            if [ -f "$f" ]; then
                dir=$(dirname "/tmp/php_backup/$f")
                mkdir -p "$dir"
                cp "$f" "/tmp/php_backup/$f" 2>/dev/null
            fi
        done
        echo "Backup created"
    fi
    
    # 解压覆盖
    tar xf /tmp/php_sync.tar -C /www/wwwroot/douyin/php/
    rm -f /tmp/php_sync.tar
    chown -R www:www /www/wwwroot/douyin/php/
    echo "Extracted OK"
""")
print(stdout.read().decode(errors='replace'))

# 验证
print("[4/4] 验证关键文件...")
stdin, stdout, stderr = ssh.exec_command("""
    echo "=== registerFromAi ==="
    grep -c 'registerFromAi' /www/wwwroot/douyin/php/app/api/controller/Live.php
    grep -c 'registerFromAi' /www/wwwroot/douyin/php/app/live/service/UserService.php
    
    echo "=== login account compat ==="
    grep "account" /www/wwwroot/douyin/php/app/api/controller/Live.php | grep -c "empty"
    
    echo "=== config/ai.php ==="
    test -f /www/wwwroot/douyin/php/config/ai.php && echo "EXISTS" || echo "MISSING"
    
    echo ""
    echo "=== 测试登录 API ==="
    curl -s -H "Host: 38.181.44.164" -X POST "http://127.0.0.1/api/live/login" -d "account=1033022842@qq.com" -d "password=123456" | head -c 200
""")
output = stdout.read().decode(errors='replace')
print(output)

ssh.close()
os.remove(tar_file)
print("[DONE]")
