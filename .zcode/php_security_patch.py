#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""四站 PHP Critical 漏洞（USN-8948-1/-2）修补工具。

CVE-2026-109618 / CVE-2026-109619 均为 Critical 级 RCE，修复版本：
  - Ubuntu 24.04 (noble)  php8.3  8.3.6-0ubuntu0.24.04.13
  - ondrej PPA (jammy)    php8.3  8.3.36-1+ubuntu22.04.1+deb.sury.org+1

用法：
  python .zcode/php_security_patch.py --check              # 只读体检（默认）
  python .zcode/php_security_patch.py --check --site kisss # 只查一台
  python .zcode/php_security_patch.py --apply --site kisss # 升级一台
  python .zcode/php_security_patch.py --apply              # 按 kisss→kelymi→heygirl→sugus 顺序全升
  python .zcode/php_security_patch.py --rollback --site sugus

安全设计：
  - 24.04 三台缺 noble-security 源，脚本自动补（单独文件，不动 ubuntu.sources）
  - HK 镜像的 noble-updates 只到 .12（仍有洞），所以必须从 security 源取 .13
  - 升级前记录每个 php8.3-* 包的精确版本，失败可逐包精确回滚
  - reload 而非 restart（不断连接）；升级后服务不健康自动回滚
  - php.ini 不受 dpkg 托管 → 我们改的 512M 上传限制不会被覆盖
"""
import argparse
import sys
import time

import paramiko

SITES = {
    # name: (host, password, expected_distro_kind)
    "kisss":   ("103.217.252.52", "Aa1033022842", "ondrej"),
    "kelymi":  ("172.81.99.42", "ffG2dAUGc{V572{j", "noble"),
    "heygirl": ("172.81.99.11", "mw-DGckAL,61fV00", "noble"),
    "sugus":   ("172.81.98.55", "0wJfy`50tJQzG/0X", "noble"),
}
# 执行顺序：流量小的先来，主力站放最后
ORDER = ["kisss", "kelymi", "heygirl", "sugus"]

FIXED = {
    "noble": "8.3.6-0ubuntu0.24.04.13",
    # ondrej PPA 与 packages.sury.org 的版本号后缀不同，但都 ≥ 8.3.36 即可
    "ondrej": "8.3.36",
}

# 冒烟用公网域名：kisss 的 IP 直连是 default_server{return 404}，必须走域名
DOMAINS = {
    "sugus": "https://www.sugus.ai",
    "heygirl": "https://heygirl.live",
    "kisss": "https://kisss.ai",
    "kelymi": "https://kelymi.autos",
}

SEC_SOURCE = """Types: deb
URIs: http://security.ubuntu.com/ubuntu
Suites: noble-security
Components: main universe
Signed-By: /usr/share/keyrings/ubuntu-archive-keyring.gpg
"""
SEC_FILE = "/etc/apt/sources.list.d/ubuntu-security.sources"


def connect(host, pw, timeout=30):
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect(host, 22, "root", pw, timeout=timeout,
              banner_timeout=timeout, auth_timeout=timeout,
              look_for_keys=False, allow_agent=False)
    return c


def sh(c, cmd, t=180):
    _, o, e = c.exec_command(cmd, timeout=t)
    out = o.read().decode("utf-8", "replace")
    err = e.read().decode("utf-8", "replace")
    return o.channel.returncode if hasattr(o.channel, "returncode") else 0, out.strip(), err.strip()


def run(c, cmd, t=180):
    stdin, stdout, stderr = c.exec_command(cmd, timeout=t)
    out = stdout.read().decode("utf-8", "replace")
    err = stderr.read().decode("utf-8", "replace")
    return stdout.channel.recv_exit_status(), out.strip(), err.strip()


def installed_version(c):
    rc, o, _ = run(c, "dpkg-query -W -f='${Version}' php8.3-fpm 2>/dev/null")
    return o


def ge(a, b):
    """dpkg 版本比较：a >= b ?（在远端执行）"""
    return a


def check_site(name, verbose=True):
    host, pw, kind = SITES[name]
    info = {"site": name, "host": host, "kind": kind, "problems": []}
    try:
        c = connect(host, pw)
    except Exception as ex:
        info["error"] = "连接失败: %s" % ex
        return info
    try:
        rc, ver, _ = run(c, "dpkg-query -W -f='${Version}' php8.3-fpm 2>/dev/null")
        info["installed"] = ver
        rc, phpv, _ = run(c, "php -v 2>/dev/null | head -1")
        info["php"] = phpv
        rc, st, _ = run(c, "systemctl is-active php8.3-fpm")
        info["fpm"] = st

        # 是否已修
        rc, cmp, _ = run(c, "dpkg --compare-versions '%s' ge '%s' && echo YES || echo NO"
                         % (ver, FIXED[kind]))
        info["patched"] = (cmp == "YES")

        # 源体检（noble）
        if kind == "noble":
            rc, srcs, _ = run(c, "grep -hvE '^\\s*#|^\\s*$' /etc/apt/sources.list.d/*.sources 2>/dev/null | grep -i '^Suites:' | tr '\\n' ' '")
            info["suites"] = srcs
            info["has_security"] = "noble-security" in srcs

        # 可升级候选
        rc, cand, _ = run(c, "apt-cache policy php8.3-fpm 2>/dev/null | awk '/Candidate:/{print $2}'")
        info["candidate"] = cand
        if cand and not info["patched"]:
            rc, ok, _ = run(c, "dpkg --compare-versions '%s' ge '%s' && echo YES || echo NO"
                            % (cand, FIXED[kind]))
            info["candidate_fixed"] = (ok == "YES")

        if not info["patched"]:
            info["problems"].append("PHP 未打补丁（%s < %s）" % (ver, FIXED[kind]))
        if kind == "noble" and not info.get("has_security"):
            info["problems"].append("apt 源缺 noble-security（直接 upgrade 只会到 .12，仍有洞）")
        if kind == "noble" and info.get("candidate_fixed") is False:
            info["problems"].append("当前候选 %s 仍低于修复版本" % cand)
        if info["fpm"] != "active":
            info["problems"].append("php8.3-fpm 未运行（%s）" % info["fpm"])
    finally:
        c.close()

    if verbose:
        print("-" * 62)
        print("[%s] %s  (%s)" % (name, host, kind))
        print("  PHP        : %s" % info.get("php", "?"))
        print("  php8.3-fpm : %s  [%s]" % (info.get("installed", "?"), info.get("fpm", "?")))
        print("  修复版本   : %s" % FIXED[kind])
        print("  状态       : %s" % ("✅ 已修补" if info.get("patched") else "❌ 存在漏洞"))
        if kind == "noble":
            print("  apt 源     : %s" % info.get("suites", "?"))
        print("  候选版本   : %s" % info.get("candidate", "?"))
        if info.get("problems"):
            for p in info["problems"]:
                print("  ⚠ %s" % p)
    return info


def apply_site(name):
    host, pw, kind = SITES[name]
    stamp = time.strftime("%Y%m%d%H%M%S")
    bak = "/root/php_sec_%s_%s" % (name, stamp)
    c = connect(host, pw)
    try:
        print("=" * 62)
        print("[%s] %s  开始升级" % (name, host))

        # 0) 备份
        rc, o, e = run(c, "mkdir -p %s && cp -a /etc/php/8.3/fpm/php.ini %s/ 2>/dev/null; "
                          "cp -a /etc/php/8.3/fpm/pool.d/www.conf %s/ 2>/dev/null; "
                          "dpkg-query -W -f='${Package} ${Version}\\n' 'php8.3-*' | grep -v ':' > %s/versions.txt; "
                          "wc -l < %s/versions.txt" % (bak, bak, bak, bak, bak), 120)
        print("  备份已存 %s（记录 %s 个包版本）" % (bak, o))

        before = installed_version(c)

        # 1) noble：补 security 源
        if kind == "noble":
            rc, srcs, _ = run(c, "grep -l 'noble-security' /etc/apt/sources.list.d/*.sources 2>/dev/null | head -1")
            if not srcs:
                sftp = c.open_sftp()
                with sftp.open(SEC_FILE, "w") as f:
                    f.write(SEC_SOURCE)
                sftp.chmod(SEC_FILE, 0o644)
                sftp.close()
                print("  已补 %s" % SEC_FILE)
            else:
                print("  apt 源已含 noble-security（%s）" % srcs)

        # 2) 刷新索引
        rc, o, e = run(c, "DEBIAN_FRONTEND=noninteractive apt-get update -qq 2>&1 | tail -3", 300)
        if o:
            print("  apt update: %s" % o.replace("\n", " | ")[:160])

        # 3) 检查候选版本
        rc, cand, _ = run(c, "apt-cache policy php8.3-fpm 2>/dev/null | awk '/Candidate:/{print $2}'")
        rc, ok, _ = run(c, "dpkg --compare-versions '%s' ge '%s' && echo YES || echo NO" % (cand, FIXED[kind]))
        if ok != "YES":
            print("  ❌ 中止：候选版本 %s 低于修复版本 %s" % (cand, FIXED[kind]))
            print("     可能是镜像尚未同步，稍后重试或换镜像")
            return False
        print("  候选版本 %s ≥ 修复版本 %s ✓" % (cand, FIXED[kind]))

        # 4) 升级
        rc, o, e = run(c, "DEBIAN_FRONTEND=noninteractive apt-get install -y -qq "
                          "-o Dpkg::Options::=--force-confold --only-upgrade 'php8.3-*' 2>&1 | tail -6", 600)
        print("  升级输出: %s" % (o.replace("\n", " | ")[:300] or e[:300]))

        after = installed_version(c)
        rc, ok, _ = run(c, "dpkg --compare-versions '%s' ge '%s' && echo YES || echo NO" % (after, FIXED[kind]))
        print("  版本: %s → %s  %s" % (before, after, "✓ 已修补" if ok == "YES" else "❌ 仍未修补"))

        # 5) 确认 php.ini 上传限制没被动
        rc, ini, _ = run(c, "grep -E '^(upload_max_filesize|post_max_size|memory_limit|max_execution_time)' "
                            "/etc/php/8.3/fpm/php.ini | tr '\\n' ' '")
        print("  php.ini 仍为: %s" % ini)

        # 6) reload
        rc, o, e = run(c, "systemctl reload php8.3-fpm && sleep 2 && systemctl is-active php8.3-fpm", 120)
        print("  php8.3-fpm reload → %s" % o)

        # 7) 冒烟（走公网域名：kisss 的 IP 直连是 default_server{return 404}）
        dom = DOMAINS[name]
        smoke = [
            ("首页", "curl -sk -o /dev/null -w '%{http_code}' --max-time 20 %s/ai/Home.html" % dom),
            ("Image页", "curl -sk -o /dev/null -w '%{http_code}' --max-time 20 %s/ai/Image.html" % dom),
            ("PHP+DB", "curl -sk -o /dev/null -w '%{http_code}' --max-time 20 "
                       "-H 'X-Api-Key: live-ai-api-key-2026' %s/api/customVideo/stats" % dom),
        ]
        healthy = True
        for label, cmd in smoke:
            rc, code, _ = run(c, cmd, 40)
            print("  冒烟 %-10s %s" % (label, code))
            if code not in ("200", "302"):
                healthy = False

        if ok != "YES" or not healthy:
            print("  ❌ 结果不达标，执行自动回滚")
            rollback_site(name, bak, c=c, close=False)
            return False

        print("  ✅ %s 完成" % name)
        return True
    finally:
        c.close()


def rollback_site(name, bak=None, c=None, close=True):
    host, pw, kind = SITES[name]
    own = c is None
    if own:
        c = connect(host, pw)
    try:
        if not bak:
            rc, o, _ = run(c, "ls -dt /root/php_sec_%s_* 2>/dev/null | head -1" % name)
            bak = o
        if not bak:
            print("  找不到备份目录，无法回滚")
            return False
        rc, o, _ = run(c, "cat %s/versions.txt" % bak)
        if not o:
            print("  备份里没有 versions.txt")
            return False
        args = " ".join("%s=%s" % (line.split()[0], line.split()[1])
                        for line in o.splitlines() if len(line.split()) == 2)
        print("  回滚到: %s" % bak)
        rc, out, err = run(c, "DEBIAN_FRONTEND=noninteractive apt-get install -y -qq "
                              "--allow-downgrades -o Dpkg::Options::=--force-confold %s 2>&1 | tail -4" % args, 600)
        print("  %s" % (out.replace("\n", " | ")[:300] or err[:300]))
        rc, ver, _ = run(c, "dpkg-query -W -f='${Version}' php8.3-fpm")
        run(c, "systemctl reload php8.3-fpm", 120)
        print("  回滚后版本: %s" % ver)
        return True
    finally:
        if own:
            c.close()


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--check", action="store_true", help="只读体检（默认）")
    ap.add_argument("--apply", action="store_true", help="执行升级")
    ap.add_argument("--rollback", action="store_true", help="回滚到上次升级前")
    ap.add_argument("--site", choices=list(SITES), help="只处理指定站点")
    args = ap.parse_args()

    targets = [args.site] if args.site else ORDER

    if args.apply and not args.check:
        print("修复版本要求：noble=%s  ondrej=%s" % (FIXED["noble"], FIXED["ondrej"]))
        results = {}
        for s in targets:
            results[s] = apply_site(s)
            print()
        print("=" * 62)
        print("汇总：")
        for s in targets:
            print("  %-8s %s" % (s, "✅ 完成" if results[s] else "❌ 未完成/已回滚"))
        return 0 if all(results.values()) else 1

    if args.rollback:
        for s in targets:
            rollback_site(s)
        return 0

    # 默认：只读体检
    print("USN-8948-1/-2  PHP Critical RCE 修复检查（只读，不改任何东西）")
    print("修复版本：Ubuntu 24.04 → %s    ondrej PPA → %s" % (FIXED["noble"], FIXED["ondrej"]))
    infos = [check_site(s) for s in targets]
    print("-" * 62)
    bad = [i for i in infos if not i.get("patched") or i.get("error")]
    if bad:
        print("需要处理：%s" % ", ".join(i["site"] for i in bad))
        return 1
    print("四台均已修补")
    return 0


if __name__ == "__main__":
    sys.exit(main())
