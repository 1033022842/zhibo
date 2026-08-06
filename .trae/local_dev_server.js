/**
 * 本地开发 Mock 服务器
 * - 提供 /api/v1/feed/live 等 API（返回本地 HLS URL）
 * - 提供 /hls/ 静态文件（ffmpeg 生成的 .m3u8 + .ts）
 * 
 * 用法: node .trae/local_dev_server.js
 */
const http = require('http')
const fs = require('fs')
const path = require('path')
const url = require('url')

const HLS_DIR = path.resolve(__dirname, '..', 'services', 'channel-worker', 'test_hls')
const ROOM_ID = 1

// 模拟 API 响应
const API_HANDLERS = {
  'GET /api/v1/feed/live': () => ({
    code: 0,
    data: {
      list: [{
        room_id: ROOM_ID,
        room_no: 'TEST001',
        title: '本地测试房间',
        status: 1,
        cover_url: '',
        persona: { id: 1, name: '白毛女', tags: [] },
        display: { badge_text: '直播中', online_text: '1', like_text: '0' },
        state: { mode: 'public', privilege_active: false, privilege_expire_at: 0, room_group_code: '' },
        play: {
          stream_alias: 'room/' + ROOM_ID,
          webrtc_url: '',
          hls_url: 'http://localhost:3000/hls/room/' + ROOM_ID + '/index.m3u8',
          play_token: '',
          expire_at: Date.now() + 3600000,
        },
        interaction: { allow_chat: true, allow_like: true, allow_gift: true },
        gift_panel: { quick_gifts: [], currency_name: '钻石' },
        room_tags: [],
      }],
      cursor: null,
      has_more: false,
    }
  }),

  [`GET /api/v1/rooms/${ROOM_ID}`]: () => ({
    code: 0,
    data: {
      room_id: ROOM_ID,
      room_no: 'TEST001',
      title: '本地测试房间',
      status: 1,
      cover_url: '',
      persona: { id: 1, name: '白毛女', tags: [] },
      display: { badge_text: '直播中', online_text: '1', like_text: '0' },
      state: { mode: 'public', privilege_active: false, privilege_expire_at: 0, room_group_code: '' },
      play: {
        stream_alias: 'room/' + ROOM_ID,
        webrtc_url: '',
        hls_url: 'http://localhost:3000/hls/room/' + ROOM_ID + '/index.m3u8',
        play_token: '',
        expire_at: Date.now() + 3600000,
      },
      interaction: { allow_chat: true, allow_like: true, allow_gift: true },
      gift_panel: { quick_gifts: [], currency_name: '钻石' },
      room_tags: [],
      binding: null,
    }
  }),
}

// MIME types
const MIME = {
  '.m3u8': 'application/vnd.apple.mpegurl',
  '.ts': 'video/mp2t',
  '.json': 'application/json',
}

const server = http.createServer((req, res) => {
  const parsed = url.parse(req.url, true)
  const pathname = parsed.pathname

  // CORS
  res.setHeader('Access-Control-Allow-Origin', '*')
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
  res.setHeader('Access-Control-Allow-Headers', '*')

  if (req.method === 'OPTIONS') {
    res.writeHead(204)
    res.end()
    return
  }

  // API routes
  const apiKey = req.method + ' ' + pathname
  if (API_HANDLERS[apiKey]) {
    res.writeHead(200, { 'Content-Type': 'application/json' })
    res.end(JSON.stringify(API_HANDLERS[apiKey]()))
    return
  }

  // /hls/ 静态文件
  if (pathname.startsWith('/hls/')) {
    const filePath = path.join(HLS_DIR, pathname.replace(/^\/hls\//, ''))
    const ext = path.extname(filePath)
    
    try {
      const stat = fs.statSync(filePath)
      if (stat.isFile()) {
        res.writeHead(200, {
          'Content-Type': MIME[ext] || 'application/octet-stream',
          'Cache-Control': 'no-cache, no-store, must-revalidate',
          'Content-Length': stat.size,
        })
        fs.createReadStream(filePath).pipe(res)
        return
      }
    } catch (e) {
      // 404
    }
  }

  res.writeHead(404)
  res.end('Not Found: ' + pathname)
})

const PORT = 8000
server.listen(PORT, () => {
  console.log('Local dev server: http://localhost:' + PORT)
  console.log('  API:  http://localhost:' + PORT + '/api/v1/feed/live')
  console.log('  HLS:  http://localhost:' + PORT + '/hls/room/' + ROOM_ID + '/index.m3u8')
})
