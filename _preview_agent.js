// 临时预览服务器：静态服务 ai-girl-malaysia.com + /api 反向代理到 ThinkPHP
const http = require('http');
const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, 'ai_web');
const PORT = 8080;
const API_TARGET = { hostname: '127.0.0.1', port: 8001 };

const MIME = {
  '.html': 'text/html; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.js': 'application/javascript; charset=utf-8',
  '.mjs': 'application/javascript; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.svg': 'image/svg+xml',
  '.png': 'image/png',
  '.jpg': 'image/jpeg',
  '.jpeg': 'image/jpeg',
  '.webp': 'image/webp',
  '.gif': 'image/gif',
  '.ico': 'image/x-icon',
  '.mp3': 'audio/mpeg',
  '.mp4': 'video/mp4',
  '.woff': 'font/woff',
  '.woff2': 'font/woff2',
  '.ttf': 'font/ttf',
  '.wasm': 'application/wasm',
};

const server = http.createServer((req, res) => {
  const urlPath = decodeURIComponent((req.url || '/').split('?')[0]);

  // API 反向代理
  if (urlPath.startsWith('/api/')) {
    const proxyReq = http.request(
      { ...API_TARGET, path: req.url, method: req.method, headers: req.headers },
      (proxyRes) => {
        res.writeHead(proxyRes.statusCode || 500, proxyRes.headers);
        proxyRes.pipe(res);
      }
    );
    proxyReq.on('error', () => {
      res.writeHead(502, { 'Content-Type': 'application/json; charset=utf-8' });
      res.end('{"code":"99999","msg":"后端未启动"}');
    });
    req.pipe(proxyReq);
    return;
  }

  let filePath = path.join(ROOT, urlPath === '/' ? 'index.html' : urlPath);
  if (!path.extname(filePath)) filePath = path.join(filePath, 'index.html');

  fs.readFile(filePath, (err, data) => {
    if (err) {
      res.writeHead(404, { 'Content-Type': 'text/plain; charset=utf-8' });
      res.end('Not Found');
      return;
    }
    const ext = path.extname(filePath).toLowerCase();
    res.writeHead(200, { 'Content-Type': MIME[ext] || 'application/octet-stream' });
    res.end(data);
  });
});

server.listen(PORT, () => {
  console.log('Preview: http://localhost:' + PORT + '/Girls.html');
});
