#!/usr/bin/env python3
"""
channel-worker Python 版 — HTTP 源 + 单 ffmpeg 永不重启 + RTMP 推流（AI 电脑端）

架构：AI 电脑运行本进程，ffmpeg 编码（NVENC，无 N 卡则降级 libx264）后推 RTMP
到服务器端 SRS，SRS 再转 HLS 由 Nginx 分发。

【无缝切流核心】
  单 ffmpeg 进程从 worker 内嵌的 HTTP 源持续读取视频，永不重启。
  - 默认：HTTP 源循环返回默认播单的 TS 文件。
  - 礼物：收到关键词 → 礼物视频入队 → ffmpeg 下次 EOF 重连时 HTTP 源返回礼物视频。
  - 切换发生在 HTTP 服务层，ffmpeg 和 SRS 全程无感知，零断流、零卡顿。
  - 命门参数：-reconnect_at_eof 1（ffmpeg 读到 EOF 自动重连发起新请求）。

一台 AI 电脑负责一个房间：通过 --room=N 指定，本机在 start_streams.bat 中配置。
"""

import argparse
import hashlib
import http.server
import json
import os
import re
import signal
import subprocess
import sys
import threading
import time
import urllib.request

import pymysql
import redis

# ─── 常量 ───────────────────────────────────────────────────────────────

HEARTBEAT_INTERVAL = 60      # 心跳日志间隔（秒）
LOOP_INTERVAL = 0.5          # 主循环 sleep（秒）
RTMP_CONNECT_TIMEOUT = 20    # 等待 RTMP 连接建立的最长时间（秒）
FFMPEG_RESTART_INTERVAL = 1800  # 定期重启 ffmpeg 的间隔（秒，30分钟）
PLAYLIST_REFRESH_INTERVAL = 300  # 定期刷新播单的间隔（秒，5分钟），后台改播单后自动生效
GIFT_QUEUE_MAX = 2              # 礼物视频队列上限：防连击排队数十秒导致卡死观感

# TS 预转统一规格（保证 HTTP 源拼接无花屏；实际规格可由环境变量覆盖）
TS_SCALE = os.environ.get('TS_SCALE', '360:640')   # 输出分辨率（降画质省带宽）
TS_FPS = os.environ.get('TS_FPS', '24')            # 输出帧率
TS_CQ = os.environ.get('TS_CQ', '26')              # 预转质量（nvenc cq / x264 crf）
# copy 推流开关：1 = 推流时不再重编码（预转 TS 已是目标规格），直播中 CPU≈0
PUSH_COPY = os.environ.get('PUSH_COPY', '') == '1'

# ─── 全局状态 ─────────────────────────────────────────────────────────────

ffmpeg_procs = {}   # pid → Popen
terminating = False

# HTTP 源状态：default_idx(默认播单指针) + gift_queue(礼物视频TS路径队列)
# 由主循环写入、HTTP Handler 读取，用锁保护
source_state = {
    'default_idx': 0,
    'gift_queue': [],
    'default_ts': [],   # 默认播单的 TS 路径列表
}
source_lock = threading.Lock()


# ─── 配置 ───────────────────────────────────────────────────────────────

def load_config():
    _here = os.path.dirname(os.path.abspath(__file__))          # .../python
    _worker_dir = os.path.dirname(_here)                         # .../channel-worker
    _project_root = os.path.dirname(os.path.dirname(_worker_dir))  # .../douyin
    return {
        'ffmpeg_bin': os.environ.get('FFMPEG_BIN', 'ffmpeg'),
        'ffprobe_bin': os.environ.get('FFPROBE_BIN', 'ffprobe'),
        'media_base_dir': os.environ.get('MEDIA_BASE_DIR', _project_root),
        'runtime_dir': os.environ.get('RUNTIME_DIR', os.path.join(_worker_dir, 'runtime')),
        # HTTP 源服务端口（本机，供 ffmpeg 读取）
        'http_port': int(os.environ.get('HTTP_PORT', '8123')),
        # RTMP 推流目标（服务器 SRS）。完整 URL = {RTMP_BASE}/{STREAM_APP}/{roomId}
        'rtmp_base': os.environ.get('RTMP_BASE', 'rtmp://127.0.0.1:1935'),
        'stream_app': os.environ.get('STREAM_APP', 'room'),
        # SRS HTTP API（用于探活推流是否上线）
        'srs_api_host': os.environ.get('SRS_API_HOST', '127.0.0.1'),
        'srs_api_port': int(os.environ.get('SRS_API_PORT', '1985')),
        # 服务器 PHP API（上报视频清单 + 鉴权）
        'server_api_base': os.environ.get('SERVER_API_BASE', 'http://127.0.0.1:8001/api'),
        'server_api_key': os.environ.get('SERVER_API_KEY', 'live-ai-api-key-2026'),
        'machine_id': os.environ.get('MACHINE_ID', ''),  # 留空则用 room{N}
        # 本地视频目录（上报扫描根目录，通常是 media_base_dir 下的视频成品）
        'scan_dir': os.environ.get('SCAN_DIR', ''),  # 留空则用 media_base_dir/视频成品
        'db': {
            'host': os.environ.get('DB_HOST', '127.0.0.1'),
            'port': int(os.environ.get('DB_PORT', '3306')),
            'database': os.environ.get('DB_NAME', 'zhibo'),
            'user': os.environ.get('DB_USER', 'zhibo'),
            'password': os.environ.get('DB_PASSWORD', '12345678'),
            'charset': os.environ.get('DB_CHARSET', 'utf8mb4'),
        },
        'redis': {
            'host': os.environ.get('REDIS_HOST', '127.0.0.1'),
            'port': int(os.environ.get('REDIS_PORT', '6379')),
            'password': os.environ.get('REDIS_PASSWORD', ''),
            'db': int(os.environ.get('REDIS_SELECT', '0')),
        },
    }


def log(msg):
    ts = time.strftime('%H:%M:%S')
    sys.stderr.write(f'[{ts}] {msg}\n')
    sys.stderr.flush()


# ─── 数据库 ─────────────────────────────────────────────────────────────

class Database:
    def __init__(self, config):
        self.config = config
        self.conn = None

    def _ensure(self):
        if self.conn is None:
            self.conn = self._connect()
            return
        try:
            self.conn.ping()
        except Exception:
            self.conn = self._connect()

    def _connect(self):
        return pymysql.connect(
            host=self.config['host'], port=self.config['port'],
            database=self.config['database'], user=self.config['user'],
            password=self.config['password'], charset=self.config['charset'],
            autocommit=True,
            cursorclass=pymysql.cursors.DictCursor,
        )

    def query(self, sql, params=None):
        self._ensure()
        with self.conn.cursor() as cur:
            cur.execute(sql, params or ())
            return cur.fetchall()


# ─── 播单仓库 ────────────────────────────────────────────────────────────

class PlaylistRepository:
    def __init__(self, db, media_base_dir='', ffprobe_bin='ffprobe'):
        self.db = db
        self.media_base_dir = media_base_dir
        self.ffprobe_bin = ffprobe_bin

    def room_stream_info(self, room_id):
        rows = self.db.query(
            "SELECT rb.room_id, rb.persona, st.webrtc_app, st.stream_alias_prefix "
            "FROM lp_room_binding rb "
            "LEFT JOIN lp_stream_template st ON st.id = rb.stream_template_id "
            "WHERE rb.room_id = %s LIMIT 1", (room_id,))
        if not rows:
            raise RuntimeError(f"房间 {room_id} 未配置流绑定")
        row = rows[0]
        if not row['persona']:
            raise RuntimeError(f"房间 {room_id} 未配置人设(persona)")
        prefix = row['stream_alias_prefix'] or 'room'
        return {
            'room_id': room_id, 'persona': row['persona'],
            'stream_alias': f"{prefix}/{room_id}",
        }

    def room_playlist_videos(self, room_id):
        rows = self.db.query(
            "SELECT rb.playlist_template_id FROM lp_room_binding rb WHERE rb.room_id = %s",
            (room_id,))
        if not rows:
            return []
        tpl_id = rows[0]['playlist_template_id'] or 0
        if tpl_id <= 0:
            return []
        videos = self.db.query(
            "SELECT ma.id, ma.asset_code, ma.title, ma.file_url, ma.duration_ms, ma.keywords "
            "FROM lp_playlist_template_item pti "
            "JOIN lp_media_asset ma ON ma.id = pti.asset_id "
            "WHERE pti.template_id = %s AND ma.asset_type = 'video' AND ma.status = 1 "
            "ORDER BY pti.seq ASC, pti.id ASC", (tpl_id,))
        return [self._fmt(v) for v in videos]

    def random_video(self, persona):
        rows = self.db.query(
            "SELECT id, asset_code, title, file_url, duration_ms, keywords "
            "FROM lp_media_asset WHERE persona = %s AND asset_type = 'video' AND status = 1 "
            "ORDER BY RAND() LIMIT 1", (persona,))
        return self._fmt(rows[0]) if rows else None

    def random_video_by_keyword(self, persona, keyword):
        rows = self.db.query(
            "SELECT id, asset_code, title, file_url, duration_ms, keywords "
            "FROM lp_media_asset WHERE persona = %s AND asset_type = 'video' AND status = 1 "
            "AND FIND_IN_SET(%s, REPLACE(keywords, ' ', '')) > 0 "
            "ORDER BY RAND() LIMIT 1", (persona, keyword))
        if rows:
            return self._fmt(rows[0])
        rows = self.db.query(
            "SELECT id, asset_code, title, file_url, duration_ms, keywords "
            "FROM lp_media_asset WHERE asset_type = 'video' AND status = 1 "
            "AND FIND_IN_SET(%s, REPLACE(keywords, ' ', '')) > 0 "
            "ORDER BY RAND() LIMIT 1", (keyword,))
        return self._fmt(rows[0]) if rows else None

    def all_keyword_videos(self, persona):
        rows = self.db.query(
            "SELECT id, title, file_url FROM lp_media_asset "
            "WHERE persona = %s AND asset_type = 'video' AND status = 1 "
            "AND keywords <> ''", (persona,))
        return rows

    def _fmt(self, row):
        return {
            'id': row['id'], 'title': row['title'],
            'file_url': self._resolve_path(row['file_url'] or ''),
            'duration_ms': row['duration_ms'] or 0,
        }

    def _resolve_path(self, file_url):
        file_url = file_url.strip()
        if not file_url:
            return ''
        if re.match(r'^[a-zA-Z]:[\\/]', file_url):
            return file_url.replace('/', os.sep)
        if file_url.startswith('http://') or file_url.startswith('https://'):
            from urllib.parse import urlparse
            path = urlparse(file_url).path
            if path and '/storage/' in path:
                base = os.path.dirname(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))
                return os.path.join(base, 'php', 'public', path.lstrip('/'))
        if self.media_base_dir:
            return os.path.join(self.media_base_dir, file_url.replace('/', os.sep))
        return file_url


# ─── Redis ──────────────────────────────────────────────────────────────

class RedisConsumer:
    def __init__(self, config):
        self.config = config
        self.rds = None

    def connect(self):
        try:
            self.rds = redis.Redis(
                host=self.config['host'], port=self.config['port'],
                password=self.config['password'] or None, db=self.config['db'],
                socket_timeout=3, socket_connect_timeout=3)
            self.rds.ping()
            log("Redis connected")
        except Exception as e:
            log(f"Redis connect failed: {e}")
            self.rds = None

    def consume_keyword(self, room_id):
        if self.rds is None:
            self.connect()
            if self.rds is None:
                return None
        try:
            raw = self.rds.lpop(f"list:keyword:room:{room_id}")
            if raw is None:
                return None
            data = json.loads(raw)
            kw = data.get('params', {}).get('keyword', '')
            return kw if kw else None
        except Exception:
            self.rds = None
            return None


# ─── 视频清单上报 ─────────────────────────────────────────────────────────

def sha1_of_file(path, chunk_size=1024 * 1024):
    """计算文件 sha1（用于去重）"""
    h = hashlib.sha1()
    try:
        with open(path, 'rb') as f:
            while True:
                chunk = f.read(chunk_size)
                if not chunk:
                    break
                h.update(chunk)
        return h.hexdigest()
    except Exception:
        return ''


def probe_duration_ms(ffprobe_bin, path):
    """ffprobe 探测时长（毫秒），失败返回 0"""
    try:
        r = subprocess.run(
            [ffprobe_bin, '-v', 'error', '-show_entries', 'format=duration',
             '-of', 'csv=p=0', path],
            capture_output=True, text=True, timeout=10)
        if r.stdout.strip():
            return int(float(r.stdout.strip()) * 1000)
    except Exception:
        pass
    return 0


def scan_local_videos(config):
    """扫描本地视频目录，返回 [{file_name, file_url, duration_ms, checksum}]。
    file_url 为相对 MEDIA_BASE_DIR 的路径（与 worker 播放时的 _resolve_path 对齐）。
    """
    media_base = config['media_base_dir']
    scan_dir = config['scan_dir'] or os.path.join(media_base, '视频成品')
    if not os.path.isdir(scan_dir):
        log(f"[上报] 扫描目录不存在: {scan_dir}")
        return []

    videos = []
    for name in os.listdir(scan_dir):
        if not name.lower().endswith(('.mp4', '.mov', '.mkv', '.avi', '.flv')):
            continue
        full = os.path.join(scan_dir, name)
        if not os.path.isfile(full):
            continue
        # file_url = 相对 media_base 的路径（用正斜杠，与 DB 现有格式一致）
        rel = os.path.relpath(full, media_base).replace('\\', '/')
        duration_ms = probe_duration_ms(config['ffprobe_bin'], full)
        checksum = sha1_of_file(full)
        videos.append({
            'file_name': name,
            'file_url': rel,
            'duration_ms': duration_ms,
            'checksum': checksum,
        })
    return videos


def report_videos_to_server(config, room_id, videos):
    """HTTP POST 上报视频清单到服务器 MachineAsset/report。
    失败不致命（推流不依赖上报成功），只打日志。
    """
    if not videos:
        log("[上报] 本地无视频可上报")
        return False

    base = config['server_api_base'].rstrip('/')
    url = f"{base}/machineAsset/report"
    machine_id = config['machine_id'] or f"room{room_id}"

    payload = json.dumps({
        'room_id': room_id,
        'videos': videos,
    }).encode('utf-8')

    req = urllib.request.Request(url, data=payload, method='POST')
    req.add_header('Content-Type', 'application/json')
    req.add_header('X-Api-Key', config['server_api_key'])
    req.add_header('X-Worker-Id', machine_id)

    try:
        with urllib.request.urlopen(req, timeout=60) as resp:
            body = resp.read().decode('utf-8', errors='replace')
            data = json.loads(body)
            if str(data.get('code', '')) == '00000' or data.get('code') == 0:
                d = data.get('data', {}) or {}
                log(f"[上报] 成功: 新增 {d.get('inserted', 0)}，跳过 {d.get('skipped', 0)}，"
                    f"persona={d.get('persona', '')} machine={d.get('machine', '')}")
                errs = d.get('errors', [])
                for e in errs[:3]:
                    log(f"[上报] 错误: {e}")
                return True
            else:
                log(f"[上报] 服务器返回失败: {data.get('msg', body[:200])}")
                return False
    except Exception as e:
        log(f"[上报] 请求异常（不致命）: {e}")
        return False




def ts_cache_path(config, source_path):
    """源 mp4 对应的预转 TS 缓存路径。
    缓存键 = 源文件【相对 MEDIA_BASE_DIR 的路径】+ 规格的 hash：
    绝对路径不参与哈希 → AI 电脑预转的 ts_cache 上传服务器后直接可用。"""
    try:
        rel = os.path.relpath(source_path, config['media_base_dir'])
        # 不在 media_base_dir 下的文件退回绝对路径，保证仍能工作
        if rel.startswith('..'):
            rel = source_path
        rel = rel.replace('\\', '/')
    except Exception:
        rel = source_path
    key = f"{rel}|{TS_SCALE}|{TS_FPS}"
    digest = hashlib.md5(key.encode('utf-8')).hexdigest()[:12]
    base = os.path.basename(source_path)
    name, _ = os.path.splitext(base)
    # 文件名可能含特殊字符，用 hash 为主名保证安全
    return os.path.join(config['runtime_dir'], 'ts_cache', f"{name}_{digest}.ts")


_TS_NVENC_CACHE = {}

def _ts_nvenc_ok(config):
    """探测（并缓存）本机 NVENC 是否可用于预转码。
    注意：ffmpeg 二进制里含 h264_nvenc 字符串 ≠ 有可用 N 卡，
    用 1 帧实 Encoding 探测；TS_NO_NVENC=1 可强制走 libx264。"""
    if os.environ.get('TS_NO_NVENC', '') == '1':
        return False
    key = config['ffmpeg_bin']
    if key not in _TS_NVENC_CACHE:
        _TS_NVENC_CACHE[key] = detect_nvenc(config['ffmpeg_bin'])
    return _TS_NVENC_CACHE[key]


def ensure_ts(config, source_path):
    """确保源 mp4 已预转为统一规格 TS；返回 TS 路径，失败返回 None。"""
    if not source_path or not os.path.exists(source_path):
        return None

    ts_path = ts_cache_path(config, source_path)
    if os.path.exists(ts_path) and os.path.getsize(ts_path) > 0:
        return ts_path  # 缓存命中

    os.makedirs(os.path.dirname(ts_path), exist_ok=True)
    log(f"[预转] {os.path.basename(source_path)} → TS({TS_SCALE}@{TS_FPS})")
    venc = (['-c:v', 'h264_nvenc', '-preset', 'p4', '-rc', 'vbr', '-cq', TS_CQ, '-b:v', '0']
            if _ts_nvenc_ok(config) else
            ['-c:v', 'libx264', '-preset', 'ultrafast', '-crf', TS_CQ])
    cmd = [
        config['ffmpeg_bin'], '-hide_banner', '-y', '-loglevel', 'error',
        '-i', source_path,
        '-vf', f'scale={TS_SCALE}',
        '-r', TS_FPS,
        *venc,
        '-pix_fmt', 'yuv420p',
        '-c:a', 'aac', '-b:a', '96k', '-ar', '44100',
        '-f', 'mpegts',
        ts_path,
    ]
    try:
        r = subprocess.run(cmd, capture_output=True, text=True, timeout=120)
        if r.returncode != 0 or not os.path.exists(ts_path):
            log(f"[预转] 失败: {r.stderr[-300:] if r.stderr else '未知错误'}")
            return None
        return ts_path
    except Exception as e:
        log(f"[预转] 异常: {e}")
        return None


# ─── HTTP 源服务 ─────────────────────────────────────────────────────────

class StreamSourceHandler(http.server.BaseHTTPRequestHandler):
    """每次 GET /stream 返回一个完整 TS 文件后结束响应（发 EOF）。
    ffmpeg 靠 -reconnect_at_eof 重连发起新请求拿到下一个视频。
    worker 通过 source_state 控制每次返回什么 → 实现无缝切流。"""

    def log_message(self, fmt, *args):
        pass  # 静默默认访问日志

    def do_GET(self):
        if self.path != '/stream':
            self.send_error(404)
            return

        # 决定本次返回哪个视频
        with source_lock:
            if source_state['gift_queue']:
                video_ts = source_state['gift_queue'].pop(0)
                tag = '礼物'
            else:
                default_ts = source_state['default_ts']
                if not default_ts:
                    self.send_response(204)
                    self.end_headers()
                    return
                idx = source_state['default_idx']
                video_ts = default_ts[idx % len(default_ts)]
                source_state['default_idx'] = idx + 1
                tag = '默认'

        if not video_ts or not os.path.exists(video_ts):
            log(f"[HTTP] TS 不存在: {video_ts}，返回204触发重连跳过")
            self.send_response(204)
            self.end_headers()
            return

        file_size = os.path.getsize(video_ts)
        log(f"[HTTP] 提供({tag}): {os.path.basename(video_ts)} ({file_size // 1024}KB)")

        self.send_response(200)
        self.send_header('Content-Type', 'video/mp2t')
        self.send_header('Content-Length', str(file_size))
        self.end_headers()

        try:
            with open(video_ts, 'rb') as f:
                while True:
                    chunk = f.read(65536)
                    if not chunk:
                        break
                    self.wfile.write(chunk)
            self.wfile.flush()
        except (BrokenPipeError, ConnectionResetError):
            log("[HTTP] 客户端断开")


def run_http_source(config):
    """启动 HTTP 源服务（后台线程，daemon）"""
    httpd = http.server.ThreadingHTTPServer(('127.0.0.1', config['http_port']), StreamSourceHandler)
    log(f"[HTTP] 源服务启动 http://127.0.0.1:{config['http_port']}/stream")
    httpd.serve_forever()


def enqueue_gift(ts_path):
    """主循环调用：把礼物视频 TS 压入队列，HTTP 源下次请求即返回"""
    with source_lock:
        source_state['gift_queue'].append(ts_path)
    log(f"[礼物] 入队: {os.path.basename(ts_path)}")


# ─── ffmpeg 推流 ─────────────────────────────────────────────────────────

def detect_nvenc(ffmpeg_bin):
    """探测 ffmpeg 是否支持 h264_nvenc（N 卡硬件编码）：
    用 lavfi 生成 1 帧真实编码验证，避免无 GPU 但编译含 nvenc 的误判。"""
    try:
        r = subprocess.run(
            [ffmpeg_bin, '-hide_banner', '-loglevel', 'error',
             '-f', 'lavfi', '-i', 'nullsrc=s=64x64:d=0.1',
             '-c:v', 'h264_nvenc', '-f', 'null', '-'],
            capture_output=True, text=True, timeout=15)
        return r.returncode == 0
    except Exception:
        return False


def build_rtmp_command(ffmpeg_bin, http_url, rtmp_url, use_nvenc):
    """构造推流命令：从 HTTP 源读取 → NVENC/libx264 编码 → RTMP。
    命门：-reconnect_at_eof 1（EOF 自动重连，保证单进程持续推流不退出）。
    """
    cmd = [
        ffmpeg_bin, '-hide_banner', '-y', '-re',
        '-reconnect', '1',
        '-reconnect_streamed', '1',
        '-reconnect_at_eof', '1',
        '-reconnect_delay_max', '1',
        '-i', http_url,
        '-fflags', '+genpts',  # 重新生成 PTS，配合自动 discontinuity 补偿保证时间戳连续
    ]

    if PUSH_COPY:
        # copy 模式：预转 TS 已是目标规格（h264+aac，FLV 兼容），纯转封装。
        # 直播中 CPU≈0，码率由预转参数（TS_CQ/TS_SCALE）唯一决定。
        # 注意：音视频都 copy，音频参数在此分支内给定，避免与重编码分支冲突。
        cmd += ['-c:v', 'copy', '-c:a', 'copy',
                '-max_muxing_queue_size', '4096']
        cmd += ['-f', 'flv', '-flvflags', 'no_duration_filesize', rtmp_url]
        return cmd

    if use_nvenc:
        cmd += ['-c:v', 'h264_nvenc', '-preset', 'p4', '-b:v', '800k',
                '-maxrate', '1000k', '-bufsize', '2000k',
                '-g', '48', '-bf', '0', '-pix_fmt', 'yuv420p']
    else:
        cmd += ['-c:v', 'libx264', '-preset', 'ultrafast', '-tune', 'zerolatency',
                '-crf', '30', '-maxrate', '1000k', '-bufsize', '2000k',
                '-g', '48', '-keyint_min', '48', '-sc_threshold', '0',
                '-pix_fmt', 'yuv420p']

    cmd += ['-c:a', 'aac', '-b:a', '96k', '-ar', '44100',
            '-max_muxing_queue_size', '4096',
            '-f', 'flv', '-flvflags', 'no_duration_filesize',
            rtmp_url]
    return cmd


def start_ffmpeg(config, http_url, rtmp_url, use_nvenc):
    """启动单 ffmpeg 推流进程，返回 active 句柄"""
    cmd = build_rtmp_command(config['ffmpeg_bin'], http_url, rtmp_url, use_nvenc)
    log(f"[FFMPEG] 启动 → {rtmp_url} (nvenc={use_nvenc})")
    proc = subprocess.Popen(cmd, stdout=subprocess.DEVNULL, stderr=sys.stderr)
    ffmpeg_procs[proc.pid] = proc
    return {'proc': proc, 'pid': proc.pid, 'started_at': time.time()}


# ─── SRS 探活 ────────────────────────────────────────────────────────────

def stream_online_on_srs(config, stream_alias):
    """查询 SRS HTTP API，确认指定 stream 是否已上线"""
    try:
        url = f"http://{config['srs_api_host']}:{config['srs_api_port']}/api/v1/streams/"
        req = urllib.request.Request(url, headers={'Accept': 'application/json'})
        with urllib.request.urlopen(req, timeout=3) as resp:
            data = json.loads(resp.read().decode())
        app, name = (stream_alias.split('/') + [''])[:2]
        for st in (data.get('streams') or []):
            if st.get('app') == app and str(st.get('name')) == name:
                return True
        return False
    except Exception:
        return False


# ─── 进程管理 ────────────────────────────────────────────────────────────

def kill_all_children():
    for pid, proc in list(ffmpeg_procs.items()):
        try:
            proc.kill()
            proc.wait(timeout=2)
        except Exception:
            pass
    ffmpeg_procs.clear()


def kill_child(pid):
    proc = ffmpeg_procs.pop(pid, None)
    if proc:
        try:
            proc.kill()
            proc.wait(timeout=2)
        except Exception:
            pass


# ─── 信号处理 ────────────────────────────────────────────────────────────

def on_signal(signum, frame):
    global terminating
    terminating = True
    log(f"收到信号 {signum}，正在退出...")
    kill_all_children()
    sys.exit(0)


# ─── 主流程 ──────────────────────────────────────────────────────────────

def main():
    global terminating

    parser = argparse.ArgumentParser(description='HLS channel worker (HTTP source + RTMP push)')
    parser.add_argument('--room', type=int, required=True, help='房间 ID')
    args = parser.parse_args()
    room_id = args.room

    config = load_config()
    signal.signal(signal.SIGTERM, on_signal)
    signal.signal(signal.SIGINT, on_signal)

    db = Database(config['db'])
    repo = PlaylistRepository(db, config['media_base_dir'], config['ffprobe_bin'])
    rds = RedisConsumer(config['redis'])

    # 1. 读房间配置
    try:
        room_info = repo.room_stream_info(room_id)
    except Exception as e:
        log(f"获取房间信息失败: {e}")
        time.sleep(5)
        return

    persona = room_info['persona']
    stream_alias = room_info['stream_alias']
    rtmp_url = f"{config['rtmp_base'].rstrip('/')}/{config['stream_app']}/{room_id}"
    http_url = f"http://127.0.0.1:{config['http_port']}/stream"

    # 2. 探测 NVENC
    use_nvenc = detect_nvenc(config['ffmpeg_bin'])
    log(f"room={room_id} persona={persona} alias={stream_alias}")
    log(f"rtmp={rtmp_url} nvenc={use_nvenc} ts={TS_SCALE}@{TS_FPS}")

    # 2.5 上报本地视频清单到服务器（后台线程，不阻塞推流启动；失败不致命）
    def _report_in_background():
        videos = scan_local_videos(config)
        log(f"[上报] 扫描到 {len(videos)} 个本地视频，开始上报")
        report_videos_to_server(config, room_id, videos)
    threading.Thread(target=_report_in_background, daemon=True).start()

    # 3. 取默认播单，预转为 TS
    default_videos = repo.room_playlist_videos(room_id)
    if not default_videos:
        for _ in range(3):
            v = repo.random_video(persona)
            if v:
                default_videos.append(v)
    if not default_videos:
        log("无可用视频，5 秒后重试")
        time.sleep(5)
        return

    default_ts = []
    for v in default_videos:
        ts = ensure_ts(config, v['file_url'])
        if ts:
            default_ts.append(ts)
    if not default_ts:
        log("默认播单 TS 预转全部失败（检查 MEDIA_BASE_DIR / 素材文件），5 秒后重试")
        time.sleep(5)
        return

    with source_lock:
        source_state['default_ts'] = default_ts
        source_state['default_idx'] = 0
        source_state['gift_queue'] = []
    log(f"[启动] 默认播单 {len(default_ts)} 个 TS 就绪")

    # 3.5 后台预转全部礼物素材（消除送礼时的首次转码延迟）
    def _preconvert_gifts():
        try:
            gifts = repo.all_keyword_videos(persona)
            log(f"[预转] 礼物素材后台预转开始：{len(gifts)} 个")
            ok = 0
            for v in gifts:
                full_path = repo._resolve_path(v['file_url'])
                if ensure_ts(config, full_path):
                    ok += 1
            log(f"[预转] 礼物素材预转完成 {ok}/{len(gifts)}")
        except Exception as e:
            log(f"[预转] 礼物素材预转异常: {e}")
    threading.Thread(target=_preconvert_gifts, daemon=True).start()

    # 4. 启动 HTTP 源服务（后台线程，整个进程生命周期常驻）
    http_thread = threading.Thread(target=run_http_source, args=(config,), daemon=True)
    http_thread.start()
    time.sleep(1)

    # 5. 启动单 ffmpeg 推流（永不重启）
    active = start_ffmpeg(config, http_url, rtmp_url, use_nvenc)
    # 等待 RTMP 上线（超时不致命，主循环兜底）
    if stream_online_on_srs(config, stream_alias):
        log(f"[RTMP] 已上线: {stream_alias}")
    else:
        log(f"[RTMP] 上线探测未完成，继续运行由主循环兜底")

    rds.connect()
    last_hb = time.time()
    last_playlist_refresh = time.time()

    # 6. 主循环：监控 ffmpeg + 消费礼物关键词
    while True:
        # ffmpeg 死亡 → 重启（HTTP 源服务不重启，状态保留）
        if active['proc'].poll() is not None:
            log(f"[FFMPEG] PID={active['pid']} 退出(code={active['proc'].returncode})，重启推流")
            ffmpeg_procs.pop(active['pid'], None)
            time.sleep(3)  # 等 ffmpeg 完全退出，避免新旧进程竞争同一个 SRS 流
            active = start_ffmpeg(config, http_url, rtmp_url, use_nvenc)

        # 定期重启 ffmpeg（防止长时间运行时间戳 offset 累积过大导致频繁断连）
        now_ts = time.time()
        if now_ts - active['started_at'] > FFMPEG_RESTART_INTERVAL:
            log(f"[FFMPEG] 运行超 {FFMPEG_RESTART_INTERVAL}s，定期重启清零时间戳")
            kill_child(active['pid'])
            time.sleep(3)  # 等 ffmpeg 完全退出，避免新旧进程竞争同一个 SRS 流
            active = start_ffmpeg(config, http_url, rtmp_url, use_nvenc)

        # 消费礼物关键词 → 查视频 → 预转 TS → 入队
        while True:
            kw = rds.consume_keyword(room_id)
            if kw is None:
                break
            kw_v = repo.random_video_by_keyword(persona, kw)
            if not kw_v:
                log(f"[礼物] '{kw}' 无可用视频，忽略")
                continue
            ts = ensure_ts(config, kw_v['file_url'])
            if not ts:
                log(f"[礼物] '{kw}' 预转失败，忽略")
                continue
            with source_lock:
                q = source_state['gift_queue']
                if ts in q:
                    # 连击合并：同一礼物已在待播队列，不再重复排（否则N个礼物=N×10s连播，观众侧卡死观感）
                    log(f"[礼物] '{kw}' 连击合并（队列已有同视频，跳过）")
                    continue
                if len(q) >= GIFT_QUEUE_MAX:
                    log(f"[礼物] '{kw}' 队列已满({len(q)}/{GIFT_QUEUE_MAX})，丢弃")
                    continue
                q.append(ts)
                log(f"[礼物] '{kw}' 入队({len(q)}/{GIFT_QUEUE_MAX})")

        # 心跳日志
        now = time.time()
        if now - last_hb > HEARTBEAT_INTERVAL:
            online = stream_online_on_srs(config, stream_alias)
            with source_lock:
                qsize = len(source_state['gift_queue'])
                dsize = len(source_state['default_ts'])

            # 定期刷新播单（后台改 asset_ids 后自动生效）
            if now - last_playlist_refresh > PLAYLIST_REFRESH_INTERVAL:
                last_playlist_refresh = now
                try:
                    fresh_videos = repo.room_playlist_videos(room_id)
                    if fresh_videos:
                        fresh_ts = []
                        for v in fresh_videos:
                            ts = ensure_ts(config, v['file_url'])
                            if ts:
                                fresh_ts.append(ts)
                        if fresh_ts:
                            with source_lock:
                                old_set = set(source_state['default_ts'])
                                new_set = set(fresh_ts)
                                if new_set != old_set:
                                    log(f"[播单] 检测到播单变化: {len(old_set)} → {len(new_set)} 个视频，更新")
                                    source_state['default_ts'] = fresh_ts
                                    if source_state['default_idx'] >= len(fresh_ts):
                                        source_state['default_idx'] = 0
                except Exception as e:
                    log(f"[播单] 刷新异常（不致命）: {e}")
            log(f"[hb] pid={active['pid']} srs_online={online} default={dsize} gift_q={qsize}")
            last_hb = now

        time.sleep(LOOP_INTERVAL)
        if terminating:
            break


if __name__ == '__main__':
    main()
