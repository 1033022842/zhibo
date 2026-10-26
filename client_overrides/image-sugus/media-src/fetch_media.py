#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Pull the genspicy template clips onto the sugus site.

Runs ON the sugus box. Reads clips manifest, downloads each video + corner
image from the public CDN, extracts a poster frame with ffmpeg, and writes
everything under <site>/media/gs/ along with a trimmed clips.json for the
frontend. Re-running skips files that already exist.
"""
import json
import os
import subprocess
import sys
import urllib.parse
import urllib.request
from concurrent.futures import ThreadPoolExecutor, as_completed

SITE = "/www/wwwroot/douyin/ai-girl-malaysia.com"
OUT = os.path.join(SITE, "media", "gs")
MANIFEST = os.environ.get("GS_MANIFEST", "/tmp/gs_deploy_manifest.json")
UA = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36"


def fetch(url, dest, timeout=180):
    # Some CDN keys contain non-ASCII (en dash, CJK); percent-encode the path
    # so urllib's ASCII-only header handling doesn't choke.
    safe = urllib.parse.quote(url, safe=":/?&=%#@+~")
    req = urllib.request.Request(safe, headers={"User-Agent": UA})
    with urllib.request.urlopen(req, timeout=timeout) as r:
        data = r.read()
    if len(data) < 1024:
        raise IOError("too small: %d bytes" % len(data))
    tmp = dest + ".part"
    with open(tmp, "wb") as f:
        f.write(data)
    os.replace(tmp, dest)
    return len(data)


def make_poster(video, poster):
    subprocess.run(["ffmpeg", "-v", "error", "-y", "-ss", "1.5", "-i", video,
                    "-frames:v", "1", "-vf", "scale=480:-2", "-q:v", "4", poster],
                   check=True, capture_output=True)


def main():
    data = json.load(open(MANIFEST))
    clips = data["clips"]
    os.makedirs(OUT, exist_ok=True)

    jobs = []
    for c in clips:
        if c["kind"] == "video" and c.get("src"):
            jobs.append(("video", c["src"], os.path.join(OUT, c["video"])))
        elif c["kind"] == "image" and c.get("src"):
            # still-image template: the "clip" itself is a picture
            jobs.append(("image", c["src"], os.path.join(OUT, c["image"])))
        for src, name in zip(c.get("corner_srcs") or [], c.get("corners") or []):
            jobs.append(("corner", src, os.path.join(OUT, name)))

    print("download jobs:", len(jobs))
    ok = skip = fail = 0
    failures = []

    def run(job):
        kind, url, dest = job
        if os.path.exists(dest) and os.path.getsize(dest) > 1024:
            return kind, dest, 0, "skip"
        try:
            n = fetch(url, dest)
            return kind, dest, n, "ok"
        except Exception as exc:  # noqa: BLE001
            return kind, dest, 0, "fail: %s" % exc

    with ThreadPoolExecutor(max_workers=6) as pool:
        futs = [pool.submit(run, j) for j in jobs]
        for i, f in enumerate(as_completed(futs), 1):
            kind, dest, n, status = f.result()
            if status == "ok":
                ok += 1
                print("  [%3d/%d] %7.1f KB %s" % (i, len(jobs), n / 1024, os.path.basename(dest)))
            elif status == "skip":
                skip += 1
            else:
                fail += 1
                failures.append({"dest": dest, "error": status})
                print("  [%3d/%d] FAIL %s :: %s" % (i, len(jobs), os.path.basename(dest), status))

    # posters (and the single image-kind card's still)
    print("posters...")
    for c in clips:
        poster_path = os.path.join(OUT, c["poster"])
        if os.path.exists(poster_path) and os.path.getsize(poster_path) > 512:
            continue
        if c["kind"] == "video":
            src = os.path.join(OUT, c["video"])
            if os.path.exists(src):
                try:
                    make_poster(src, poster_path)
                except Exception as exc:  # noqa: BLE001
                    print("  poster failed", c["id"], exc)
        else:
            img = os.path.join(OUT, c.get("image") or "")
            if os.path.exists(img):
                subprocess.run(["ffmpeg", "-v", "error", "-y", "-i", img,
                                "-vf", "scale=480:-2", "-q:v", "4", poster_path],
                               capture_output=True)

    # frontend manifest: only what the page needs
    front = {"count": len(clips), "clips": [{
        "id": c["id"], "title": c["title"], "creator": c["creator"],
        "duration": c.get("duration"), "w": c["w"], "h": c["h"],
        "kind": c["kind"], "video": c["video"], "image": c["image"],
        "poster": c["poster"], "corners": c["corners"], "tags": c.get("tags") or [],
    } for c in clips]}
    with open(os.path.join(OUT, "clips.json"), "w", encoding="utf-8") as f:
        json.dump(front, f, ensure_ascii=False, indent=1)

    print("done: %d new, %d skipped, %d failed" % (ok, skip, fail))
    print("wrote", os.path.join(OUT, "clips.json"))
    return 1 if failures else 0


if __name__ == "__main__":
    sys.exit(main())
