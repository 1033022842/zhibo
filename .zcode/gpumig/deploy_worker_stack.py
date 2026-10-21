#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Deploy the 4-site worker stack to the consolidated GPU node.

Idempotent. Creates:
  /opt/cv/tpl/worker_gpu.py                 single source of truth
  /opt/cv/<site>/{env, 4 workflows, wf_prompts.json, tmp/}
  /etc/systemd/system/{comfyui.service, custom-video-worker@.service}
  /opt/comfyui/ComfyUI/extra_model_paths.yaml   -> /data/models
"""
import os
import stat
import sys

import paramiko

HOST, PORT, USER, PASSWORD = "38.247.166.124", 22, "administrator", "WB*!uZfGJa9K7eG@"
HERE = os.path.dirname(os.path.abspath(__file__))
REPO = os.path.abspath(os.path.join(HERE, "..", ".."))

SITES = [
    # (instance, api base, worker id, comfy tag, poll offset handled in code)
    ("sugus", "https://sugus.ai/api", "sugus-gpu-h3", "sugus"),
    ("heygirl", "https://heygirl.live/api", "heygirl-h3", "heygirl"),
    ("kiss", "https://kisss.ai/api", "kiss-gpu-h3", "kiss"),
    ("kelymi", "https://kelymi.autos/api", "kelymi-gpu-h3", "kelymi"),
]

WORKFLOWS = [
    "h3_fingering_api.json",
    "MysticXXX_api.json",
    "CumFacial_api.json",
    "qwenedit_api.json",
    "wf_prompts.json",
]

EXTRA_PATHS = """# ComfyUI model paths — 模型全部放 /data 大盘（重装系统不影响）
data:
  is_default: true
  base_path: /data/models/
  checkpoints: .
  clip: clip/
  clip_vision: clip_vision/
  vae: vae/
  loras: loras/
  diffusion_models: diffusion_models/
  text_encoders: text_encoders/
  unet: unet/
  controlnet: controlnet/
  upscale_models: upscale_models/
  onnx: onnx/
"""


def connect():
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect(HOST, PORT, USER, PASSWORD, timeout=30, banner_timeout=30,
              auth_timeout=30, look_for_keys=False, allow_agent=False)
    return c


def sh(c, cmd, sudo=False, t=120):
    if sudo:
        real = "sudo -S -p '' bash -c " + "'" + cmd.replace("'", "'\\''") + "'"
    else:
        real = "bash -c " + "'" + cmd.replace("'", "'\\''") + "'"
    stdin, out, err = c.exec_command(real, timeout=t)
    if sudo:
        stdin.write(PASSWORD + "\n")
        stdin.flush()
    o = out.read().decode("utf-8", "replace")
    e = err.read().decode("utf-8", "replace")
    rc = out.channel.recv_exit_status()
    return rc, o, e


def put_text(sftp, text, remote, mode=0o644):
    with sftp.open(remote, "w") as f:
        f.write(text)
    sftp.chmod(remote, mode)


def main():
    c = connect()
    sftp = c.open_sftp()

    # 1) dirs
    rc, o, e = sh(c, "mkdir -p /data/build", sudo=True)
    sftp.open("/data/build/.keep", "w").close()

    # 2) worker template
    sftp.put(os.path.join(HERE, "worker_gpu.py"), "/opt/cv/tpl/worker_gpu.py")
    print("tpl worker_gpu.py", sftp.stat("/opt/cv/tpl/worker_gpu.py").st_size)

    # 3) per-site dirs + workflows + env
    for inst, api, wid, tag in SITES:
        base = "/opt/cv/" + inst
        for sub in ("", "/tmp"):
            try:
                sftp.stat(base + sub)
            except IOError:
                sftp.mkdir(base + sub)
        for wf in WORKFLOWS:
            local = os.path.join(HERE, "..", "aipc", wf)
            sftp.put(local, base + "/" + wf)
        env = (
            "WORKER_API_BASE=%s\n"
            "WORKER_KEY=live-ai-api-key-2026\n"
            "WORKER_ID=%s\n"
            "WORKER_TAG=%s\n"
            "WORKER_ROOT=%s\n"
            "COMFY_BASE=/opt/comfyui/ComfyUI\n"
            "WORKER_POLL_SEC=15\n"
            "WORKER_GEN_TIMEOUT=3600\n" % (api, wid, tag, base)
        )
        put_text(sftp, env, base + "/env")
        print("site", inst, "->", api, "tag", tag)

    # 4) extra_model_paths
    put_text(sftp, EXTRA_PATHS, "/opt/comfyui/ComfyUI/extra_model_paths.yaml")
    print("extra_model_paths.yaml written")

    # 5) systemd units (root)
    for unit in ("comfyui.service", "custom-video-worker@.service"):
        tmp = "/tmp/" + unit
        sftp.put(os.path.join(HERE, unit), tmp)
        rc, o, e = sh(c, "install -m 644 %s /etc/systemd/system/%s" % (tmp, unit), sudo=True)
        print("unit", unit, "rc", rc, e.strip()[:120])
    rc, o, e = sh(c, "systemctl daemon-reload && systemctl enable comfyui", sudo=True)
    print("daemon-reload/enable rc", rc, o.strip()[:200], e.strip()[:200])

    sftp.close()
    c.close()
    print("=== deploy done ===")


if __name__ == "__main__":
    main()
