#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""在本机（GPU 节点）直接向 ComfyUI 提交一个工作流做冒烟测试，不走任何站点。

用法（在 GPU 节点上）：
  python3 comfy_smoke.py <workflow.json> <input_image_in_comfy_input> [style_tag]

会打印 prompt_id、轮询进度、产物路径和耗时，用于验证：
  - 模型能否加载（UNET/CLIP/VAE/LoRA）
  - 自定义节点是否齐全
  - RIFE TensorRT 引擎首次构建
"""
import json
import random
import sys
import time
from pathlib import Path

import requests

COMFY = "http://127.0.0.1:8000"
BASE = Path("/opt/comfyui/ComfyUI")


def main():
    wf_path = Path(sys.argv[1])
    img_name = sys.argv[2]
    tag = sys.argv[3] if len(sys.argv) > 3 else "smoke"

    wf = json.loads(wf_path.read_text(encoding="utf-8"))
    is_video = any(v.get("class_type") == "MiniMaxH3ImageToVideo" for v in wf.values() if isinstance(v, dict))

    load = seed = save = None
    for k, v in wf.items():
        ct = v.get("class_type") if isinstance(v, dict) else None
        if ct == "LoadImage":
            load = k
        elif ct in ("RandomNoise",):
            seed = k
        elif ct in ("SaveVideo", "VHS_VideoCombine", "SaveImage"):
            save = k

    wf[load]["inputs"]["image"] = img_name
    if seed:
        wf[seed]["inputs"]["noise_seed" if is_video else "seed"] = random.randint(1, 2 ** 48)
    if save:
        wf[save]["inputs"]["filename_prefix"] = "smoke/%s_%d" % (tag, int(time.time()))

    print("submitting %s (video=%s) load=%s seed=%s save=%s"
          % (wf_path.name, is_video, load, seed, save), flush=True)
    r = requests.post(COMFY + "/prompt", json={"prompt": wf, "client_id": "smoke"}, timeout=60)
    d = r.json()
    if d.get("node_errors"):
        print("NODE ERRORS:", json.dumps(d["node_errors"], ensure_ascii=False)[:1500], flush=True)
        return 1
    if "prompt_id" not in d:
        print("SUBMIT FAILED:", d, flush=True)
        return 1
    pid = d["prompt_id"]
    print("prompt_id", pid, flush=True)

    t0 = time.time()
    last = 0
    while time.time() - t0 < 5400:
        try:
            h = requests.get(COMFY + "/history/" + pid, timeout=20).json()
        except Exception:
            time.sleep(5)
            continue
        e = h.get(pid)
        if e:
            st = e.get("status", {})
            for m in st.get("messages", []):
                if m[0] == "execution_error":
                    print("EXECUTION ERROR:", str(m[1].get("exception_message"))[:800], flush=True)
                    return 1
                if m[0] == "execution_interrupted":
                    print("INTERRUPTED", flush=True)
                    return 1
            if st.get("completed"):
                print("COMPLETED in %.1fs" % (time.time() - t0), flush=True)
                outs = e.get("outputs", {})
                for node, val in outs.items():
                    for key in ("images", "gifs", "video"):
                        for f in val.get(key, []) or []:
                            p = Path(f.get("fullpath") or str(BASE / "output" / f.get("subfolder", "") / f["filename"]))
                            print("OUT", p, p.stat().st_size if p.exists() else "MISSING", flush=True)
                return 0
        else:
            q = requests.get(COMFY + "/queue", timeout=20).json()
            running = q.get("queue_running") or []
            pending = q.get("queue_pending") or []
            phase = "running" if any(len(i) > 1 and i[1] == pid for i in running) else \
                    ("queued" if any(len(i) > 1 and i[1] == pid for i in pending) else "lost")
            now = int(time.time() - t0)
            if now - last >= 30:
                last = now
                print("  %ds %s" % (now, phase), flush=True)
            if phase == "lost":
                time.sleep(5)
        time.sleep(5)
    print("TIMEOUT", flush=True)
    return 1


if __name__ == "__main__":
    sys.exit(main())
