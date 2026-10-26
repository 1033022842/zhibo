#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Pull the 15 production models from 99.11 staging into /data/models on 166.124.

- 4 parallel rsync streams (a single stream caps ~6.7 MB/s; 4 streams ~10 MB/s)
- idempotent: files already at the exact expected byte size are skipped
- loops until every file matches; safe to re-run or kill at any time
- never uses rsync --inplace (a truncated source would shred the target)
"""
import os
import subprocess
import sys
import time
import threading

SRC = "root@172.81.99.11"
KEY = "/home/administrator/.ssh/modelpull"
SM = "/data/modelmig"
M = "/data/models"
LOG = "/data/model_pull.log"
PAR = 4

MANIFEST = [
    ("diffusion_models/minimax_h3_fl2va_pruned_int8_convrot.safetensors", 20970379616),
    ("text_encoders/qwen3vl_32b_heretic_minimax_h3_nvfp4.safetensors", 15683129587),
    ("unet/qwen_image_edit_fp8_e4m3fn.safetensors", 20430635136),
    ("clip/qwen_2.5_vl_7b_fp8_scaled.safetensors", 9384670680),
    ("vae/minimax_h3_video_vae_fp16.safetensors", 5207808496),
    ("vae/minimax_h3_audio_vae_fp32.safetensors", 605254808),
    ("vae/qwen_image_vae.safetensors", 253806246),
    ("loras/minimax-h3_fl2v_8Step_motion_enhancer.safetensors", 1956171592),
    ("loras/MMH3_Perfect_fingering_v1_000002000.safetensors", 155110392),
    ("loras/MysticXXX_MMH3-V4.safetensors", 155095800),
    ("loras/MM-H3 - Pussy Lora.safetensors", 155132912),
    ("loras/soulmate_h3_v1.safetensors", 155095800),
    ("loras/Qwen-Image-Edit-Remove-Clothes_V.1.safetensors", 472047184),
    ("loras/cum_facial_000005400.safetensors", 310168760),
    ("onnx/rife425_ensemble_False_scale_1_sim.onnx", 22777748),
]

SSHOPT = ("-i %s -o StrictHostKeyChecking=no -o BatchMode=yes -o ConnectTimeout=20 "
          "-o ServerAliveInterval=20 -o ServerAliveCountMax=6" % KEY)

_lock = threading.Lock()


def log(msg):
    line = time.strftime("%Y-%m-%d %H:%M:%S ") + str(msg)
    with _lock:
        print(line, flush=True)
        with open(LOG, "a", encoding="utf-8") as f:
            f.write(line + "\n")


def size(path):
    try:
        return os.path.getsize(path)
    except OSError:
        return 0


def pull_one(rel, want):
    dst = os.path.join(M, rel)
    os.makedirs(os.path.dirname(dst), exist_ok=True)
    cur = size(dst)
    if cur == want:
        log("SKIP %s (%d B) ok" % (rel, want))
        return True
    if cur > want:
        log("TRUNC %s local=%d > want=%d -> remove" % (rel, cur, want))
        os.remove(dst)
        cur = 0
    for att in range(1, 11):
        log("PULL[%d] %s (%d/%d)" % (att, rel, cur, want))
        subprocess.run(
            ["rsync", "-a", "--partial", "--append-verify", "--timeout=600",
             "-e", "ssh " + SSHOPT, "%s:%s/%s" % (SRC, SM, rel), dst],
            stdout=subprocess.DEVNULL, stderr=subprocess.STDOUT)
        cur = size(dst)
        if cur == want:
            log("DONE %s (%d B)" % (rel, want))
            return True
        log("SHORT %s %d/%d, retry in 20s" % (rel, cur, want))
        time.sleep(20)
    log("GIVEUP %s %d/%d" % (rel, cur, want))
    return False


def incomplete():
    return [(r, w) for r, w in MANIFEST if size(os.path.join(M, r)) != w]


def main():
    os.makedirs(M, exist_ok=True)
    log("=== model pull start (par=%d) ===" % PAR)
    rnd = 0
    while True:
        rnd += 1
        todo = incomplete()
        log("round %d: %d file(s) to pull" % (rnd, len(todo)))
        if not todo:
            log("=== ALL COMPLETE (round %d) ===" % rnd)
            break
        # one thread per file, capped at PAR concurrent
        sem = threading.Semaphore(PAR)
        threads = []

        def worker(r, w):
            with sem:
                try:
                    pull_one(r, w)
                except Exception as e:
                    log("ERR %s: %s" % (r, e))

        for r, w in todo:
            t = threading.Thread(target=worker, args=(r, w), daemon=True)
            t.start()
            threads.append(t)
        for t in threads:
            t.join()
        sync = subprocess.run(["sync"])
        left = incomplete()
        log("round %d done, still incomplete: %d" % (rnd, len(left)))
        if not left:
            log("=== ALL COMPLETE ===")
            break
        time.sleep(15)
    log("=== model pull end ===")


if __name__ == "__main__":
    main()
