#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""50 条预设批量验收（QA）—— 在 GPU 节点上跑。

设计
----
* 复用 worker_short.build_workflow 构造工作流（与实际生产完全同源），
  但直接提交 ComfyUI，跳过「API 队列 + 60s 软限流」这一跳，
  这样 50 条不用等 9 小时。
* 每条自动产出三个判据：
    1. 出片    —— 文件存在、可解码、时长/分辨率正确
    2. 黑屏    —— 三帧亮度峰值（复用 worker 的判据）
    3. 动作幅度 —— 相邻帧平均绝对差（motion energy）。数值过低说明
                  「画面几乎不动」，通常意味着该条 LoRA/提示词没生效。
* 语义层面的「动作是否相符」无法自动判定（本机无可用 VLM），
  故额外生成 2x2 关键帧对照图，交给人工看。
* 断点续跑：已完成的 preset 跳过（结果写 results.jsonl），
  可按 --batch 分批。

用法
----
  python qa_50.py --batch 1      # 第 1 批（gs-01..gs-10）
  python qa_50.py --only gs-17   # 单条
  python qa_50.py --list         # 只打印计划
"""
import argparse
import json
import os
import subprocess
import sys
import time
from pathlib import Path

os.environ.setdefault("SHORT_ROOT", "/opt/cv/short/sugus")
os.environ.setdefault("SHORT_TAG", "sugus")
sys.path.insert(0, "/opt/cv/short")

import worker_short as W   # noqa: E402

QA_DIR = Path("/opt/cv/short/qa")
OUT_DIR = QA_DIR / "videos"
SHEET_DIR = QA_DIR / "sheets"
RESULT = QA_DIR / "results.jsonl"
LOG = QA_DIR / "qa.log"

# 每条抽帧做对照图的时刻（占时长比例）
SHEET_FRACS = (0.15, 0.40, 0.65, 0.90)
# 动作幅度下限：低于此值判定「几乎静止」
MOTION_MIN = 1.5
# 亮度峰值下限（与 worker assert 一致）
LUMA_MIN = 8


def log(msg):
    line = time.strftime("%Y-%m-%d %H:%M:%S ") + str(msg)
    print(line, flush=True)
    QA_DIR.mkdir(parents=True, exist_ok=True)
    with open(LOG, "a", encoding="utf-8") as f:
        f.write(line + "\n")


# ── 预设来源：从后端 API 取（GPU 节点没有 php/数据库） ───────────────
API_BASE = os.environ.get("SHORT_API_BASE", "https://sugus.ai/api/aiShort")
API_KEY = os.environ.get("SHORT_KEY", "live-ai-api-key-2026")


def load_presets():
    """从后端 /export 取全量（含 prompt / lora_chain），X-Api-Key 鉴权。"""
    import requests
    r = requests.get(API_BASE + "/export",
                     headers={"X-Api-Key": API_KEY, "X-Worker-Id": "qa-50"},
                     timeout=120)
    r.raise_for_status()
    body = r.json()
    if str(body.get("code")) != "00000":
        raise RuntimeError("拉取预设失败: %s" % body.get("msg"))
    return body["data"]


def load_ref_image(code):
    """参考图：用预设封面（/ai/media/gs/xx.jpg），落到 ComfyUI/input。"""
    num = int(code.split("-")[1])
    src = W.COMFY_BASE / "input" / ("gs_ref_%02d.jpg" % num)
    if src.exists() and src.stat().st_size > 1024:
        return src.name
    url = "https://sugus.ai/ai/media/gs/%02d.jpg" % num
    import requests
    r = requests.get(url, timeout=120)
    r.raise_for_status()
    src.write_bytes(r.content)
    if src.stat().st_size < 1024:
        raise RuntimeError("参考图过小: %s" % url)
    return src.name


def _as_chain(v):
    """lora_chain 可能已是数组（/export 返回）或 JSON 字符串（库原样），两种都兼容。"""
    if not v:
        return []
    if isinstance(v, list):
        return v
    try:
        return json.loads(v)
    except (TypeError, json.JSONDecodeError):
        return []


# ── 判据 ────────────────────────────────────────────────────────────
def probe(path):
    """时长 / 宽高 / 帧数"""
    r = subprocess.run(
        ["ffprobe", "-v", "quiet", "-print_format", "json",
         "-show_format", "-show_streams", str(path)],
        capture_output=True, text=True)
    try:
        j = json.loads(r.stdout or "{}")
    except json.JSONDecodeError:
        return {}
    v = next((s for s in j.get("streams", []) if s.get("codec_type") == "video"), {})
    return {
        "duration": float(j.get("format", {}).get("duration") or 0),
        "width": int(v.get("width") or 0),
        "height": int(v.get("height") or 0),
        "nb_frames": int(v.get("nb_frames") or 0),
        "has_audio": any(s.get("codec_type") == "audio" for s in j.get("streams", [])),
        "size": os.path.getsize(path),
    }


def frame_metrics(path, duration):
    """返回 (亮度峰值, 动作幅度)。动作幅度 = 相邻采样帧的平均绝对差。"""
    from PIL import Image
    tmp = Path("/tmp/_qa_frames")
    tmp.mkdir(exist_ok=True)
    # 取 6 帧用于动作幅度
    n = 6
    peaks, prev, diffs = [], None, []
    for i in range(n):
        t = max(0.0, duration * (i / max(1, n - 1)) * 0.95)
        fp = tmp / ("f_%d.jpg" % i)
        subprocess.run(["ffmpeg", "-y", "-v", "quiet", "-ss", "%.2f" % t, "-i", str(path),
                        "-vframes", "1", "-vf", "scale=160:-2", "-q:v", "3", str(fp)],
                       capture_output=True)
        if not fp.exists() or fp.stat().st_size == 0:
            continue
        im = Image.open(fp).convert("L")
        px = list(im.getdata())
        peaks.append(max(px))
        if prev is not None:
            diffs.append(sum(abs(a - b) for a, b in zip(px, prev)) / len(px))
        prev = px
        fp.unlink(missing_ok=True)
    return (max(peaks) if peaks else 0), (sum(diffs) / len(diffs) if diffs else 0.0)


def contact_sheet(path, code, duration):
    """2x2 关键帧对照图，供人工核对动作。"""
    SHEET_DIR.mkdir(parents=True, exist_ok=True)
    tmp = Path("/tmp/_qa_sheet")
    tmp.mkdir(exist_ok=True)
    imgs = []
    for i, frac in enumerate(SHEET_FRACS):
        t = max(0.0, duration * frac)
        fp = tmp / ("s%d.jpg" % i)
        subprocess.run(["ffmpeg", "-y", "-v", "quiet", "-ss", "%.2f" % t, "-i", str(path),
                        "-vframes", "1", "-vf", "scale=360:-2", "-q:v", "3", str(fp)],
                       capture_output=True)
        if fp.exists() and fp.stat().st_size > 0:
            imgs.append(fp)
    if not imgs:
        return None
    from PIL import Image, ImageDraw
    tiles = [Image.open(p).convert("RGB") for p in imgs]
    w = max(t.width for t in tiles)
    h = max(t.height for t in tiles)
    pad, label_h = 4, 18
    sheet = Image.new("RGB", (w * 2 + pad, (h + label_h) * 2 + pad), (18, 14, 22))
    d = ImageDraw.Draw(sheet)
    for idx, t in enumerate(tiles[:4]):
        x = (idx % 2) * (w + pad)
        y = (idx // 2) * (h + label_h + pad)
        sheet.paste(t, (x, y + label_h))
        d.text((x + 4, y + 3), "%s @%.0f%%" % (code, SHEET_FRACS[idx] * 100), fill=(230, 230, 230))
    out = SHEET_DIR / ("%s.jpg" % code)
    sheet.save(out, quality=88)
    for p in imgs:
        p.unlink(missing_ok=True)
    return out


# ── 单条执行 ────────────────────────────────────────────────────────
def run_one(preset):
    code = preset["code"]
    duration = int(preset.get("duration_sec") or 8)
    t0 = time.time()
    rec = {
        "code": code, "title": preset["title"], "kind": preset["kind"],
        "duration_sec": duration,
    }
    log("=== %s %s (%ss) 开始" % (code, preset["title"], duration))
    try:
        img = load_ref_image(code)
        wf_preset = {
            "task_id": code,
            "kind": preset["kind"],
            "prompt": preset["prompt"],
            "lora_chain": _as_chain(preset.get("lora_chain")),
            "duration_sec": duration,
        }
        wf, save_id = W.build_workflow(wf_preset, img)
        pid = W.comfy("/prompt", {"prompt": wf, "client_id": "qa_" + code})["prompt_id"]
        entry = W.wait_prompt(pid, timeout=2400)
        out = W.find_output(entry, save_id)
        rec["elapsed"] = round(time.time() - t0, 1)

        if preset["kind"] == "image":
            rec.update(probe=probe(out), luma_peak=None, motion=None, ok=True, note="图片预设")
            dst = OUT_DIR / ("%s.png" % code)
        else:
            pr = probe(out)
            luma, motion = frame_metrics(out, pr.get("duration") or duration)
            rec.update(probe=pr, luma_peak=round(luma, 1), motion=round(motion, 2))

            problems = []
            if not pr.get("duration"):
                problems.append("无法解码")
            if pr.get("duration", 0) < duration * 0.6:
                problems.append("时长偏短(%.1fs<%.1fs)" % (pr.get("duration", 0), duration))
            if luma <= LUMA_MIN:
                problems.append("全黑(峰值%d)" % luma)
            if motion is not None and motion < MOTION_MIN:
                problems.append("几乎静止(幅度%.2f<%.1f)" % (motion, MOTION_MIN))
            rec["problems"] = problems
            rec["ok"] = not problems
            rec["note"] = "自动通过" if not problems else "; ".join(problems)

            sheet = contact_sheet(out, code, pr.get("duration") or duration)
            rec["sheet"] = str(sheet) if sheet else ""
            dst = OUT_DIR / ("%s.mp4" % code)

        OUT_DIR.mkdir(parents=True, exist_ok=True)
        subprocess.run(["cp", str(out), str(dst)], check=True)
        rec["file"] = str(dst)
        rec["file_size"] = os.path.getsize(dst)
        log("=== %s 完成 ok=%s %s" % (code, rec.get("ok"), rec.get("note", "")))
    except Exception as e:
        rec.update(ok=False, note="异常: %s" % str(e)[:200], problems=["异常"])
        log("=== %s 失败: %s" % (code, e))
    rec["total_elapsed"] = round(time.time() - t0, 1)
    with open(RESULT, "a", encoding="utf-8") as f:
        f.write(json.dumps(rec, ensure_ascii=False) + "\n")
    return rec


def done_codes():
    if not RESULT.exists():
        return set()
    s = set()
    for line in RESULT.read_text(encoding="utf-8").splitlines():
        if not line.strip():
            continue
        try:
            r = json.loads(line)
        except json.JSONDecodeError:
            continue
        if r.get("ok") is not None:
            s.add(r["code"])
    return s


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--batch", type=int, default=0, help="第 N 批（每批 10 条）")
    ap.add_argument("--size", type=int, default=10, help="每批条数")
    ap.add_argument("--only", default="", help="只跑指定 code，逗号分隔")
    ap.add_argument("--list", action="store_true")
    ap.add_argument("--force", action="store_true", help="忽略已完成记录")
    a = ap.parse_args()

    presets = load_presets()
    if a.only:
        want = [x.strip() for x in a.only.split(",") if x.strip()]
        presets = [p for p in presets if p["code"] in want]
    elif a.batch:
        start = (a.batch - 1) * a.size
        presets = presets[start:start + a.size]

    done = set() if a.force else done_codes()
    todo = [p for p in presets if p["code"] not in done]

    log("计划 %d 条，已完成 %d，待跑 %d" % (len(presets), len(presets) - len(todo), len(todo)))
    if a.list:
        for p in presets:
            mark = "OK" if p["code"] in done else "  "
            print("%s %s %-46s %s" % (mark, p["code"], p["title"][:46], p["kind"]))
        return

    for p in todo:
        run_one(p)
    log("本批结束")


if __name__ == "__main__":
    main()
