# -*- coding: utf-8 -*-
"""VIP 定制内容 worker v6.0 — 单 GPU 多站共用版

与 v5.1-linux 的差异（四站共用一台 GPU 必须解决的三个真故障）：
1. **站点隔离**：ComfyUI 的 input/output 是四站共用的，而 task_id 各站各自从 1 开始。
   若仍用 `task_{id}.jpg` / `custom_video/task_{id}`，A 站的 task 12 会覆盖 B 站的 task 12
   （ComfyUI 排队执行时才读文件 → 生成出别人的图）。故文件名与输出前缀统一加 WORKER_TAG。
2. **accept 结果校验**：后端用 jsonFail 返回 HTTP 200 + code!=00000，
   v5.1 只 raise_for_status 就当成接单成功 → 重复生成 + 成品丢弃。现在显式判 code。
3. **网络重试**：progress/upload/complete 加指数退避重试，避免跨境抖动把已生成好的视频判失败。

另外：生成期间每 60s 发一次 progress 心跳（大任务在 96G 卡上可能跑很久），
轮询周期按站点错峰，避免 4 个 worker 同秒抢 ComfyUI 队列。

环境变量：
  WORKER_API_BASE  例 https://sugus.ai/api
  WORKER_KEY       X-Api-Key
  WORKER_ID        X-Worker-Id（记录到 lp_ai_task.worker_id）
  WORKER_TAG       站点标识，用于隔离 ComfyUI 内的文件名（sugus/heygirl/kiss/kelymi）
  WORKER_ROOT      本站工作目录（工作流 JSON / wf_prompts.json / tmp / lock / log）
  COMFY_BASE       ComfyUI 安装目录（input/output 所在）
  WORKER_POLL_SEC  轮询基础周期（默认 15；实际 = 15 + 站点偏移）
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

API_BASE = os.environ.get("WORKER_API_BASE", "http://127.0.0.1/api").rstrip("/")
WORKER_KEY = os.environ.get("WORKER_KEY", "live-ai-api-key-2026")
WORKER_ID = os.environ.get("WORKER_ID", "gpu-h3")
WORKER_TAG = (os.environ.get("WORKER_TAG", "sugus") or "sugus").strip().lower()
PROXIES = None

ROOT = Path(os.environ.get("WORKER_ROOT", "/opt/cv/sugus"))
WF_IMAGE = ROOT / "qwenedit_api.json"
LOG_FILE = ROOT / "worker.log"

H3_BASE = Path(os.environ.get("COMFY_BASE", "/opt/comfyui/ComfyUI"))
COMFY_CANDIDATES = ["http://127.0.0.1:8000", "http://127.0.0.1:8188"]
COMFY = COMFY_CANDIDATES[0]
COMFY_PATHS = {
    "http://127.0.0.1:8000": (H3_BASE / "input", H3_BASE / "output"),
    "http://127.0.0.1:8188": (H3_BASE / "input", H3_BASE / "output"),
}

WF_VIDEO_DEFAULT = "fingering"
WF_VIDEO_MAP = {
    "fingering": ROOT / "h3_fingering_api.json",
    "mysticxxx": ROOT / "MysticXXX_api.json",
    "cumfacial": ROOT / "CumFacial_api.json",
}

I_LOAD, I_POS, I_SEED, I_SAVE = "78", "76", "3", "60"

# 四站共用一条 ComfyUI 队列，排队时间要算进超时：默认 60 分钟
GEN_TIMEOUT = int(os.environ.get("WORKER_GEN_TIMEOUT", "3600"))
UPLOAD_TIMEOUT = 900
HEARTBEAT_SEC = 60

POLL_BASE = int(os.environ.get("WORKER_POLL_SEC", "15"))
# 站点错峰：同一台机器上 4 个 worker 不能在同一秒抢队列
POLL_OFFSET = {"sugus": 0, "heygirl": 2, "kiss": 4, "kelymi": 6}.get(WORKER_TAG, 0)
POLL_SEC = POLL_BASE + POLL_OFFSET

_MUTEX_FD = None
_LOG_LOCK = threading.Lock()


def log(msg):
    line = time.strftime("%Y-%m-%d %H:%M:%S ") + "[%s] " % WORKER_TAG + str(msg)
    with _LOG_LOCK:
        print(line, flush=True)
        try:
            with open(LOG_FILE, "a", encoding="utf-8") as f:
                f.write(line + "\n")
        except OSError:
            pass


def load_prompts():
    p = ROOT / "wf_prompts.json"
    if p.exists():
        try:
            return json.loads(p.read_text(encoding="utf-8"))
        except Exception as e:
            log("wf_prompts.json 读取失败: %s" % e)
    return {}


WF_PROMPTS = load_prompts()


def pick_video_wf(style):
    key = (style or "").strip().lower()
    if key in WF_VIDEO_MAP:
        return key, WF_VIDEO_MAP[key]
    if key:
        log("未知 style=%r，回退默认 %s" % (style, WF_VIDEO_DEFAULT))
    return WF_VIDEO_DEFAULT, WF_VIDEO_MAP[WF_VIDEO_DEFAULT]


def pick_prompt(style):
    pool = WF_PROMPTS.get(style) or []
    return random.choice(pool) if pool else None


def acquire_singleton():
    global _MUTEX_FD
    ROOT.mkdir(parents=True, exist_ok=True)
    lock_path = ROOT / "worker.lock"
    _MUTEX_FD = open(lock_path, "w")
    try:
        fcntl.flock(_MUTEX_FD, fcntl.LOCK_EX | fcntl.LOCK_NB)
    except BlockingIOError:
        log("已有 worker 在运行（文件锁被占用），本进程退出")
        return False
    _MUTEX_FD.write(str(os.getpid()))
    _MUTEX_FD.flush()
    log("worker 获得单例, pid=%d" % os.getpid())
    return True


# ── API 调用（带重试）────────────────────────────────────
RETRY_STATUS = {429, 500, 502, 503, 504}


def api(path, method="get", timeout=60, retries=1, **kw):
    """调用后端。retries=1 表示只有一次尝试（轮询用）；关键步骤传 3~5。"""
    headers = kw.pop("headers", {})
    headers["X-Api-Key"] = WORKER_KEY
    headers.setdefault("X-Worker-Id", WORKER_ID)
    last = None
    for att in range(1, max(1, retries) + 1):
        try:
            r = requests.request(method, API_BASE + path, headers=headers,
                                 timeout=timeout, verify=False, proxies=PROXIES, **kw)
            if r.status_code in RETRY_STATUS and att < retries:
                raise requests.RequestException("HTTP %d" % r.status_code)
            r.raise_for_status()
            return r.json()
        except (requests.RequestException, ValueError) as e:
            last = e
            if att < retries:
                wait = min(20, 2 * att)
                log("API %s 第 %d 次失败(%s)，%ds 后重试" % (path, att, e, wait))
                time.sleep(wait)
    raise last


def ok(d):
    return isinstance(d, dict) and str(d.get("code")) == "00000"


def download(url, dest):
    if url.startswith("/"):
        url = API_BASE.split("/api")[0] + url
    r = requests.get(url, timeout=120, verify=False, stream=True, proxies=PROXIES)
    r.raise_for_status()
    with open(dest, "wb") as f:
        for chunk in r.iter_content(65536):
            if chunk:
                f.write(chunk)


def probe_comfy():
    global COMFY
    for base in COMFY_CANDIDATES:
        try:
            requests.get(base + "/system_stats", timeout=5)
            if COMFY != base:
                log("ComfyUI 端点 -> %s" % base)
            COMFY = base
            return True
        except Exception:
            continue
    return False


def submit_workflow(wf):
    r = requests.post(COMFY + "/prompt",
                      json={"prompt": wf, "client_id": WORKER_ID}, timeout=30)
    r.raise_for_status()
    d = r.json()
    if d.get("node_errors"):
        raise RuntimeError("校验失败: " + json.dumps(d["node_errors"], ensure_ascii=False)[:400])
    return d["prompt_id"]


def prompt_in_queue(prompt_id):
    q = requests.get(COMFY + "/queue", timeout=15).json()
    for item in (q.get("queue_running") or []) + (q.get("queue_pending") or []):
        if len(item) > 1 and item[1] == prompt_id:
            return True
    return False


def wait_prompt(prompt_id):
    t0 = time.time()
    miss = 0
    while time.time() - t0 < GEN_TIMEOUT:
        try:
            h = requests.get(COMFY + "/history/" + prompt_id, timeout=15).json()
            e = h.get(prompt_id)
            if e:
                st = e.get("status", {})
                for m in st.get("messages", []):
                    if m[0] == "execution_error":
                        raise RuntimeError("生成失败: " + str(m[1].get("exception_message"))[:300])
                if st.get("completed"):
                    return e
                miss = 0
            elif prompt_in_queue(prompt_id):
                miss = 0
            else:
                miss += 1
                if miss >= 2:
                    raise RuntimeError("生成被取消或中断（prompt 已不在队列与历史中）")
        except requests.RequestException:
            pass
        time.sleep(8)
    raise RuntimeError("生成超时(%ds，含四站排队)" % GEN_TIMEOUT)


def _find_output(entry, node_id):
    outs = entry.get("outputs", {})
    node = outs.get(node_id) or next(iter(outs.values()), {})
    files = node.get("images") or node.get("gifs") or []
    if not files:
        raise RuntimeError("无产物: " + json.dumps(outs, ensure_ascii=False)[:200])
    _, cout = COMFY_PATHS.get(COMFY, (H3_BASE / "input", H3_BASE / "output"))
    info = files[0]
    path = Path(info.get("fullpath") or str(cout / info.get("subfolder", "") / info["filename"]))
    if not path.exists():
        raise RuntimeError("产物不存在: " + str(path))
    return path


def vid_nodes(wf):
    def find(*types):
        for k, v in wf.items():
            if v.get("class_type") in types:
                return k
        return None
    seed = find("RandomNoise")
    save = find("SaveVideo", "VHS_VideoCombine")
    load = find("LoadImage")
    i2v = find("MiniMaxH3ImageToVideo")
    missing = [n for n, x in (("RandomNoise", seed), ("SaveVideo/VHS_VideoCombine", save),
                              ("LoadImage", load), ("MiniMaxH3ImageToVideo", i2v)) if x is None]
    if missing:
        raise RuntimeError("工作流缺少节点: " + ", ".join(missing))
    return seed, save, load, i2v


def stage_input(source_img: Path, task_id: int) -> str:
    """把参考图放进 ComfyUI 共用 input/，文件名带站点标签防串台。

    参考图 URL 可能没有扩展名（历史数据 `/storage/custom_video/20260920/photo_2026-09-2ef053...`），
    ComfyUI 的 LoadImage 在下拉列表里按扩展名过滤，没有后缀会看不到，所以兜底补 .jpg。
    """
    cin, _ = COMFY_PATHS.get(COMFY, (H3_BASE / "input", H3_BASE / "output"))
    ext = source_img.suffix.lower()
    if ext not in (".jpg", ".jpeg", ".png", ".webp", ".bmp"):
        ext = ".jpg"
    img_name = "task_%s_%d%s" % (WORKER_TAG, task_id, ext)
    target = cin / img_name
    target.parent.mkdir(parents=True, exist_ok=True)
    shutil.copy(source_img, target)
    return img_name


def generate_video(task, source_img: Path, task_id: int, wf_path: Path, style: str):
    wf = json.loads(wf_path.read_text(encoding="utf-8"))
    img_name = stage_input(source_img, task_id)

    v_seed, v_save, v_load, v_i2v = vid_nodes(wf)
    wf[v_load]["inputs"]["image"] = img_name
    pr = pick_prompt(style)
    if pr:
        wf[v_i2v]["inputs"]["prompt"] = pr
        log("[task %d] 随机提示词: %s..." % (task_id, pr[:60]))
    wf[v_seed]["inputs"]["noise_seed"] = random.randint(1, 2 ** 48)
    wf[v_save]["inputs"]["filename_prefix"] = "custom_video/%s/task_%d" % (WORKER_TAG, task_id)

    pid = submit_workflow(wf)
    log("[task %d] 视频任务已提交: %s" % (task_id, pid))
    entry = wait_prompt(pid)
    video = _find_output(entry, v_save)
    log("[task %d] 视频已生成: %s" % (task_id, video.name))

    cover = video.with_suffix(".cover.jpg")
    subprocess.run(["ffmpeg", "-y", "-hide_banner", "-loglevel", "error", "-i", str(video),
                    "-vframes", "1", "-q:v", "4", str(cover)], check=True, capture_output=True)
    return video, cover, "video"


def generate_image(task, source_img: Path, task_id: int):
    wf = json.loads(WF_IMAGE.read_text(encoding="utf-8"))
    img_name = stage_input(source_img, task_id)

    wf[I_LOAD]["inputs"]["image"] = img_name
    if task.get("prompt"):
        wf[I_POS]["inputs"]["prompt"] = task["prompt"]
    wf[I_SEED]["inputs"]["seed"] = random.randint(1, 2 ** 48)
    wf[I_SAVE]["inputs"]["filename_prefix"] = "custom_edit/%s/task_%d" % (WORKER_TAG, task_id)

    pid = submit_workflow(wf)
    log("[task %d] 图片任务已提交: %s" % (task_id, pid))
    entry = wait_prompt(pid)
    img = _find_output(entry, I_SAVE)
    log("[task %d] 图片已生成: %s" % (task_id, img.name))
    return img, None, "image"


def heartbeat_loop(tid, stop_ev):
    """生成期间每 60s 上报一次 progress，前端能看到任务还活着。"""
    pct = 40
    while not stop_ev.wait(HEARTBEAT_SEC):
        try:
            api("/aiTask/progress", "post", timeout=30, retries=2,
                data={"task_id": tid, "percent": min(pct, 90), "message": "generating"})
            pct = min(pct + 5, 90)
        except Exception:
            pass


def process(task):
    tid = task["task_id"]
    kind = task.get("kind", "video")

    # 接单：后端失败时是 HTTP 200 + code!=00000，必须显式判断，否则会重复生成
    acc = api("/customVideo/accept", "post", timeout=60, retries=3, data={"task_id": tid})
    if not ok(acc):
        log("[task %d] 接单失败，放弃: %s" % (tid, acc.get("msg") if isinstance(acc, dict) else acc))
        return

    style, wf_video = pick_video_wf(task.get("style"))
    log("[task %d] 已接单: kind=%s style=%s wf=%s" % (tid, kind, style, wf_video.name))
    stop_ev = threading.Event()
    hb = None
    try:
        if not probe_comfy():
            raise RuntimeError("ComfyUI 未运行（8000/8188 均无响应）")

        tmp = ROOT / "tmp"
        tmp.mkdir(parents=True, exist_ok=True)
        # 参考图 URL 常常没有扩展名：补一个 .jpg 让 ComfyUI 的 LoadImage 能识别
        raw = tmp / ("%s_%d_src" % (WORKER_TAG, tid))
        api("/aiTask/progress", "post", timeout=30, retries=2,
            data={"task_id": tid, "percent": 15, "message": "downloading"})
        download(task["image_url"], raw)
        src = raw.with_suffix(".jpg") if raw.suffix == "" else raw
        if src is not raw:
            raw.replace(src)

        api("/aiTask/progress", "post", timeout=30, retries=2,
            data={"task_id": tid, "percent": 35, "message": "generating"})
        hb = threading.Thread(target=heartbeat_loop, args=(tid, stop_ev), daemon=True)
        hb.start()

        if kind == "image":
            out, cover, _ = generate_image(task, src, tid)
            upname, upmime = "ci_%s_%d.png" % (WORKER_TAG, tid), "image/png"
            with open(out, "rb") as f:
                up = api("/customVideo/uploadVideo", "post", timeout=UPLOAD_TIMEOUT, retries=4,
                         data={"task_id": tid}, files={"video": (upname, f, upmime)})
            dur = 0
        else:
            out, cover, _ = generate_video(task, src, tid, wf_video, style)
            with open(out, "rb") as fv, open(cover, "rb") as fc:
                up = api("/customVideo/uploadVideo", "post", timeout=UPLOAD_TIMEOUT, retries=4,
                         data={"task_id": tid},
                         files={"video": ("cv_%s_%d.mp4" % (WORKER_TAG, tid), fv, "video/mp4"),
                                "cover": ("cv_%s_%d.jpg" % (WORKER_TAG, tid), fc, "image/jpeg")})
            d = subprocess.run(["ffprobe", "-v", "quiet", "-show_entries", "format=duration",
                                "-of", "csv=p=0", str(out)], capture_output=True, text=True)
            dur = int(float(d.stdout.strip() or 10))

        stop_ev.set()

        if not ok(up):
            raise RuntimeError("上传失败: %s" % (up.get("msg") if isinstance(up, dict) else up))

        done = api("/aiTask/complete", "post", timeout=120, retries=5, data={
            "task_id": tid,
            "video_url": up["data"]["video_url"],
            "duration_sec": max(0, dur),
            "cover_url": up["data"].get("cover_url", ""),
        })
        if not ok(done):
            raise RuntimeError("回调失败: %s" % (done.get("msg") if isinstance(done, dict) else done))
        log("[task %d] 完成 -> %s" % (tid, up["data"]["video_url"]))
    except Exception as e:
        stop_ev.set()
        log("[task %d] 失败: %s\n%s" % (tid, e, traceback.format_exc()[-400:]))
        try:
            api("/aiTask/fail", "post", timeout=60, retries=3,
                data={"task_id": tid, "error_msg": str(e)[:400]})
        except Exception:
            pass


def main():
    ROOT.mkdir(parents=True, exist_ok=True)
    if not acquire_singleton():
        return
    log("worker v6.0 启动 tag=%s api=%s root=%s poll=%ds comfy=%s"
        % (WORKER_TAG, API_BASE, ROOT, POLL_SEC, COMFY if probe_comfy() else "unreachable"))
    while True:
        try:
            d = api("/customVideo/pending?count=1", timeout=60, retries=1)
            tasks = (d.get("data") or []) if ok(d) else []
            for t in tasks:
                process(t)
            if not tasks:
                time.sleep(POLL_SEC)
        except requests.RequestException as e:
            log("网络错误: %s" % e)
            time.sleep(30)
        except Exception as e:
            log("循环异常: %s\n%s" % (e, traceback.format_exc()[-300:]))
            time.sleep(30)


if __name__ == "__main__":
    main()
