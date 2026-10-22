# userInfo 无限循环事故（2026-10-22）

> 现象：Image 页提交 `customVideo/submit` 长时间无响应，页面疯狂重复请求 `/api/live/userInfo`，
> 等很久后提交才成功。

## 一、根因：前端 fetch 钩子自我触发

`js/shell-new.js` 里装了一个全局 fetch 钩子：

```js
window.fetch = function () {
  var p = orig.apply(this, arguments)
  if (/\/api\/live\//.test(u)) {              // ← 任何 /api/live/ 请求
    if (method === 'POST') p.then(() => scheduleRefresh())
  }
  return p
}
```

而 `scheduleRefresh()` 300ms 后调用 `refreshTokenBalance()`，后者又是：

```js
fetch('/api/live/userInfo', { method: 'POST', headers: { token: tk } })
```

于是形成闭环：

```
POST /api/live/userInfo 完成
  → 钩子命中（POST + /api/live/）
  → 300ms 后 refreshTokenBalance()
  → POST /api/live/userInfo
  → 再次命中钩子 …
```

**每个标签页约 3.3 次/秒，永不停止。**

## 二、为什么表现为「提交没响应」而不是报错

这是最容易误判的一点。**服务端全程是健康的**：

| 指标 | 实测 |
|---|---|
| `userInfo` 处理耗时 | **4.9 ms**（20 次采样，min 3.3ms / max 9.2ms）|
| FPM 池状态 | `active: 0, idle: 12`（40 个 worker 从未打满）|
| `max_children` 告警 | **0 条** |
| 事故窗口 502/504 | **0 条** |

真正的原因在**浏览器侧**：同一域名只有 ~6 个并发连接。
死循环把它们全占了，`submit` 的 POST（还要传图片 body）只能排队等空闲连接
→ 表现为「转圈很久，最后终于成功」。

> 所以「服务器被占满了」这个直觉是错的。误判方向就会去加机器/调 FPM，
> 而真正的问题在客户端。

## 三、实测规模

| 站点 | 单日 userInfo | 占该站总请求 | 独立 IP 数 |
|---|---|---|---|
| sugus | 53,613 | — | **6** |
| heygirl | 37,913 | **88%** | 5 |
| kisss | 15,901 | **67%** | 2 |
| kelymi | 175 | 12% | 1 |

**判据**：只有 1~6 个 IP，单 IP 最高 **1,577 次**。
真实用户不可能这样请求 → 确定是死循环，不是流量增长。

另一个关键证据：这些 IP **从未加载过 `shell-new.js`**
（日志里 0 次请求），说明 JS 早就在内存里跑着了，是修复前打开、一直没刷新的旧标签页。

## 四、修复（两层）

### 1. 前端根因（`js/shell-new.js`）

```js
// ① 钩子排除 userInfo 自身
if (/\/api\/live\//.test(u) && !/\/api\/live\/userInfo(\?|$)/.test(u)) { … }

// ② refreshTokenBalance 加单飞（任何调用方都无法并发风暴）
if (window.__tokenBalanceInflight) return
window.__tokenBalanceInflight = true
var __tbDone = function () { window.__tokenBalanceInflight = false }
…
.then(__tbDone, __tbDone)
```

同时把 **51 个 HTML** 的引用版本从 `shell-new.js?v=20261018c` 提到 `?v=20261022a`
（否则浏览器继续用缓存里的旧 JS，修复等于没做）。

### 2. 服务端兜底（四站 nginx）

即便前端修好，历史旧标签页仍会继续刷（浏览器把后台标签定时器限流到 1 次/秒，
所以它们不会马上消失）；将来若再出现类似客户端 bug，也需要服务端兜底。

```nginx
# http 块
limit_req_zone $binary_remote_addr zone=userinfo:10m rate=30r/m;

# vhost 内，必须放在 `location /api/` 之前（精确匹配优先级更高）
location = /api/live/userInfo {
    limit_req zone=userinfo burst=15 nodelay;
    limit_req_status 429;
    root /www/wwwroot/douyin/php/public;
    try_files $uri /index.php$uri$is_args$args;
}
```

**参数取值依据**：

| 场景 | 速率 | 是否该拦 |
|---|---|---|
| 正常用户（页面加载 1 次 + 操作后若干次） | 峰值几次/分钟 | 否 |
| 后台标签死循环（浏览器限流后） | 1 次/秒 | 是 |
| 前台标签死循环（修复前） | 3.3 次/秒 | 是 |
| 恶意刷 | 更高 | 是 |

30r/m = 0.5 次/秒 + burst 15：正常用户永远碰不到，1 次/秒的循环会被压到 0.5 次/秒。

> **第一版设的是 `5r/s`，实测完全无效**（1 次/秒的循环根本碰不到 5 的上限）。
> 一个不会触发的兜底等于没有兜底 —— 改成 30r/m 后实测才拦得住。

## 五、验证

### 限流实测（四站一致）

| 测试 | sugus | heygirl | kisss | kelymi |
|---|---|---|---|---|
| 正常单次 | 200 / 9.6ms | 200 / 11.7ms | 200 / 5.7ms | 200 / 22.2ms |
| 30 并发 | 20 通过 / 10×429 | 同 | 同 | 同 |
| **真实 bug 速率（300ms×40）** | **22 通过 / 18×429** | **22 / 18** | — | — |

### 业务冒烟（四站）

```
Home / Image / Chat        全 200
shell-new.js 版本          20261022a（四站）
单飞标记                   四站均 3 处
customVideo/submit         200，5-20ms
userInfo                   200，5-10ms
verify_client.py           sugus / kiss / kelymi 全绿
                           heygirl 仅 share_card.png 不同（既有品牌图，与本次无关）
```

### 品牌未被破坏

四站 `shell-new.js` 逐站校验客服邮箱 / CDN 域名 / 通知文案 / localStorage key，
全部保留各自品牌（sugusmail / heygirl.live / kisss.ai / kelymi.autos）。

## 六、遗留观察项

修复后 userInfo 仍占 70~95% 流量，但：

- `429 = 0`（说明当前速率低于 0.5 次/秒阈值后已回落）
- **加载新版 JS 的请求 = 0**（该窗口内没有新页面加载）
- 来源仍是那几个旧 IP

结论：**残余流量全是修复前的旧标签页**，浏览器后台限流把它们压到了 1 次/秒，
对 FPM 毫无压力（4 req/s vs 容量 ~2000/s）。它们会随用户刷新/关闭自然消失。

**不需要额外处理**，但可留意：
```bash
# 隔几小时再看一次占比，应该持续下降
grep -c userInfo /var/log/nginx/access.log
```

## 七、教训

1. **全局 fetch 钩子必须排除钩子自己的请求**，否则必成死循环。
   任何「请求完成后刷新 X」的钩子，都要检查 X 本身是不是也在被钩的路径上。
2. **「服务器慢」先看 FPM 池状态和 502/504**。这次池子是空的、零 5xx，
   直接排除了服务端容量问题，省掉大量无用排查。
3. **兜底阈值要用真实故障速率校准**。第一版 5r/s 看着合理，
   实测对 1r/s 的循环零作用。加完限流必须用真实故障模式打一遍。
4. **nginx 备份不能放 `sites-enabled/`**。`*.bak` 会被当配置加载，
   本次就触发过 `duplicate default server` 导致 `nginx -t` 失败。
   备份统一放 `/root/nginx_bak/`。

## 八、工具与回滚

```bash
python .zcode/fix_userinfo_loop.py --check     # 四站是否有死循环
python .zcode/fix_userinfo_loop.py --apply     # 修 JS（保留各站品牌）
python .zcode/add_userinfo_ratelimit.py --check
python .zcode/add_userinfo_ratelimit.py --apply
python .zcode/add_userinfo_ratelimit.py --rollback
```

回滚：
```bash
# 前端：每站还原 .bak.userinfo.*（同目录）
ls /www/wwwroot/douyin/ai-girl-malaysia.com/js/shell-new.js.bak.userinfo.*

# nginx
for f in /root/nginx_bak/*.bak.userinfo; do ... done
nginx -t && nginx -s reload
```
