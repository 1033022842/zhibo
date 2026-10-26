# AiShort API 文档（50 条短视频预设通道）

> 版本：v1.0 ｜ 更新：2026-10-27 ｜ 状态：sugus 站已上线，其余三站待部署
>
> 这是一条**独立通道**，与原有 `/api/customVideo/*`（3 个风格）完全无关。
> 50 条预设各有专属提示词、4 层 LoRA 链、时长，用 `code` 精确寻址，
> 不是随机轮换的池子。

---

## 一、基础信息

| 项 | 值 |
|---|---|
| **Base URL** | `https://sugus.ai/api/aiShort` |
| 已部署站点 | sugus（`https://sugus.ai`） |
| 协议 | HTTPS，JSON |
| 字符集 | UTF-8 |
| 用户鉴权 | `Authorization: Bearer <access_token>` |
| Worker 鉴权 | `X-Api-Key: <ai_key>` + `X-Worker-Id: <worker_id>` |

### 1.1 统一响应格式

所有接口都包一层：

```json
{ "code": "00000", "msg": "成功", "data": { } }
```

- `code == "00000"` 表示成功，其余为失败，`msg` 是给人看的错误说明。
- **注意**：HTTP 状态码通常仍是 200，**必须判 `code` 而不是只看 HTTP 状态**。
- 业务异常（如 Token 无效）也是 HTTP 200 + `code != "00000"`。

### 1.2 枚举

**`kind`** —— 产物类型

| 值 | 说明 |
|---|---|
| `video` | 视频（49 条） |
| `image` | 图片（仅 `gs-08`） |

**`status`** —— 任务状态

| 值 | 含义 | 前端建议 |
|---|---|---|
| `pending` | 排队中 | 显示「排队中」 |
| `accepted` | worker 已接单 | 显示「生成中」 |
| `processing` | 生成中 | 显示「生成中」 |
| `completed` | 已完成 | 用 `video_url` / `cover_url` 展示 |
| `failed` | 失败（已自动退款） | 显示失败，可重试 |
| `expired` | 超时作废 | 可重试 |

> 轮询时若 `video_url` 非空但状态还是 `accepted/processing`，按已完成处理——
> 这是成品已上传但 complete 回调延迟的情况，`myList`/`task` 已内置该兜底。

---

## 二、用户侧接口

### 2.0 获取 Token（前置）

`POST https://sugus.ai/api/live/login`

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| `account` | string | 是 | 用户名或邮箱 |
| `password` | string | 是 | 密码 |

```bash
curl -X POST https://sugus.ai/api/live/login \
  -d 'account=your_name' -d 'password=your_pass'
```

```json
{
  "code": "00000",
  "data": {
    "access_token": "eyJ0eXAiOiJKV1Qi...",
    "refresh_token": "eyJ0eXAiOiJKV1Qi...",
    "expires_in": 31536000,
    "user": { "id": 123, "user_no": "...", "nickname": "...", "level": 1 }
  }
}
```

后续所有需登录接口带上：`Authorization: Bearer <access_token>`

---

### 2.1 预设列表（公开，无需登录）

```
GET /presets
```

**不返回 prompt 和 lora_chain**，避免提示词被批量抓取。

```bash
curl -s https://sugus.ai/api/aiShort/presets
```

```json
{
  "code": "00000",
  "msg": "成功",
  "data": {
    "total": 50,
    "list": [
      {
        "code": "gs-01",
        "title": "Deepthroat (POV) - 8s",
        "category": "blowjob",
        "kind": "video",
        "duration_sec": 8,
        "cover_url": "/ai/media/gs/01.jpg",
        "preview_url": "/ai/media/gs/01.mp4"
      },
      {
        "code": "gs-02",
        "title": "Cowgirl - 8s",
        "category": "cowgirl",
        "kind": "video",
        "duration_sec": 8,
        "cover_url": "/ai/media/gs/02.jpg",
        "preview_url": "/ai/media/gs/02.mp4"
      }
    ]
  }
}
```

**字段说明**

| 字段 | 类型 | 说明 |
|---|---|---|
| `code` | string | **寻址键**，提交时用它 |
| `title` | string | 卡片标题 |
| `category` | string | `blowjob` / `cowgirl` / `doggy` / `other` / `cumshot` / `solo` |
| `kind` | string | `video` / `image` |
| `duration_sec` | int | 默认生成时长 |
| `cover_url` | string | 封面图（站点根相对路径） |
| `preview_url` | string | 参考视频；`gs-08` 为空 |

> `cover_url` / `preview_url` 是**根相对路径**（`/ai/media/...`），
> 拼接方式：`https://sugus.ai` + 路径。

---

### 2.2 单条详情（需登录）

```
GET /detail?code=gs-17
```

**含完整 prompt 与 LoRA 链**，供需要自定义编排的调用方使用；
普通调用直接用 `/submit` 即可，不需要此接口。

```bash
curl -s "https://sugus.ai/api/aiShort/detail?code=gs-17" \
  -H "Authorization: Bearer $TOKEN"
```

```json
{
  "code": "00000",
  "data": {
    "code": "gs-17",
    "title": "Make sex video",
    "category": "solo",
    "kind": "video",
    "prompt": "A woman making her own sex video. She positions a phone, strips, spreads her legs wide for the camera, fingers her pussy, uses a vibrator, and talks dirty. Bedroom, natural light. Single female character only, explicit amateur high-quality style.",
    "lora_chain": [
      ["minimax-h3_fl2v_8Step_motion_enhancer.safetensors", 1.0],
      ["MMH3_Perfect_fingering_v1_000002000.safetensors", 0.7],
      ["MM-H3 - Pussy Lora.safetensors", 0.45],
      ["ass_jiggle_H3_i2v_v1.0.safetensors", 0.25]
    ],
    "duration_sec": 8,
    "cover_url": "/ai/media/gs/17.jpg",
    "preview_url": "/ai/media/gs/17.mp4"
  }
}
```

**错误**

| code | 场景 |
|---|---|
| `A0001` | 缺 `code` 参数 |
| `A0002` | preset 不存在或已下架 |
| `A0100` | 未登录 / Token 无效 |

---

### 2.3 提交生成（需登录）

```
POST /submit
Content-Type: multipart/form-data
```

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| `image` | file | 是 | jpg / png / webp，≤ 10MB |
| `preset_code` | string | 是 | 如 `gs-17` |
| `agreed_policy` | string | 是 | 必须传 `1`（确认图片合规） |
| `duration_sec` | int | 否 | 覆盖预设默认时长，范围 1–15，默认 8 |
| `confirm_paid` | string | 否 | 免费额度用尽后，二次提交需传 `1` |

```bash
curl -X POST https://sugus.ai/api/aiShort/submit \
  -H "Authorization: Bearer $TOKEN" \
  -F "image=@/path/her.jpg" \
  -F "preset_code=gs-17" \
  -F "agreed_policy=1"
```

**成功响应**

```json
{
  "code": "00000",
  "msg": "成功",
  "data": {
    "task_id": 193,
    "task_no": "AS202610270043150001",
    "status": "pending",
    "preset_code": "gs-17",
    "kind": "video",
    "duration_sec": 8,
    "remain": 1,
    "paid_tokens": 0,
    "balance": 120
  }
}
```

| 字段 | 说明 |
|---|---|
| `task_id` | 任务 ID（轮询用） |
| `task_no` | 任务号（对用户展示更友好） |
| `remain` | 提交后剩余免费次数；`-1` 表示不限量 |
| `paid_tokens` | 本次消耗的代币数，`0` 表示走免费额度 |
| `balance` | 当前代币余额 |

**额度规则**

| 用户 | 免费额度 | 用尽后 |
|---|---|---|
| 普通用户 | 上限 2 次，每天 +1（最多累积到 2） | 每次扣 `video_gen_price` 代币（默认 50）；`0` 表示关闭 |
| 会员 | 上限 5 次，每天回满 5 | 同上 |

**付费确认两步走**

免费额度用尽且配置了代币价格时，首次提交会返回：

```json
{ "code": "C0102", "msg": "This video costs 50 tokens." }
```

前端弹确认框，用户同意后带 `confirm_paid=1` 重新提交同一请求即可。

**错误**

| code | 场景 | 处理 |
|---|---|---|
| `A0001` | 未同意合规条款 / 缺 preset_code / 图片不合规 / 超 10MB / 格式不符 | 修正参数重试 |
| `A0002` | preset 不存在或下架 | 换 `code` |
| `A0100` | 未登录 | 先登录 |
| `C0100` | 余额不足 | 引导充值 |
| `C0102` | 需确认代币消费 | 弹确认框后带 `confirm_paid=1` 重提 |

> 提交失败**会自动退还**已扣的额度/代币，可以放心重试。

---

### 2.4 我的作品（需登录）

```
GET /myList
```

返回最近 50 条，按 id 倒序。

```bash
curl -s https://sugus.ai/api/aiShort/myList \
  -H "Authorization: Bearer $TOKEN"
```

```json
{
  "code": "00000",
  "data": [
    {
      "task_id": 193,
      "task_no": "AS202610270043150001",
      "preset_code": "gs-17",
      "title": "Make sex video",
      "kind": "video",
      "status": "completed",
      "video_url": "/storage/custom_video/20261027/task_193_00001-36a4a40c....mp4",
      "cover_url": "/storage/custom_video/20261027/task_193_00001-2f1fcef3....jpg",
      "duration": 8,
      "created_at": "2026-10-27 00:43:15",
      "finished_at": "2026-10-27 00:45:02"
    }
  ]
}
```

> `video_url` / `cover_url` 是**根相对路径或带时效签名的地址**，
> 前端直接 `https://sugus.ai` + 路径使用。

---

### 2.5 单任务状态（需登录，轮询推荐）

```
GET /task?task_no=AS...      # 或 task_id=193
```

`task_no` 与 `task_id` 二选一即可。

```bash
curl -s "https://sugus.ai/api/aiShort/task?task_id=193" \
  -H "Authorization: Bearer $TOKEN"
```

```json
{
  "code": "00000",
  "data": {
    "task_id": 193,
    "task_no": "AS202610270043150001",
    "preset_code": "gs-17",
    "title": "Make sex video",
    "status": "completed",
    "video_url": "/storage/custom_video/20261027/task_193_00001-....mp4",
    "cover_url": "/storage/custom_video/20261027/task_193_00001-....jpg",
    "duration": 8,
    "error": "",
    "created_at": "2026-10-27 00:43:15",
    "finished_at": "2026-10-27 00:45:02"
  }
}
```

失败时 `status=failed`，`error` 里是原因（如「视频全黑，判定生成失败」）。

---

## 三、Worker 侧接口

> 仅 GPU 节点使用。鉴权：`X-Api-Key` + `X-Worker-Id`。
> 普通业务方不需要调用这些。

### 3.1 捞单

```
GET /pending?count=1
```

`count` 范围 1–3，默认 1。按 `priority` 升序（新通道 `priority=1`，低于老通道的 5）。

响应里**含 prompt 与 lora_chain**（由服务端 join `lp_short_preset` 下发）：

```json
{
  "code": "00000",
  "data": [
    {
      "task_id": 193,
      "task_no": "AS202610270043150001",
      "kind": "video",
      "preset_code": "gs-17",
      "title": "Make sex video",
      "prompt": "A woman making her own sex video. ...",
      "lora_chain": [["minimax-h3_fl2v_8Step_motion_enhancer.safetensors", 1.0], ["...", 0.7]],
      "duration_sec": 8,
      "image_url": "https://sugus.ai/storage/custom_video/20261027/xxx.jpg",
      "created_at": "2026-10-27 00:43:15"
    }
  ]
}
```

空队列返回 `{"code":"00000","data":[]}`。

### 3.2 接单

```
POST /accept   task_id
```

`pending → accepted`，记录 `worker_id`。

### 3.3 上报进度

```
POST /progress   task_id
```

`accepted → processing`。只推进状态，不会回退已完成/失败的任务。

### 3.4 上传成品

```
POST /upload   (multipart)
  task_id
  video   视频文件；图片预设(g) 用此字段传 png
  cover   封面图（可选，视频建议传）
```

**注意**：字段名是 `video` 而非 `video_file`，与老通道 `/uploadVideo` 一致。

响应：`{"data":{"video_url":"...","cover_url":"..."}}`

### 3.5 报完成

```
POST /complete   task_id  duration_sec
```

`accepted/processing → completed`。

### 3.6 报失败（自动退款）

```
POST /fail   task_id  error_msg
```

置 `failed` 并**自动退还**代币或免费额度。

### 3.7 队列概览

```
GET /stats
```

```json
{
  "code": "00000",
  "data": {
    "pending": 0,
    "running": 0,
    "stuck": 0,
    "server_time": "2026-10-27 00:25:01"
  }
}
```

`stuck` = 接单超过 90 分钟仍未完成的任务数。

---

## 四、完整调用示例

### 4.1 最小可用流程（JavaScript）

```javascript
const BASE = 'https://sugus.ai';
let token = '';

async function login(account, password) {
  const r = await fetch(`${BASE}/api/live/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ account, password })
  }).then(x => x.json());
  if (r.code !== '00000') throw new Error(r.msg);
  token = r.data.access_token;
  return r.data.user;
}

async function listPresets() {
  const r = await fetch(`${BASE}/api/aiShort/presets`).then(x => x.json());
  return r.data.list;   // 50 条
}

async function generate(imageFile, presetCode, confirmPaid = false) {
  const fd = new FormData();
  fd.append('image', imageFile);
  fd.append('preset_code', presetCode);
  fd.append('agreed_policy', '1');
  if (confirmPaid) fd.append('confirm_paid', '1');

  const r = await fetch(`${BASE}/api/aiShort/submit`, {
    method: 'POST',
    headers: { Authorization: `Bearer ${token}` },
    body: fd
  }).then(x => x.json());

  if (r.code === 'C0102') {          // 需确认代币消费
    if (!window.confirm(r.msg)) return null;
    return generate(imageFile, presetCode, true);
  }
  if (r.code !== '00000') throw new Error(r.msg);
  return r.data;                     // { task_id, task_no, status, ... }
}

async function poll(taskId, onUpdate) {
  for (;;) {
    const r = await fetch(`${BASE}/api/aiShort/task?task_id=${taskId}`, {
      headers: { Authorization: `Bearer ${token}` }
    }).then(x => x.json());
    if (r.code !== '00000') throw new Error(r.msg);
    const t = r.data;
    onUpdate?.(t);
    if (t.status === 'completed') return t;
    if (t.status === 'failed') throw new Error(t.error || '生成失败');
    await new Promise(s => setTimeout(s, 5000));   // 5 秒轮询
  }
}

// 用法
await login('your_name', 'your_pass');
const presets = await listPresets();
console.log(`共 ${presets.length} 条`);

const task = await generate(fileInput.files[0], 'gs-17');
const done = await poll(task.task_id, t => console.log('状态:', t.status));
console.log('成品:', BASE + done.video_url);
```

### 4.2 curl 全流程

```bash
BASE=https://sugus.ai

# 1) 登录拿 token
TOKEN=$(curl -s -X POST $BASE/api/live/login \
  -d 'account=your_name' -d 'password=your_pass' \
  | python -c 'import sys,json;print(json.load(sys.stdin)["data"]["access_token"])')

# 2) 看有哪些预设
curl -s $BASE/api/aiShort/presets \
  | python -c 'import sys,json;[print(x["code"],x["title"]) for x in json.load(sys.stdin)["data"]["list"]]'

# 3) 提交 gs-17
curl -s -X POST $BASE/api/aiShort/submit \
  -H "Authorization: Bearer $TOKEN" \
  -F "image=@her.jpg" -F "preset_code=gs-17" -F "agreed_policy=1"

# 4) 轮询（把 193 换成返回的 task_id）
watch -n5 "curl -s '$BASE/api/aiShort/task?task_id=193' -H 'Authorization: Bearer $TOKEN'"
```

### 4.3 Python

```python
import requests, time

BASE = "https://sugus.ai"

def login(account, password):
    r = requests.post(f"{BASE}/api/live/login",
                      data={"account": account, "password": password}).json()
    r["code"] == "00000" or (_ for _ in ()).throw(RuntimeError(r["msg"]))
    return r["data"]["access_token"]

def generate(token, img_path, preset_code, confirm_paid=False):
    data = {"preset_code": preset_code, "agreed_policy": "1"}
    if confirm_paid:
        data["confirm_paid"] = "1"
    with open(img_path, "rb") as f:
        r = requests.post(f"{BASE}/api/aiShort/submit",
                          headers={"Authorization": f"Bearer {token}"},
                          data=data, files={"image": f}).json()
    if r["code"] == "C0102":
        return {"need_confirm": True, "msg": r["msg"]}
    if r["code"] != "00000":
        raise RuntimeError(r["msg"])
    return r["data"]

def wait(token, task_id, timeout=1800):
    deadline = time.time() + timeout
    while time.time() < deadline:
        r = requests.get(f"{BASE}/api/aiShort/task",
                         params={"task_id": task_id},
                         headers={"Authorization": f"Bearer {token}"}).json()
        t = r["data"]
        if t["status"] == "completed":
            return t
        if t["status"] == "failed":
            raise RuntimeError(t.get("error") or "生成失败")
        time.sleep(5)
    raise TimeoutError("等待超时")

token = login("your_name", "your_pass")
task = generate(token, "her.jpg", "gs-17")
done = wait(token, task["task_id"])
print(BASE + done["video_url"])
```

---

## 五、错误码

| code | 含义 | 常见场景 | 处理建议 |
|---|---|---|---|
| `00000` | 成功 | — | — |
| `A0001` | 参数错误 | 缺 preset_code / 未传 agreed_policy / 图片超 10MB / 格式不符 | 修正参数 |
| `A0002` | 记录不存在 | preset 不存在或已下架 / 任务不存在 | 换 code 或检查 task_id |
| `A0100` | Token 无效 | 未登录 / token 错误 | 重新登录 |
| `A0101` | Token 已过期 | token 超过 365 天 | 用 refresh_token 换新，或重新登录 |
| `C0100` | 余额不足 | 付费续生成时代币不够 | 引导充值 |
| `C0102` | 需确认代币消费 | 免费额度用尽，配置了代币价 | 弹确认框后带 `confirm_paid=1` 重提 |
| `A0100` | 缺少 X-Api-Key | worker 侧接口未带 key | 带 `X-Api-Key` 头 |
| `99999` | 失败 | 未分类错误 | 看 `msg` |

---

## 六、重要约束

### 6.1 生成耗时

四站共用一张 GPU、一条 ComfyUI 队列，串行执行。**4 层 LoRA 的实际耗时**：

| 时长 | 参考耗时 |
|---|---|
| 3 秒 | 约 100 秒 |
| **8 秒（默认）** | **约 10 分钟** |
| 更长 | 线性增加 |

所以：

- 轮询间隔建议 **5 秒**，超时至少设 **20 分钟**（排队 + 生成）。
- 排队上限 90 分钟，超过会先重试一次，再失败退款。
- 高峰期排队会更久，这是物理限制，不是接口问题。

### 6.2 隔离保证

本通道与 `/api/customVideo/*` 在数据库、队列、worker 进程、工作流上完全独立：

- 任务类型 `ai_short_video` / `ai_short_image`
- 老通道只查 `custom_video` / `custom_image`，互不可见
- 新通道任务优先级更低（`priority=1` vs `5`），繁忙时自动让路给老通道

### 6.3 内容合规

`agreed_policy=1` 是**强制**的，表示调用方确认上传图片为 AI 生成、
动漫风格或已获当事人授权，并承担违规责任。缺失会被拒绝。

### 6.4 提示词保密

`/presets` 有意不返回 `prompt` 和 `lora_chain`。
`/detail` 需要登录才能看到，请勿在客户端代码里硬编码这些内容。

---

## 七、50 条预设清单

| code | 标题 | 分类 |
|---|---|---|
| gs-01 | Deepthroat (POV) - 8s | blowjob |
| gs-02 | Cowgirl - 8s | cowgirl |
| gs-03 | Her Best Friend's Secret | solo |
| gs-04 | Doggystyle car model | doggy |
| gs-05 | Private Cinema missionary | other |
| gs-06 | Hot Shower Sex | doggy |
| gs-07 | Make a Couple Video | solo |
| gs-08 | Edit Image（图片） | other |
| gs-09 | Undressing | solo |
| gs-10 | High-speed thrusting in female-superior position | cowgirl |
| gs-11 | Seductive Blowjob | blowjob |
| gs-12 | Spanking ass | doggy |
| gs-13 | Leg Up Fuck Her Pussy | other |
| gs-14 | Step-mommy's Desire | solo |
| gs-15 | Kitchen doggystyle | doggy |
| gs-16 | Big-breasted young woman | solo |
| gs-17 | Make sex video | solo |
| gs-18 | PoV Huge Tits Oiled Titjob | other |
| gs-19 | ONE NIGHT STAY | other |
| gs-20 | Private Session | solo |
| gs-21 | Morning Craving | solo |
| gs-22 | First Light | other |
| gs-23 | STEPMOM'S LAST DROP | cumshot |
| gs-24 | Cum on stepmother after Came home | cumshot |
| gs-25 | Morning Taste | blowjob |
| gs-26 | Fuck her brains out in Las Vegas | other |
| gs-27 | Take It All | blowjob |
| gs-28 | Keeping fuck Stepmom until her man returns | doggy |
| gs-29 | Riding and bouncing | cowgirl |
| gs-30 | Sofa with multiple positions | other |
| gs-31 | Giant Cumshot on Huge Tits | cumshot |
| gs-32 | Rear climax | doggy |
| gs-33 | POV Seated Blowjob (HQ) V2 | blowjob |
| gs-34 | Turn It Up | cowgirl |
| gs-35 | Sheer Obsession | solo |
| gs-36 | Little slut begs for sex | blowjob |
| gs-37 | POV: Reverse Cowgirl Anal | cowgirl |
| gs-38 | She pushes the cock into pussy and rides | other |
| gs-39 | Tightly shut mouth gets fucked open | blowjob |
| gs-40 | All the Way | blowjob |
| gs-41 | Girl loves choking on big cocks | blowjob |
| gs-42 | Horny Pussy Destroyed by Your Giant | other |
| gs-43 | Couple Tit Sucking Handjob Cum | cumshot |
| gs-44 | Riding you like a bull | cowgirl |
| gs-45 | Missionary creampie pullout | cumshot |
| gs-46 | Lovers' Trance | other |
| gs-47 | Hot Couple in Love - Cowgirl | cowgirl |
| gs-48 | Hot Couple in Love - Doggystyle | doggy |
| gs-49 | Lost Sex - Final Doggy Madness | doggy |
| gs-50 | Sex or Die - Cowgirl Race Against Death | cowgirl |

---

## 八、实测记录（2026-10-27，sugus）

以下为线上真实返回，可用于对齐字段：

| 场景 | 结果 |
|---|---|
| `GET /presets`（无鉴权） | `200`，`total=50`，list 项字段 `code,title,category,kind,duration_sec,cover_url,preview_url` |
| `GET /detail?code=gs-17`（有效 token） | `200`，返回完整 prompt + 4 层 lora_chain |
| `GET /detail?code=gs-99`（不存在） | `{"code":"A0002","msg":"Preset not found"}` |
| `GET /detail`（无 token） | `{"code":"A0100","msg":"Token无效"}` |
| `GET /myList`（有效 token，无历史） | `{"code":"00000","data":[]}` |
| `GET /pending`（无 X-Api-Key） | `{"code":"A0100","msg":"缺少 X-Api-Key"}` |
| `GET /pending`（有 X-Api-Key，空队列） | `{"code":"00000","data":[]}` |
| `POST /submit` → worker → `complete` | 端到端跑通，`task 193` 状态 `completed`，3 秒片约 103 秒 |

`/detail` 真实响应片段（gs-17）：

```json
{
  "code": "00000",
  "msg": "成功",
  "data": {
    "code": "gs-17",
    "title": "Make sex video",
    "category": "solo",
    "kind": "video",
    "prompt": "A woman making her own sex video. She positions a phone, strips, spreads her legs wide for the camera, fingers her pussy, uses a vibrator, and talks dirty. Bedroom, natural light. Single female character only, explicit amateur high-quality style.",
    "lora_chain": [
      ["minimax-h3_fl2v_8Step_motion_enhancer.safetensors", 1],
      ["MMH3_Perfect_fingering_v1_000002000.safetensors", 0.7],
      ["MM-H3 - Pussy Lora.safetensors", 0.45],
      ["ass_jiggle_H3_i2v_v1.0.safetensors", 0.25]
    ],
    "duration_sec": 8,
    "cover_url": "/ai/media/gs/17.jpg",
    "preview_url": "/ai/media/gs/17.mp4"
  }
}
```

---

## 九、接口速查

| 方法 | 路径 | 鉴权 | 用途 |
|---|---|---|---|
| `POST` | `/api/live/login` | 无 | 拿 token |
| `GET` | `/api/aiShort/presets` | 无 | 50 条列表 |
| `GET` | `/api/aiShort/detail` | Bearer | 单条详情（含 prompt） |
| `POST` | `/api/aiShort/submit` | Bearer | 提交生成 |
| `GET` | `/api/aiShort/myList` | Bearer | 我的作品 |
| `GET` | `/api/aiShort/task` | Bearer | 单任务状态 |
| `GET` | `/api/aiShort/pending` | X-Api-Key | worker 捞单 |
| `POST` | `/api/aiShort/accept` | X-Api-Key | worker 接单 |
| `POST` | `/api/aiShort/progress` | X-Api-Key | worker 报进度 |
| `POST` | `/api/aiShort/upload` | X-Api-Key | worker 传成品 |
| `POST` | `/api/aiShort/complete` | X-Api-Key | worker 报完成 |
| `POST` | `/api/aiShort/fail` | X-Api-Key | worker 报失败 |
| `GET` | `/api/aiShort/stats` | X-Api-Key | 队列概览 |

> **注意**：单条详情端点是 `/detail` 而非 `/preset`。
> 原因：ThinkPHP 多应用模式下 URL 段直接映射控制器名，`preset` 会与
> 控制器 `AiShort` 的解析冲突。同理，`php/route/live.php` 里的显式路由
> 在本项目**不生效**，控制器类名必须等于 URL 段。
