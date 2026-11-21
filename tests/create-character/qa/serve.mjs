// 本地 QA 静态服务器：把 /ai/* 映射到 ai-girl-malaysia.com，/api/* 返回本地 mock。
// 仅监听 127.0.0.1；所有请求写入 logs/requests.jsonl 供创建请求计数取证。
// 用法: node tests/create-character/qa/serve.mjs [port]
import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const REPO = path.resolve(HERE, '../../..');
const SITE = path.join(REPO, 'ai-girl-malaysia.com');
const PORT = Number(process.argv[2] || process.env.QA_PORT || 8017);
const LOG_DIR = path.join(HERE, 'logs');
fs.mkdirSync(LOG_DIR, { recursive: true });
const LOG = path.join(LOG_DIR, 'requests.jsonl');

const MIME = {
  '.html': 'text/html; charset=utf-8', '.js': 'text/javascript; charset=utf-8',
  '.css': 'text/css; charset=utf-8', '.json': 'application/json; charset=utf-8',
  '.png': 'image/png', '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg',
  '.webp': 'image/webp', '.gif': 'image/gif', '.svg': 'image/svg+xml',
  '.woff': 'font/woff', '.woff2': 'font/woff2', '.ttf': 'font/ttf',
  '.mp4': 'video/mp4', '.webm': 'video/webm', '.ico': 'image/x-icon',
};

function log(obj) {
  fs.appendFileSync(LOG, JSON.stringify({ t: new Date().toISOString(), ...obj }) + '\n');
}

function json(res, code, body) {
  res.writeHead(code, { 'content-type': 'application/json; charset=utf-8', 'cache-control': 'no-store' });
  res.end(JSON.stringify(body));
}

function mockApi(req, res, u, body) {
  const scenario = req.headers['x-qa-scenario'] || 'ok';
  const p = u.pathname;
  const entry = { kind: 'api', method: req.method, path: p, scenario, bodyLen: body.length };
  log(entry);
  if (p.includes('customRoleCreate') || p.includes('customRoleRegenerate')) {
    if (scenario === 'fail') return json(res, 200, { code: '10003', message: 'QA mock auth failure' });
    if (scenario === 'slow') return setTimeout(() => json(res, 200, { code: '00000', data: { id: 90001, image_task_id: 'qa-task-1', status: 'processing' } }), 1200);
    return json(res, 200, { code: '00000', data: { id: 90001, image_task_id: 'qa-task-1', status: 'processing' } });
  }
  if (p.includes('customRoleImageStatus')) {
    if (scenario === 'fail') return json(res, 200, { code: '00000', data: { status: 'failed' } });
    return json(res, 200, { code: '00000', data: { status: 'done', image_url: '/ai/assets/brand/share_card.png', progress: 100 } });
  }
  return json(res, 200, { code: '00000', data: {}, message: 'QA mock' });
}

const server = http.createServer((req, res) => {
  const u = new URL(req.url, `http://127.0.0.1:${PORT}`);
  let body = '';
  req.on('data', c => { body += c; if (body.length > 1e6) req.destroy(); });
  req.on('end', () => {
    if (u.pathname.startsWith('/api/')) return mockApi(req, res, u, body);
    if (u.pathname === '/') { res.writeHead(302, { location: '/ai/createCharacter.html' }); return res.end(); }
    if (!u.pathname.startsWith('/ai/')) { res.writeHead(404); return res.end('not found'); }
    let rel = decodeURIComponent(u.pathname.slice(4));
    if (!rel || rel.endsWith('/')) rel += 'createCharacter.html';
    const abs = path.resolve(SITE, rel);
    if (!abs.startsWith(SITE)) { res.writeHead(403); return res.end('forbidden'); }
    fs.stat(abs, (err, st) => {
      if (err || !st.isFile()) {
        log({ kind: 'static', status: 404, path: u.pathname });
        res.writeHead(404, { 'cache-control': 'no-store' }); return res.end('404');
      }
      log({ kind: 'static', status: 200, path: u.pathname, bytes: st.size });
      res.writeHead(200, {
        'content-type': MIME[path.extname(abs).toLowerCase()] || 'application/octet-stream',
        'content-length': st.size,
        'cache-control': 'no-store',
      });
      fs.createReadStream(abs).pipe(res);
    });
  });
});

server.listen(PORT, '127.0.0.1', () => {
  console.log(`QA server http://127.0.0.1:${PORT}/ai/createCharacter.html -> ${SITE}`);
  console.log(`request log: ${LOG}`);
});
