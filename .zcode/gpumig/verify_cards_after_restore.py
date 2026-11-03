#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""GPU 重建后：验证 AI 工作室卡片链路是否恢复。

检查四层：
  1. 模型文件齐全（UNET + 7 LoRA + CLIP/VAE）
  2. ComfyUI 能认出这些模型（/object_info）
  3. worker 用真实 preset 拼出的工作流接线正确
  4. 真实提交一条 Ref2VA 任务，产物非黑非空

在 GPU 机上用 /opt/comfyui/venv/bin/python3 运行：
  python3 verify_cards_after_restore.py [--generate]
"""
import json
import os
import sys

sys.path.insert(0, "/opt/cv/short")
import worker_short as W

MODELS = {
    "UNET": "/data/models/diffusion_models/h3ErosMax_beta7_int8.safetensors",
    "CLIP": "/data/models/text_encoders/qwen3vl_32b_heretic_minimax_h3_nvfp4.safetensors",
    "VideoVAE": "/data/models/vae/minimax_h3_video_vae_fp16.safetensors",
    "AudioVAE": "/data/models/vae/minimax_h3_audio_vae_fp32.safetensors",
}
LORAS = [
    "MMH3_DeepThroat_v0.1.safetensors",
    "DR34ML4Y_AllInOne_NSFW_H3.safetensors",
    "Minimax_H3_Unlocked_V2.1.safetensors",
    "zy_AmateurPov_K2_v1.safetensors",
    "HMMisDog_H3_v1.safetensors",
    "HMMasturbation_v2.safetensors",
    "HMPussy_V2.safetensors",
]

ok = True


def check_files():
    global ok
    print("=== 1. 模型文件 ===")
    for label, p in MODELS.items():
        if os.path.exists(p) and os.path.getsize(p) > 10_000_000:
            print("  OK   %-10s %.1f GB" % (label, os.path.getsize(p) / 1073741824))
        else:
            print("  FAIL %-10s 缺失或过小: %s" % (label, p))
            ok = False
    for n in LORAS:
        p = "/data/models/loras/" + n
        if os.path.exists(p) and os.path.getsize(p) > 1_000_000:
            print("  OK   LoRA %-42s %.0f MB" % (n, os.path.getsize(p) / 1048576))
        else:
            print("  FAIL LoRA %-42s 缺失" % n)
            ok = False


def check_comfy():
    global ok
    print("\n=== 2. ComfyUI 可见性 ===")
    try:
        info = W.comfy("/object_info/UNETLoader", timeout=30)
        opts = (info["UNETLoader"]["input"]["required"]["unet_name"][0] or [])
        hit = "h3ErosMax_beta7_int8.safetensors" in opts
        print("  %s UNETLoader 含 h3ErosMax_beta7_int8" % ("OK  " if hit else "FAIL"))
        ok = ok and hit

        info = W.comfy("/object_info/LoraLoaderModelOnly", timeout=30)
        opts = info["LoraLoaderModelOnly"]["input"]["required"]["lora_name"][0] or []
        miss = [n for n in LORAS if n not in opts]
        print("  %s LoraLoaderModelOnly 含全部 7 个（缺 %d）" % ("OK  " if not miss else "FAIL", len(miss)))
        for m in miss:
            print("       缺:", m)
        ok = ok and not miss

        info = W.comfy("/object_info", timeout=60)
        t8 = "MiniMaxH3AudioConditioningT8" in info
        print("  %s T8 节点已注册" % ("OK  " if t8 else "FAIL"))
        ok = ok and t8
    except Exception as e:
        print("  FAIL ComfyUI 查询失败:", e)
        ok = False


def check_workflow():
    global ok
    print("\n=== 3. 工作流接线（Ref2VA / t8 / 3 层 LoRA） ===")
    preset = {
        "task_id": 0, "kind": "video", "prompt": "test",
        "duration_sec": 10, "task_type": "Ref2VA", "pipeline": "t8",
        "sampler": "euler", "scheduler": "beta57", "sampler_alpha": 0.5,
        "sampler_beta": 0.7, "steps": 8,
        "unet_name": "h3ErosMax_beta7_int8.safetensors",
        "resolution": "Preview — 640×640",
        "lora_chain": [["HMMisDog_H3_v1.safetensors", 0.85],
                       ["Minimax_H3_Unlocked_V2.1.safetensors", 0.5],
                       ["DR34ML4Y_AllInOne_NSFW_H3.safetensors", 0.8]],
    }
    try:
        wf, save = W.build_workflow(preset, "persona_020.jpg")
        t8id = next((k for k, v in wf.items()
                     if v["class_type"] == "MiniMaxH3AudioConditioningT8"), None)
        c = wf[t8id]["inputs"] if t8id else {}
        checks = [
            ("T8 节点存在", bool(t8id)),
            ("task_type=Ref2VA", c.get("task_type") == "Ref2VA"),
            ("有 ref_images", bool(c.get("ref_images.ref_image_0"))),
            ("无 first_frame", c.get("first_frame") is None),
            ("UNET=ErosMax", wf[W.find_node(wf, "UNETLoader")]["inputs"]["unet_name"]
             == "h3ErosMax_beta7_int8.safetensors"),
            ("调度器 beta57", wf[W.find_node(wf, "BetaSamplingScheduler")]["inputs"]["alpha"] == 0.5),
            ("分辨率 640", wf["205"]["inputs"]["resolution"] == "Preview — 640×640"),
            ("LoRA 3 层", sum(1 for v in wf.values()
                              if v["class_type"] == "LoraLoaderModelOnly") == 3),
        ]
        for label, good in checks:
            print("  %s %s" % ("OK  " if good else "FAIL", label))
            ok = ok and good
    except Exception as e:
        print("  FAIL 工作流拼装异常:", e)
        ok = False


def generate():
    print("\n=== 4. 真实生成一条（约 2-3 分钟） ===")
    preset = {
        "task_id": 999, "kind": "video",
        "prompt": ("For the target video, at 0.00 seconds into the target video, "
                   "<Picture 1> (from [Shot 1]) is fully referenced for the woman's "
                   "appearance and identity.\n\nintegrated_multimodal_description: "
                   "[Shot 1] Live-action, cinematic, photorealism. A woman is lying "
                   "on a bed, looking at the camera.\n\noverall_soundscape: quiet room.\n\n"
                   "non_diegetic_music: N/A"),
        "duration_sec": 10, "task_type": "Ref2VA", "pipeline": "t8",
        "sampler": "euler", "scheduler": "beta57", "sampler_alpha": 0.5,
        "sampler_beta": 0.7, "steps": 8,
        "unet_name": "h3ErosMax_beta7_int8.safetensors",
        "resolution": "Preview — 640×640",
        "lora_chain": [["HMMisDog_H3_v1.safetensors", 0.85]],
    }
    wf, save = W.build_workflow(preset, "persona_020.jpg")
    pid = W.comfy("/prompt", {"prompt": wf, "client_id": "verify_restore"})["prompt_id"]
    print("  已提交:", pid, flush=True)
    entry = W.wait_prompt(pid, timeout=1800)
    st = (entry.get("status") or {}).get("status_str")
    if st != "success":
        print("  FAIL 生成失败:", json.dumps(entry.get("status"), ensure_ascii=False)[:300])
        return False
    out = W.find_output(entry, save)
    sz = os.path.getsize(out)
    print("  OK   产物 %s (%.1f MB)" % (out.name, sz / 1048576))
    return sz > 100_000


if __name__ == "__main__":
    check_files()
    check_comfy()
    check_workflow()
    if "--generate" in sys.argv and ok:
        ok = generate()
    print("\n=== 总结:", "全部通过" if ok else "存在问题", "===")
    sys.exit(0 if ok else 1)
