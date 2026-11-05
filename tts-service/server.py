#!/usr/bin/env python3
"""Edge TTS 本地常驻服务

供 PHP（app/common/service/EdgeTtsService.php）调用：
    POST /tts   {"text": "...", "voice": "sweet"}  ->  audio/mpeg
    GET  /health                                    ->  {"ok": true, "voices": [...]}

只监听 127.0.0.1，不对外暴露；音色映射与创建角色页的 6 种女声保持一致。
依赖：python3 -m pip install edge-tts
"""
import asyncio
import json
import os
import re
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

import edge_tts

# 与 ai-girl-malaysia.com/js/createCharacter.js 的 VOICES 一一对应
# (Edge voice, rate, pitch) —— 试听样音 assets/create/voice-*.mp3 用同一组参数生成
VOICES = {
    "sweet":    ("en-US-AvaMultilingualNeural",      "+5%",  "+10Hz"),
    "mature":   ("fr-FR-VivienneMultilingualNeural", "-5%",  "-10Hz"),
    "magnetic": ("de-DE-SeraphinaMultilingualNeural", "-10%", "-25Hz"),
    "loli":     ("en-US-AnaNeural",                  "+8%",  "+25Hz"),
    "cold":     ("en-US-EmmaMultilingualNeural",     "-2%",  "-5Hz"),
    "gentle":   ("pt-BR-ThalitaMultilingualNeural",  "-8%",  "+5Hz"),
}
MAX_CHARS = 600
TIMEOUT = float(os.environ.get("EDGE_TTS_TIMEOUT", "15"))
TOKEN = os.environ.get("EDGE_TTS_TOKEN", "")
HOST = os.environ.get("EDGE_TTS_HOST", "127.0.0.1")
PORT = int(os.environ.get("EDGE_TTS_PORT", "8100"))


def clean_text(text: str) -> str:
    """去掉 Markdown 标记，避免把 ** / # / 链接念出来（与 PHP 侧 clean 保持一致）"""
    text = re.sub(r"```[\s\S]*?```", " ", text)
    text = re.sub(r"\[([^\]]*)\]\([^)]*\)", r"\1", text)
    for ch in ("**", "__", "`", "#", ">", "*", "~"):
        text = text.replace(ch, "")
    text = re.sub(r"\s+", " ", text)
    return text.strip()


async def synth(text: str, preset: str) -> bytes:
    voice, rate, pitch = VOICES[preset]
    communicate = edge_tts.Communicate(text, voice, rate=rate, pitch=pitch)
    chunks = []
    async for chunk in communicate.stream():
        if chunk.get("type") == "audio" and chunk.get("data"):
            chunks.append(chunk["data"])
    return b"".join(chunks)


class Handler(BaseHTTPRequestHandler):
    server_version = "EdgeTTS/1.0"

    def log_message(self, fmt, *args):
        print("[edge-tts] " + (fmt % args), flush=True)

    def send_json(self, code: int, payload: dict):
        body = json.dumps(payload, ensure_ascii=False).encode("utf-8")
        self.send_response(code)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def do_GET(self):
        if self.path.split("?")[0] == "/health":
            self.send_json(200, {"ok": True, "voices": sorted(VOICES.keys())})
            return
        self.send_json(404, {"error": "not found"})

    def do_POST(self):
        if self.path.split("?")[0] != "/tts":
            self.send_json(404, {"error": "not found"})
            return
        if TOKEN and self.headers.get("X-TTS-Token", "") != TOKEN:
            self.send_json(403, {"error": "forbidden"})
            return
        try:
            length = int(self.headers.get("Content-Length") or 0)
        except ValueError:
            length = 0
        if length <= 0 or length > 20000:
            self.send_json(400, {"error": "invalid body"})
            return
        try:
            data = json.loads(self.rfile.read(length).decode("utf-8"))
        except Exception:
            self.send_json(400, {"error": "invalid json"})
            return

        text = clean_text(str(data.get("text", "")))
        if text == "":
            self.send_json(400, {"error": "empty text"})
            return
        if len(text) > MAX_CHARS:
            text = text[:MAX_CHARS]

        preset = str(data.get("voice", "")).strip().lower()
        if preset not in VOICES:
            preset = "sweet"

        try:
            audio = asyncio.run(asyncio.wait_for(synth(text, preset), timeout=TIMEOUT))
        except Exception as exc:  # 上游抖动/断网：交给 PHP 回退 xAI
            self.log_message("synth failed preset=%s err=%s", preset, str(exc)[:160])
            self.send_json(502, {"error": "tts failed"})
            return
        if not audio:
            self.send_json(502, {"error": "empty audio"})
            return

        self.send_response(200)
        self.send_header("Content-Type", "audio/mpeg")
        self.send_header("Content-Length", str(len(audio)))
        self.send_header("Cache-Control", "no-store")
        self.end_headers()
        self.wfile.write(audio)


if __name__ == "__main__":
    print("[edge-tts] listening on %s:%d, voices=%s" % (HOST, PORT, ",".join(sorted(VOICES))), flush=True)
    ThreadingHTTPServer((HOST, PORT), Handler).serve_forever()
