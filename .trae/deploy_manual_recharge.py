import os
import sys
import datetime
import zipfile
import paramiko

try:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
except Exception:
    pass

HOST = "38.181.44.164"
USER = "root"
PWD = "Mr3$Ye7]Dx7|"

LOCAL_DIST = r"d:\phpstudy_pro\WWW\douyin\php\web\dist"
LOCAL_ZIP = r"d:\phpstudy_pro\WWW\douyin\admin-dist.zip"
REMOTE_ZIP = "/tmp/admin_dist.zip"
REMOTE_ADMIN = "/www/wwwroot/douyin/admin"

PHP_FILES = [
    (r"d:\phpstudy_pro\WWW\douyin\php\app\admin\controller\user\LiveUser.php",
     "/www/wwwroot/douyin/php/app/admin/controller/user/LiveUser.php"),
]

STAMP = datetime.datetime.now().strftime("%Y%m%d_%H%M%S")


def build_zip():
    if os.path.exists(LOCAL_ZIP):
        os.remove(LOCAL_ZIP)
    count = 0
    with zipfile.ZipFile(LOCAL_ZIP, "w", zipfile.ZIP_DEFLATED) as zf:
        for root, _dirs, files in os.walk(LOCAL_DIST):
            for name in files:
                full = os.path.join(root, name)
                rel = os.path.relpath(full, LOCAL_DIST).replace("\\", "/")
                zf.write(full, rel)
                count += 1
    size = os.path.getsize(LOCAL_ZIP)
    print(f"[zip] {LOCAL_ZIP} files={count} size={size}")
    return count


def run(cli, cmd, title):
    print("$", title)
    stdin, stdout, stderr = cli.exec_command(cmd, timeout=180)
    out = stdout.read().decode("utf-8", "replace").rstrip()
    err = stderr.read().decode("utf-8", "replace").rstrip()
    if out:
        print(out)
    if err:
        print("[stderr]", err)
    print()


def main():
    php_only = "php-only" in sys.argv

    if not php_only:
        build_zip()

    cli = paramiko.SSHClient()
    cli.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    cli.connect(HOST, username=USER, password=PWD, timeout=30)
    sftp = cli.open_sftp()

    # 1. 上传 PHP 控制器（先备份）
    for local, remote in PHP_FILES:
        with open(local, "rb") as f:
            data = f.read()
        bak = remote + ".bak_" + STAMP
        try:
            with sftp.open(remote, "rb") as f:
                old = f.read()
            with sftp.open(bak, "wb") as f:
                f.write(old)
            print(f"[backup] {bak} ({len(old)} bytes)")
        except Exception as e:
            print("[backup skipped]", e)
        with sftp.open(remote, "wb") as f:
            f.write(data)
        print(f"[upload] {remote} ({len(data)} bytes)")

    if not php_only:
        # 2. 上传 dist 压缩包
        sftp.put(LOCAL_ZIP, REMOTE_ZIP)
        print(f"[upload] {REMOTE_ZIP}")

    sftp.close()

    if not php_only:
        # 3. 解压到新目录后原子替换（避免线上出现空档）
        run(cli,
            f"cd {REMOTE_ADMIN} && rm -rf dist_new && "
            f"unzip -o {REMOTE_ZIP} -d dist_new >/dev/null && "
            f"test -f dist_new/index.html && echo UNZIP_OK && "
            f"mv dist dist.bak.{STAMP} && mv dist_new dist && "
            f"chown -R www:www dist && echo SWAP_OK",
            "解压并替换 admin/dist")

        # 4. 校验
        run(cli, f"ls -la {REMOTE_ADMIN}/dist/ && ls {REMOTE_ADMIN}/dist/assets/liveUser-*.js", "校验 dist")

    run(cli, f"/www/server/php/83/bin/php -l {PHP_FILES[0][1]} 2>&1", "校验 PHP 语法")

    # 5. 重载 PHP-FPM（清 opcache 使新控制器立即生效）
    run(cli,
        "if [ -f /etc/init.d/php-fpm-83 ]; then /etc/init.d/php-fpm-83 reload && echo FPM_RELOAD_OK; "
        "else echo NO_FPM_INIT; fi",
        "重载 PHP-FPM")

    cli.close()
    print("DONE")
    return 0


if __name__ == "__main__":
    sys.exit(main())
