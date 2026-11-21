import http from 'node:http'
import fs from 'node:fs'
import path from 'node:path'

const ROOT = path.resolve('D:/ever/douyin/douyin/ai-girl-malaysia.com')
const PORT = Number(process.env.PORT || 4180)
const TYPES = {
  '.html': 'text/html; charset=utf-8', '.js': 'text/javascript; charset=utf-8',
  '.css': 'text/css; charset=utf-8', '.json': 'application/json; charset=utf-8',
  '.webp': 'image/webp', '.png': 'image/png', '.jpg': 'image/jpeg', '.svg': 'image/svg+xml',
  '.mp4': 'video/mp4', '.mp3': 'audio/mpeg', '.woff2': 'font/woff2'
}

http.createServer((req, res) => {
  let url = decodeURIComponent((req.url || '/').split('?')[0])
  if (url === '/ai' || url === '/ai/') url = '/ai/createCharacter.html'
  if (url.startsWith('/ai/')) url = url.slice(3)
  if (url === '/') url = '/createCharacter.html'
  const file = path.join(ROOT, path.normalize(url).replace(/^([/\\])+/, ''))
  if (!file.startsWith(ROOT)) { res.writeHead(403); res.end('forbidden'); return }
  fs.readFile(file, (err, data) => {
    if (err) { res.writeHead(404, { 'content-type': 'text/plain' }); res.end('not found: ' + url); return }
    res.writeHead(200, { 'content-type': TYPES[path.extname(file).toLowerCase()] || 'application/octet-stream', 'cache-control': 'no-store' })
    res.end(data)
  })
}).listen(PORT, '127.0.0.1', () => console.log('serving http://127.0.0.1:' + PORT + '/ai/createCharacter.html'))
