#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""修复 shell-new.js 的 userInfo 无限循环（四站通用，保留各站品牌特化）。

线上故障（2026-10-22）：
  用户提交 Image 页 customVideo/submit 一直无响应，同时页面疯狂重复请求
  /api/live/userInfo。实测 sugus 单日 userInfo 53,613 次，heygirl 37,913 次
  （占该站全部请求的 88%），kisss 15,901 次（67%）。

根因：
  shell-new.js 里装了全局 fetch 钩子——任何 POST /api/live/* 完成后 300ms
  调用 refreshTokenBalance()；而 refreshTokenBalance() 本身就是
  POST /api/live/userInfo，于是「userInfo 完成 → 触发刷新 → 再发 userInfo」
  无限循环，每个标签页约 3.3 次/秒。数个标签页即可吃满 PHP-FPM 的 40 个
  worker（同时 AI 聊天请求还会阻塞在外部 LLM 上最长 120s），
  导致 customVideo/submit 排不到 worker，浏览器表现为「一直没响应」。

修法（两层）：
  1. fetch 钩子排除 userInfo 自身
  2. refreshTokenBalance 加单飞（inflight）保护

用法：
  python .zcode/fix_userinfo_loop.py --check    # 只读检查四站是否仍有该 bug
  python .zcode/fix_userinfo_loop.py --apply    # 就地修复（保留品牌差异）
"""
import argparse
import hashlib
import io
import re
import sys

import paramiko

SITES = {
    "sugus":   ("172.81.98.55", "0wJfy`50tJQzG/0X"),
    "heygirl": ("172.81.99.11", "mw-DGckAL,61fV00"),
    "kisss":   ("103.217.252.52", "Aa1033022842"),
    "kelymi":  ("172.81.99.42", "ffG2dAUGc{V572{j"),
}
ORDER = ["sugus", "heygirl", "kisss", "kelymi"]
REMOTE = "/www/wwwroot/douyin/ai-girl-malaysia.com/js/shell-new.js"

# ── 补丁 1：fetch 钩子排除 userInfo 自身 ─────────────────────────
OLD_HOOK_IF = "        if (/\\/api\\/live\\//.test(u)) {"
NEW_HOOK_IF = (
    "        // 必须排除 userInfo 自身：refreshTokenBalance() 就是 POST /api/live/userInfo，\n"
    "        // 不排除会「userInfo 完成 -> 触发刷新 -> 再发 userInfo」无限循环，\n"
    "        // 每个标签页 300ms 一次，数个标签页即可吃满 PHP-FPM worker（2026-10-22 线上事故）\n"
    "        if (/\\/api\\/live\\//.test(u) && !/\\/api\\/live\\/userInfo(\\?|$)/.test(u)) {"
)

# ── 补丁 2：refreshTokenBalance 单飞 ─────────────────────────────
OLD_REFRESH_HEAD = """  window.refreshTokenBalance = function () {
    var tk = null
    try { tk = localStorage.getItem('live_access_token') } catch (e) { return }
    if (!tk) return
    fetch('/api/live/userInfo', { method: 'POST', headers: { token: tk } })"""
NEW_REFRESH_HEAD = """  window.refreshTokenBalance = function () {
    var tk = null
    try { tk = localStorage.getItem('live_access_token') } catch (e) { return }
    if (!tk) return
    // 单飞：上一次请求未回来前不重复发，任何调用方都不会造成并发风暴
    if (window.__tokenBalanceInflight) return
    window.__tokenBalanceInflight = true
    var __tbDone = function () { window.__tokenBalanceInflight = false }
    fetch('/api/live/userInfo', { method: 'POST', headers: { token: tk } })"""

OLD_REFRESH_TAIL = """      })
      .catch(function () {})
  }

  // 站内 API（/api/live/）请求完成后自动刷新顶栏余额"""
NEW_REFRESH_TAIL = """      })
      .catch(function () {})
      .then(__tbDone, __tbDone)
  }

  // 站内 API（/api/live/）请求完成后自动刷新顶栏余额"""


def connect(host, pw, timeout=30):
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect(host, 22, "root", pw, timeout=timeout, banner_timeout=timeout,
              auth_timeout=timeout, look_for_keys=False, allow_agent=False)
    return c


def run(c, cmd, t=120):
    stdin, out, err = c.exec_command(cmd, timeout=t)
    o = out.read().decode("utf-8", "replace")
    e = err.read().decode("utf-8", "replace")
    return out.channel.recv_exit_status(), o.strip(), e.strip()


def patched(text):
    """是否已修复（两个补丁都在）"""
    return ("!/\\/api\\/live\\/userInfo" in text) and ("__tokenBalanceInflight" in text)


def apply_patch(text):
    """返回 (新文本, 说明)。只做精确替换，锚点不匹配就报错，绝不盲改。"""
    notes = []
    t = text

    if "!/\\/api\\/live\\/userInfo" not in t:
        if OLD_HOOK_IF not in t:
            raise RuntimeError("hook 锚点未找到，放弃（避免误改）")
        t = t.replace(OLD_HOOK_IF, NEW_HOOK_IF, 1)
        notes.append("hook 排除 userInfo")
    else:
        notes.append("hook 已修")

    if "__tokenBalanceInflight" not in t:
        if OLD_REFRESH_HEAD not in t:
            raise RuntimeError("refresh 头部锚点未找到，放弃")
        if OLD_REFRESH_TAIL not in t:
            raise RuntimeError("refresh 尾部锚点未找到，放弃")
        t = t.replace(OLD_REFRESH_HEAD, NEW_REFRESH_HEAD, 1)
        t = t.replace(OLD_REFRESH_TAIL, NEW_REFRESH_TAIL, 1)
        notes.append("refresh 加单飞")
    else:
        notes.append("单飞已在")

    return t, notes


def brand_fingerprint(text):
    """抽取品牌相关行做前后比对，确保补丁没破坏站点特化"""
    keys = []
    for pat in (r"mailto:[^'\"]+", r"https://[a-z0-9.\-]+\.b-cdn\.net/ai/",
                r"localStorage\.getItem\('[a-z]+\.chat_price'\)",
                r"Important notifications from [^.<]+"):
        keys += re.findall(pat, text)
    return sorted(set(keys))


def check_site(name, verbose=True):
    host, pw = SITES[name]
    info = {"site": name}
    try:
        c = connect(host, pw)
    except Exception as ex:
        info["error"] = str(ex)
        return info
    try:
        sftp = c.open_sftp()
        raw = sftp.open(REMOTE, "rb").read()
        sftp.close()
        text = raw.replace(b"\r\n", b"\n").decode("utf-8", "replace")
        info["size"] = len(raw)
        info["fixed"] = patched(text)
        # 今日 userInfo 量（各站 access log 名不同，取最大的那个）
        rc, o, _ = run(c, "grep -c 'userInfo' /var/log/nginx/*access*.log 2>/dev/null | "
                          "awk -F: '{s+=$2} END{print s+0}'", 90)
        info["userinfo_today"] = int(o or 0)
        rc, o, _ = run(c, "grep -c '' /var/log/nginx/*access*.log 2>/dev/null | "
                          "awk -F: '{s+=$2} END{print s+0}'", 90)
        info["req_total"] = int(o or 0)
    finally:
        c.close()

    if verbose:
        pct = (100.0 * info["userinfo_today"] / info["req_total"]) if info.get("req_total") else 0
        print("[%-8s] %-15s 代码:%s  userInfo今日=%d / 总请求=%d (%.0f%%)"
              % (name, host,
                 "✅已修" if info["fixed"] else "❌有死循环",
                 info["userinfo_today"], info["req_total"], pct))
    return info


def apply_site(name):
    host, pw = SITES[name]
    c = connect(host, pw)
    try:
        sftp = c.open_sftp()
        raw = sftp.open(REMOTE, "rb").read()
        crlf = b"\r\n" in raw
        text = raw.replace(b"\r\n", b"\n").decode("utf-8", "replace")

        if patched(text):
            print("  [%s] 已修复，跳过" % name)
            return True

        before_brand = brand_fingerprint(text)
        new_text, notes = apply_patch(text)
        after_brand = brand_fingerprint(new_text)
        if before_brand != after_brand:
            print("  [%s] ❌ 品牌指纹变化，放弃写入" % name)
            print("     before:", before_brand)
            print("     after :", after_brand)
            return False

        # 备份
        stamp = __import__("time").strftime("%Y%m%d%H%M%S")
        run(c, "cp -a %s %s.bak.userinfo.%s" % (REMOTE, REMOTE, stamp), 60)

        out = new_text.replace("\n", "\r\n").encode("utf-8") if crlf else new_text.encode("utf-8")
        with sftp.open(REMOTE, "wb") as f:
            f.write(out)
        sftp.close()

        # 复核：读回来确认补丁在、字节数合理
        sftp = c.open_sftp()
        back = sftp.open(REMOTE, "rb").read().replace(b"\r\n", b"\n").decode("utf-8", "replace")
        sftp.close()
        ok = patched(back)
        print("  [%s] %s  %s  大小 %d → %d B  (brand 一致)"
              % (name, "✅" if ok else "❌", " / ".join(notes), len(raw), len(out)))
        if not ok:
            return False

        # nginx/opcache 无需动（静态文件）；确认 Nginx 能正常返回
        rc, code, _ = run(c, "curl -sk -o /dev/null -w '%%{http_code} %%{size_download}' --max-time 15 "
                             "https://127.0.0.1/js/shell-new.js -H 'Host: localhost'", 40)
        print("     本机自取: %s" % code)
        return True
    finally:
        c.close()


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--check", action="store_true")
    ap.add_argument("--apply", action="store_true")
    ap.add_argument("--site", choices=list(SITES))
    args = ap.parse_args()
    targets = [args.site] if args.site else ORDER

    print("shell-new.js userInfo 死循环 · 四站体检")
    print("-" * 74)
    if args.apply:
        results = {}
        for s in targets:
            print("=" * 74)
            print("[%s] %s" % (s, SITES[s][0]))
            try:
                results[s] = apply_site(s)
            except Exception as ex:
                print("  ❌ 失败: %s" % ex)
                results[s] = False
        print("=" * 74)
        for s in targets:
            print("  %-8s %s" % (s, "✅ 已修" if results[s] else "❌ 未完成"))
        return 0 if all(results.values()) else 1

    infos = [check_site(s) for s in targets]
    print("-" * 74)
    bad = [i["site"] for i in infos if not i.get("fixed") or i.get("error")]
    if bad:
        print("仍有死循环：%s" % ", ".join(bad))
        return 1
    print("四站均已修复")
    return 0


if __name__ == "__main__":
    sys.exit(main())
