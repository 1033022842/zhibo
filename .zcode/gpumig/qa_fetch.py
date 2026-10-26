#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""把 QA 产物（视频 + 对照图）从 GPU 节点拉到本机，并生成验收报告。

本机运行。产物目录：D:\\ever\\douyin\\qa_out\\

用法：
  python .zcode/gpumig/qa_fetch.py            # 拉全部已完成
  python .zcode/gpumig/qa_fetch.py --report   # 只重新生成报告
"""
import argparse
import json
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from gpu import connect  # .zcode/gpu.py，复用现成的跳板连接（含重试）

LOCAL = r"D:\ever\douyin\qa_out"
REMOTE = "/opt/cv/short/qa"


def read_results(cli):
    _, o, _ = cli.exec_command("cat %s/results.jsonl 2>/dev/null" % REMOTE, timeout=60)
    rows = []
    for line in o.read().decode("utf-8", "replace").splitlines():
        line = line.strip()
        if not line:
            continue
        try:
            rows.append(json.loads(line))
        except json.JSONDecodeError:
            pass
    # 同一 code 可能有多条（重跑），保留最后一条
    latest = {}
    for r in rows:
        latest[r["code"]] = r
    return [latest[k] for k in sorted(latest)]


def fetch(cli, results):
    vdir = os.path.join(LOCAL, "videos")
    sdir = os.path.join(LOCAL, "sheets")
    os.makedirs(vdir, exist_ok=True)
    os.makedirs(sdir, exist_ok=True)
    sftp = cli.open_sftp()
    got_v = got_s = 0
    for r in results:
        for key, dest in (("file", vdir), ("sheet", sdir)):
            remote = r.get(key) or ""
            if not remote:
                continue
            name = os.path.basename(remote)
            local = os.path.join(dest, name)
            try:
                rsize = sftp.stat(remote).st_size
            except IOError:
                print("  缺失:", remote)
                continue
            if os.path.exists(local) and os.path.getsize(local) == rsize:
                if key == "file":
                    got_v += 1
                else:
                    got_s += 1
                continue
            sftp.get(remote, local)
            if key == "file":
                got_v += 1
            else:
                got_s += 1
            print("  下载 %-34s %8.2f MB" % (name, rsize / 1024 / 1024))
    sftp.close()
    return got_v, got_s


def build_report(results):
    total = len(results)
    ok = [r for r in results if r.get("ok")]
    bad = [r for r in results if not r.get("ok")]

    def fmt_dur(r):
        p = r.get("probe") or {}
        d = p.get("duration") or 0
        return "%.2fs" % d if d else "-"

    lines = []
    W = lines.append
    W("# 50 条预设验收报告")
    W("")
    W("> 生成时间：%s" % __import__("time").strftime("%Y-%m-%d %H:%M:%S"))
    W("> 产物目录：`%s`（视频 `videos/`，关键帧对照图 `sheets/`）" % LOCAL)
    W("")
    W("## 总览")
    W("")
    W("| 项 | 数量 |")
    W("|---|---|")
    W("| 已跑 | %d / 50 |" % total)
    W("| 自动通过 | %d |" % len(ok))
    W("| 有问题 | %d |" % len(bad))
    W("")
    if bad:
        W("### 有问题的条目")
        W("")
        W("| code | 标题 | 问题 |")
        W("|---|---|---|")
        for r in bad:
            W("| %s | %s | %s |" % (r["code"], r["title"], r.get("note", "")))
        W("")

    W("## 逐条明细")
    W("")
    W("| code | 标题 | 分类 | 时长 | 分辨率 | 音轨 | 亮度峰值 | 动作幅度 | 耗时 | 判定 | 对照图 |")
    W("|---|---|---|---|---|---|---|---|---|---|---|")
    for r in results:
        p = r.get("probe") or {}
        wh = "%dx%d" % (p.get("width", 0), p.get("height", 0)) if p.get("width") else "-"
        luma = r.get("luma_peak")
        motion = r.get("motion")
        sheet = os.path.basename(r.get("sheet") or "")
        verdict = "通过" if r.get("ok") else "**问题**"
        W("| %s | %s | %s | %s | %s | %s | %s | %s | %ss | %s | %s |" % (
            r["code"], r["title"][:34], r.get("kind", ""),
            fmt_dur(r), wh,
            "有" if p.get("has_audio") else ("无" if p else "-"),
            "-" if luma is None else "%.0f" % luma,
            "-" if motion is None else "%.1f" % motion,
            int(r.get("total_elapsed") or 0),
            verdict,
            ("[图](sheets/%s)" % sheet) if sheet else "-",
        ))
    W("")
    W("## 判据说明")
    W("")
    W("| 判据 | 阈值 | 含义 |")
    W("|---|---|---|")
    W("| 出片 | 文件存在且可解码、时长 ≥ 60% 目标 | 生成是否成功 |")
    W("| 全黑 | 6 个采样帧亮度峰值 ≤ 8 | 生成失败（与 worker 同一判据） |")
    W("| 动作幅度 | 相邻采样帧平均绝对差 ≥ 1.5 | 低于此值说明画面几乎静止，通常意味着该条 LoRA/提示词没生效 |")
    W("| 动作相符 | **需人工**看对照图 | 本机无可用视觉模型，无法自动判定语义是否匹配标题 |")
    W("")
    W("> **重要**：「动作幅度」只能说明画面在动，不能证明动作与标题相符。")
    W("> 语义是否符合，请打开 `sheets/<code>.jpg`（2x2 关键帧）对照标题人工确认。")
    W("")

    # 动作幅度排序，方便挑出最可能是「没生效」的
    vids = [r for r in results if r.get("motion") is not None]
    if vids:
        W("## 动作幅度排序（低 → 高，越低越可疑）")
        W("")
        W("| 排名 | code | 标题 | 动作幅度 |")
        W("|---|---|---|---|")
        for i, r in enumerate(sorted(vids, key=lambda x: x["motion"])[:15], 1):
            W("| %d | %s | %s | %.2f |" % (i, r["code"], r["title"][:36], r["motion"]))
        W("")

    path = os.path.join(LOCAL, "REPORT.md")
    with open(path, "w", encoding="utf-8", newline="\n") as f:
        f.write("\n".join(lines) + "\n")
    return path


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--report", action="store_true", help="只生成报告，不下载")
    a = ap.parse_args()
    os.makedirs(LOCAL, exist_ok=True)

    cli = connect()
    try:
        results = read_results(cli)
        print("已完成 %d 条" % len(results))
        if not results:
            print("还没有结果")
            return
        if not a.report:
            v, s = fetch(cli, results)
            print("已同步 视频 %d / 对照图 %d" % (v, s))
        path = build_report(results)
        print("报告:", path)
    finally:
        cli.close()


if __name__ == "__main__":
    main()
