# -*- coding: utf-8 -*-
"""50 条短视频预设 worker（独立通道，与 worker_gpu.py 完全隔离）

设计原则
--------
1. **不 import 现有 worker**：老通道服务 4 个站的 customVideo 业务，
   这里任何 import 都可能把老逻辑牵连进来。共享的只有「怎么跟 ComfyUI 说话」。
2. **独立目录/独立锁**：WORKER_ROOT 默认 /opt/cv/short/<site>，
   单例锁在同目录 worker.lock，和老通道 /opt/cv/<site>/worker.lock 互不干扰。
3. **独立 API 命名空间**：/api/aiShort/*，只认 task_type=ai_short_video|ai_short_image。
4. **精确提示词**：用后端下发的 preset.prompt，绝不随机轮换。
5. **动态 LoRA 链**：按 preset.lora_chain 生成 LoraLoaderModelOnly 节点，
   默认 4 层（motion_enhancer + 主 LoRA + 2 个低强度补位）。
6. **软限流（方案 A+B）**：每轮只取 1 条，两条之间 sleep WORKER_GAP_SEC（默认 60s）；
   后端 priority=1 低于老通道的 5，高峰期让老通道先取。

环境变量
--------
  SHORT_API_BASE  例 https://sugus.ai/api/aiShort
  SHORT_KEY       X-Api-Key（与 AI 通道同一把）
  SHORT_WORKER_ID X-Worker-Id，记账用
  SHORT_TAG       站点标识 sugus/heygirl/kiss/kelymi（用于 ComfyUI 文件名隔离）
  SHORT_ROOT      本站工作目录（工作流 / tmp / lock / log）
  SHORT_GAP_SEC   两条之间的间隔秒数（软限流，默认 60）
  COMFY_BASE      ComfyUI 安装目录
"""
import fcntl
import json
import os
import random
import shutil
import subprocess
import threading
import time
import traceback
from pathlib import Path

import requests

API_BASE = os.environ.get("SHORT_API_BASE", "http://127.0.0.1/api/aiShort").rstrip("/")
WORKER_KEY = os.environ.get("SHORT_KEY", "live-ai-api-key-2026")
WORKER_ID = os.environ.get("SHORT_WORKER_ID", "gpu-short")
WORKER_TAG = (os.environ.get("SHORT_TAG", "sugus") or "sugus").strip().lower()

ROOT = Path(os.environ.get("SHORT_ROOT", "/opt/cv/short/" + WORKER_TAG))
WF_VIDEO = ROOT / "short_base.json"       # 视频工作流（现有 h3 的只读副本）
WF_IMAGE = ROOT / "qwenedit_api.json"     # 图片预设（gs-08）用
LOG_FILE = ROOT / "worker.log"

COMFY_BASE = Path(os.environ.get("COMFY_BASE", "/opt/comfyui/ComfyUI"))
COMFY = "http://127.0.0.1:8000"

# 节点 id 与现有工作流一致（见 /opt/cv/sugus/h3_fingering_api.json）
I_UNET = "105:6"          # UNETLoader
I_LORA_BASE = "900"       # 原 motion_enhancer 位（模板里会被替换）
I_LORA_ACTION = "901"     # 原动作 LoRA 位（模板里会被替换）
I_ATTN = "105:197"        # MiniMaxH3ScheduledSolAttentionPatch（模型链终点）
I_I2V = "105:104"         # MiniMaxH3ImageToVideo
I_SEED = "105:15"         # RandomNoise
I_DUR = "105:111"         # PrimitiveFloat (duration)
I_LOAD = "114"            # LoadImage
I_SAVE = "105:84"         # SaveVideo（运行时按 class_type 探测，id 兜底）

PREFIX_VIDEO = "custom_video"
PREFIX_IMAGE = "custom_edit"

GAP_SEC = int(os.environ.get("SHORT_GAP_SEC", "60"))
GEN_TIMEOUT = int(os.environ.get("SHORT_GEN_TIMEOUT", "3600"))
UPLOAD_TIMEOUT = 900
HEARTBEAT_SEC = 60

_MUTEX_FD = None
_LOG_LOCK = threading.Lock()


def log(msg):
    line = time.strftime("%Y-%m-%d %H:%M:%S ") + "[short:%s] " % WORKER_TAG + str(msg)
    with _LOG_LOCK:
        print(line, flush=True)
        try:
            ROOT.mkdir(parents=True, exist_ok=True)
            with open(LOG_FILE, "a", encoding="utf-8") as f:
                f.write(line + "\n")
        except OSError:
            pass


def acquire_singleton():
    global _MUTEX_FD
    ROOT.mkdir(parents=True, exist_ok=True)
    lock_path = ROOT / "worker.lock"
    _MUTEX_FD = open(lock_path, "w")
    try:
        fcntl.flock(_MUTEX_FD, fcntl.LOCK_EX | fcntl.LOCK_NB)
    except BlockingIOError:
        log("已有 short worker 在运行（文件锁被占用），本进程退出")
        return False
    _MUTEX_FD.write(str(os.getpid()))
    _MUTEX_FD.flush()
    log("short worker 获得单例, pid=%d" % os.getpid())
    return True


# ── HTTP ────────────────────────────────────────────────────
RETRY_STATUS = {429, 500, 502, 503, 504}


def api(path, method="get", timeout=60, retries=1, **kw):
    url = API_BASE + path
    headers = {"X-Api-Key": WORKER_KEY, "X-Worker-Id": WORKER_ID}
    headers.update(kw.pop("headers", {}))
    last = None
    for attempt in range(retries + 1):
        try:
            if method == "get":
                r = requests.get(url, headers=headers, params=kw.get("params"), timeout=timeout)
            else:
                r = requests.post(url, headers=headers, data=kw.get("data"),
                                  files=kw.get("files"), timeout=timeout)
            if r.status_code in RETRY_STATUS and attempt < retries:
                time.sleep(2 ** attempt * 3)
                continue
            r.raise_for_status()
            return r.json()
        except Exception as e:
            last = e
            if attempt < retries:
                time.sleep(2 ** attempt * 3)
                continue
    raise last


def ok(resp):
    return isinstance(resp, dict) and str(resp.get("code")) == "00000"


# ── ComfyUI ─────────────────────────────────────────────────
def comfy(path, payload=None, timeout=60):
    url = COMFY + path
    if payload is None:
        r = requests.get(url, timeout=timeout)
    else:
        r = requests.post(url, json=payload, timeout=timeout)
    r.raise_for_status()
    return r.json()


def probe_comfy():
    try:
        comfy("/system_stats", timeout=10)
        return True
    except Exception:
        return False


def find_node(wf, *types):
    for k, v in wf.items():
        if v.get("class_type") in types:
            return k
    return None


def build_workflow(preset, img_name):
    """按 preset 拼工作流：精确 prompt + 动态 LoRA 链 + 指定时长。"""
    kind = preset.get("kind") or "video"
    is_image = kind == "image"
    wf_path = WF_IMAGE if is_image else WF_VIDEO
    wf = json.loads(wf_path.read_text(encoding="utf-8"))

    lora_chain = preset.get("lora_chain") or []
    if not isinstance(lora_chain, list):
        lora_chain = []
    duration = int(preset.get("duration_sec") or 8)

    if is_image:
        # 图片预设（gs-08）：走 qwenedit。
        # 节点 id 与 qwenedit_api.json 一致：78 LoadImage / 76 正向 TextEncodeQwenImageEdit
        # （3=KSampler, 76=positive, 77=negative）/ 3 KSampler / 60 SaveImage。
        # 图片路径不改 LoRA——qwenedit 自带 900 号 LoRA，语义与视频动作 LoRA 不同。
        load = find_node(wf, "LoadImage")
        pos = find_node(wf, "TextEncodeQwenImageEdit")   # 第一个（76）是正向
        ksam = find_node(wf, "KSampler")
        save = find_node(wf, "SaveImage")
        if not (load and pos and save):
            raise RuntimeError("图片工作流缺节点 LoadImage/TextEncodeQwenImageEdit/SaveImage")
        wf[load]["inputs"]["image"] = img_name
        wf[pos]["inputs"]["prompt"] = preset.get("prompt") or ""
        if ksam:
            wf[ksam]["inputs"]["seed"] = random.randint(1, 2 ** 48)
        wf[save]["inputs"]["filename_prefix"] = "%s/%s/task_%s" % (
            PREFIX_IMAGE, WORKER_TAG, preset["task_id"])
        return wf, save

    load = find_node(wf, "LoadImage")
    i2v = find_node(wf, "MiniMaxH3ImageToVideo")
    seed = find_node(wf, "RandomNoise")
    save = find_node(wf, "SaveVideo", "VHS_VideoCombine")
    attn = find_node(wf, "MiniMaxH3ScheduledSolAttentionPatch")
    if not all((load, i2v, seed, save, attn)):
        raise RuntimeError("视频工作流缺节点：LoadImage/ImageToVideo/RandomNoise/SaveVideo/AttnPatch")

    wf[load]["inputs"]["image"] = img_name
    wf[i2v]["inputs"]["prompt"] = preset.get("prompt") or ""
    wf[seed]["inputs"]["noise_seed"] = random.randint(1, 2 ** 48)
    wf[save]["inputs"]["filename_prefix"] = "%s/%s/task_%s" % (
        PREFIX_VIDEO, WORKER_TAG, preset["task_id"])

    dur_node = find_node(wf, "PrimitiveFloat")
    if dur_node:
        wf[dur_node]["inputs"]["value"] = duration

    # 删掉模板里原有的 LoRA 节点，改为按 preset 动态串
    for old in (I_LORA_BASE, I_LORA_ACTION):
        wf.pop(old, None)
    # 模板里可能还有别的 LoraLoaderModelOnly（位置不固定），一并清掉
    for k in [k for k, v in wf.items() if v.get("class_type") == "LoraLoaderModelOnly"]:
        wf.pop(k, None)

    unet = find_node(wf, "UNETLoader")
    prev = unet
    for i, item in enumerate(lora_chain):
        try:
            name, strength = item[0], float(item[1])
        except (TypeError, ValueError, IndexError):
            log("lora_chain 第 %d 项格式异常，跳过: %r" % (i, item))
            continue
        node = str(902 + i)
        wf[node] = {
            "class_type": "LoraLoaderModelOnly",
            "inputs": {"model": [prev, 0], "lora_name": name, "strength_model": strength},
        }
        prev = node
    wf[attn]["inputs"]["model"] = [prev, 0]

    log("  LoRA 链 %d 层: %s" % (len(lora_chain),
                                ", ".join("%s@%.2f" % (n, s) for n, s in lora_chain)))
    return wf, save


def wait_prompt(pid, timeout=None):
    deadline = time.time() + (timeout or GEN_TIMEOUT)
    while time.time() < deadline:
        h = comfy("/history/" + pid, timeout=30)
        if pid in h:
            return h[pid]
        time.sleep(5)
    raise RuntimeError("ComfyUI 生成超时（%ds）" % (timeout or GEN_TIMEOUT))


def find_output(entry, save_id):
    """从 history 里取产物路径。

    VHS_VideoCombine 的输出在 outputs[<id>]['gifs']，SaveImage/SaveVideo 在 'images'
    或 'videos'；filename 可能带子目录（custom_video/<tag>/xxx.mp4），要按后缀名找。
    """
    outs = entry.get("outputs", {}) or {}
    node = outs.get(save_id)
    candidates = []
    for v in ([node] if node else []) + list(outs.values()):
        for key in ("gifs", "videos", "images"):
            for item in (v.get(key) or []):
                fn = item.get("filename") if isinstance(item, dict) else None
                if fn:
                    candidates.append(fn)
        if candidates:
            break
    if not candidates:
        raise RuntimeError("ComfyUI 未返回产物（outputs=%s）" % list(outs.keys()))

    out_root = COMFY_BASE / "output"
    for fn in candidates:
        full = out_root / fn
        if full.exists():
            return full
        # 带子目录但路径拼不上时按文件名递归找
        hits = list(out_root.rglob(Path(fn).name))
        if hits:
            return hits[0]
    raise RuntimeError("产物文件不存在: %s" % candidates[0])


def assert_not_black(path: Path, what: str, min_luma: int = 8) -> None:
    """全黑产物拦截（与老 worker v6.1 同一道兜底，独立实现避免 import）。"""
    from PIL import Image
    im = Image.open(path)
    im.load()
    peak = max(im.convert("L").resize((64, 64)).getdata())
    if peak <= min_luma:
        raise RuntimeError("%s 全黑（max luminance=%d），判定生成失败" % (what, peak))


def assert_video_not_black(video: Path, duration: float) -> None:
    from PIL import Image
    peaks = []
    for frac in (0.25, 0.5, 0.75):
        frame = video.with_suffix(".probe_%d.jpg" % int(frac * 100))
        subprocess.run(["ffmpeg", "-y", "-hide_banner", "-loglevel", "error",
                        "-ss", "%.2f" % max(0.0, duration * frac), "-i", str(video),
                        "-vframes", "1", "-q:v", "6", str(frame)],
                       check=False, capture_output=True)
        try:
            if frame.exists() and frame.stat().st_size > 0:
                peaks.append(max(Image.open(frame).convert("L").resize((64, 64)).getdata()))
        finally:
            frame.unlink(missing_ok=True)
    if peaks and max(peaks) <= 8:
        raise RuntimeError("视频全黑（三帧 max luminance=%s），判定生成失败" % peaks)


def fit_portrait(path, ratio=9 / 16):
    """把参考图居中裁成 9:16。

    为什么必须裁：ComfyUI-PerfectVideoResolution 的源码（perfect_video_resolution.py:488）
    会用**输入图的真实宽高比**覆盖工作流里配置的 aspect_ratio：

        resolved_aspect = _best_aspect_ratio(image_w, image_h, aspect_order, fallback)
        target_w, target_h = _adaptive_size(image_w, image_h, target_pixels, divisible_by)

    所以只改 aspect_ratio 参数没用 —— 横图进去必然出横屏。
    2026-10-27 QA 实测：50 条里 19 条（封面本身是横图）输出了 1280x736 横屏，
    与竖屏产品的预期不符。实测「喂图前裁成 9:16」可稳定得到 736x1280 竖屏。
    """
    from PIL import Image
    im = Image.open(path).convert("RGB")
    w, h = im.size
    target = w / h
    if abs(target - ratio) < 0.01:
        return
    if target > ratio:            # 偏宽 → 裁左右
        new_w = max(1, int(round(h * ratio)))
        left = (w - new_w) // 2
        im = im.crop((left, 0, left + new_w, h))
    else:                          # 偏高 → 裁上下
        new_h = max(1, int(round(w / ratio)))
        top = (h - new_h) // 2
        im = im.crop((0, top, w, top + new_h))
    im.save(path, quality=95)
    log("  参考图已裁成 9:16: %s -> %dx%d" % (Path(path).name, im.size[0], im.size[1]))


def stage_input(task_id, image_url):
    """下载参考图到 ComfyUI/input，文件名带站点标签防串台。"""
    inp = COMFY_BASE / "input"
    inp.mkdir(parents=True, exist_ok=True)
    name = "short_%s_%s.jpg" % (WORKER_TAG, task_id)
    dst = inp / name
    r = requests.get(image_url, timeout=180, stream=True)
    r.raise_for_status()
    with open(dst, "wb") as f:
        shutil.copyfileobj(r.raw, f)
    if dst.stat().st_size < 1024:
        raise RuntimeError("参考图下载异常（%d 字节）" % dst.stat().st_size)
    # 统一裁成 9:16，否则横图会导致横屏输出（见 fit_portrait 注释）
    try:
        fit_portrait(dst)
    except Exception as e:
        log("  裁切失败（继续用原图）: %s" % e)
    return name


def heartbeat_loop(task_id, stop_ev):
    pct = 40
    while not stop_ev.wait(HEARTBEAT_SEC):
        try:
            api("/progress", "post", timeout=30, retries=1,
                data={"task_id": task_id, "percent": min(pct, 90), "message": "generating"})
            pct = min(pct + 5, 90)
        except Exception:
            pass


def process(task):
    tid = task["task_id"]
    kind = task.get("kind") or "video"
    code = task.get("preset_code") or ""
    prompt = (task.get("prompt") or "").strip()
    if not prompt:
        log("[task %d] preset %s 缺 prompt，拒绝执行并置失败" % (tid, code))
        try:
            api("/fail", "post", timeout=30, retries=1,
                data={"task_id": tid, "error_msg": "preset prompt missing: " + code})
        except Exception:
            pass
        return

    # 接单：后端失败是 HTTP 200 + code!=00000，必须显式判
    acc = api("/accept", "post", timeout=60, retries=3, data={"task_id": tid})
    if not ok(acc):
        log("[task %d] 接单失败，放弃: %s" % (tid, acc.get("msg") if isinstance(acc, dict) else acc))
        return

    preset = {
        "task_id": tid,
        "kind": kind,
        "prompt": prompt,
        "lora_chain": task.get("lora_chain") or [],
        "duration_sec": int(task.get("duration_sec") or 8),
    }
    log("[task %d] 已接单: preset=%s kind=%s dur=%ds 提示词=%s…"
        % (tid, code, kind, preset["duration_sec"], prompt[:70]))

    stop_ev = threading.Event()
    hb = None
    try:
        if not probe_comfy():
            raise RuntimeError("ComfyUI 未运行（%s 无响应）" % COMFY)

        img_name = stage_input(tid, task["image_url"])
        api("/progress", "post", timeout=30, retries=2,
            data={"task_id": tid, "percent": 35, "message": "generating"})
        hb = threading.Thread(target=heartbeat_loop, args=(tid, stop_ev), daemon=True)
        hb.start()

        wf, save_id = build_workflow(preset, img_name)
        pid = comfy("/prompt", {"prompt": wf, "client_id": "ai_short_" + WORKER_TAG})["prompt_id"]
        log("[task %d] 已提交 ComfyUI: %s" % (tid, pid))
        entry = wait_prompt(pid)
        out = find_output(entry, save_id)
        log("[task %d] 产物: %s" % (tid, out.name))

        if kind == "image":
            assert_not_black(out, "图片")
            with open(out, "rb") as f:
                up = api("/upload", "post", timeout=UPLOAD_TIMEOUT, retries=4,
                         data={"task_id": tid},
                         files={"video": (out.name, f, "image/png")})
            dur = 0
        else:
            d = subprocess.run(["ffprobe", "-v", "quiet", "-show_entries", "format=duration",
                                "-of", "csv=p=0", str(out)], capture_output=True, text=True)
            dur = int(float(d.stdout.strip() or 10))
            assert_video_not_black(out, dur)
            cover = out.with_suffix(".cover.jpg")
            subprocess.run(["ffmpeg", "-y", "-hide_banner", "-loglevel", "error", "-i", str(out),
                            "-vframes", "1", "-q:v", "4", str(cover)],
                           check=True, capture_output=True)
            with open(out, "rb") as fv, open(cover, "rb") as fc:
                up = api("/upload", "post", timeout=UPLOAD_TIMEOUT, retries=4,
                         data={"task_id": tid},
                         files={"video": (out.name, fv, "video/mp4"),
                                "cover": (cover.name, fc, "image/jpeg")})
            cover.unlink(missing_ok=True)

        if not ok(up):
            raise RuntimeError("上传失败: %s" % (up.get("msg") if isinstance(up, dict) else up))

        cp = api("/complete", "post", timeout=60, retries=3,
                 data={"task_id": tid, "duration_sec": dur})
        if not ok(cp):
            raise RuntimeError("complete 失败: %s" % (cp.get("msg") if isinstance(cp, dict) else cp))
        log("[task %d] 完成，时长 %ds" % (tid, dur))
    except Exception as e:
        log("[task %d] 失败: %s" % (tid, e))
        log(traceback.format_exc())
        try:
            api("/fail", "post", timeout=60, retries=3,
                data={"task_id": tid, "error_msg": str(e)[:400]})
        except Exception as e2:
            log("[task %d] 上报失败也失败: %s" % (tid, e2))
    finally:
        stop_ev.set()
        if hb:
            hb.join(timeout=5)


def main():
    ROOT.mkdir(parents=True, exist_ok=True)
    if not acquire_singleton():
        return
    log("short worker 启动 tag=%s api=%s root=%s gap=%ds comfy=%s"
        % (WORKER_TAG, API_BASE, ROOT, GAP_SEC, COMFY if probe_comfy() else "unreachable"))
    while True:
        try:
            res = api("/pending", "get", timeout=60, retries=3, params={"count": 1})
            tasks = (res.get("data") or []) if ok(res) else []
            if not tasks:
                time.sleep(15)
                continue
            for t in tasks:
                process(t)
            # 软限流（方案 A）：每轮只取 1 条，跑完歇一会儿，别把一张卡占满
            time.sleep(GAP_SEC)
        except Exception as e:
            log("轮询异常: %s" % e)
            time.sleep(30)


if __name__ == "__main__":
    main()
