# 直播平台 API 对接文档

***

## 一、AI 客户端接口

> **鉴权方式**：所有 AI 接口需在请求头携带 `X-Api-Key: {api_key}`，可选的 `X-Worker-Id: {worker_id}` 标识客户端身份。
>
> **API Key 默认值**：`live-ai-api-key-2026`（在 `.env` 中通过 `AI_API_KEY` 覆盖）
>
> **Base URL**：`http://{host}:{port}`（ThinkPHP 端口，默认 8000）

### 1.1 拉取待处理任务

```
GET /api/v1/ai/tasks/pull?count=10
```

| 参数 | 类型 | 默认 | 说明 |
|------|------|------|------|
| count | int | 10 | 每次拉取上限（1~10） |

**内部机制**：该接口底层通过 **Redis Stream 消费组**（`stream:ai:tasks` / `ai-workers`）实时获取弹幕触发的 AI 任务——不是从 MySQL 轮询。每次调用执行 `XREADGROUP` 阻塞读取（默认 3 秒超时），有新任务当场返回，无任务等到超时返回空。**AI 客户端只需循环调用此接口即可实现实时拉取，无需自己处理 Redis。**

**数据流**：
```
观众弹幕 → ws-webman ChatService
            │
            ├─ XADD stream:ai:tasks (Redis Stream)
            │
            ▼
AI 客户端：GET /api/v1/ai/tasks/pull
            │
            ├─ XREADGROUP stream:ai:tasks ai-workers {workerId}  ← 阻塞读取
            ├─ 有消息 → 创建 lp_ai_task MySQL 记录 → XACK → 返回
            └─ 无消息 → 3 秒后返回 []
```

**响应示例：**

```json
{
  "code": "00000",
  "msg": "成功",
  "data": [
    {
      "task_id": 1,
      "task_no": "AI202605231430120001",
      "room_id": 1,
      "status": "pending",
      "task_type": "interaction_async",
      "content": "你好呀",
      "persona_id": null,
      "deadline_at": "2026-05-23 14:35:12",
      "created_at": "2026-05-23 14:30:12"
    }
  ]
}
```

> **关键技术点**：
> - `XREADGROUP` 阻塞读取代替 MySQL 轮询，延迟从秒级降到毫秒级
> - 消费组 `ai-workers` 确保多 AI Worker 负载均衡（同一条消息不会发给多个 Worker）
> - `XACK` 确认消费，`XPENDING` 可查未确认的死信
> - MySQL `lp_ai_task` 仍然写入，作为**持久化备份 + 管理后台查询**

### 1.2 接单

```
POST /api/v1/ai/tasks/accept
Content-Type: application/json

{
  "task_id": 1
}
```

将任务状态从 `pending` 改为 `accepted`，记录 `accepted_at` 和 `worker_id`。

| 字段       | 类型  | 必填 | 说明    |
| -------- | --- | -- | ----- |
| task\_id | int | 是  | 任务 ID |

**响应：**

```json
{
  "code": "00000",
  "msg": "已接单",
  "data": {
    "task_id": 1,
    "task_no": "AI202605231430120001",
    "room_id": 1,
    "status": "accepted",
    "accepted_at": "2026-05-23 14:30:18"
  }
}
```

**错误码：**

| code  | 说明                           |
| ----- | ---------------------------- |
| F0100 | 任务不存在                        |
| F0102 | 任务已完成/已失败，不可接单               |
| F0101 | 任务已过期（接单时发现已超 `deadline_at`） |

### 1.3 进度上报

```
POST /api/v1/ai/tasks/progress
Content-Type: application/json

{
  "task_id": 1,
  "percent": 50,
  "message": "正在生成视频..."
}
```

| 字段       | 类型     | 必填 | 说明           |
| -------- | ------ | -- | ------------ |
| task\_id | int    | 是  | 任务 ID        |
| percent  | int    | 否  | 进度百分比 0\~100 |
| message  | string | 否  | 进度描述         |

首次上报进度时，任务状态自动从 `accepted` 变为 `processing`。

### 1.4 任务完成回调

```
POST /api/v1/ai/tasks/complete
Content-Type: application/json

{
  "task_id": 1,
  "video_url": "https://cdn.example.com/ai/room1_interaction_001.mp4",
  "duration_sec": 15,
  "cover_url": "https://cdn.example.com/ai/room1_interaction_001_cover.jpg"
}
```

| 字段            | 类型     | 必填 | 说明                       |
| ------------- | ------ | -- | ------------------------ |
| task\_id      | int    | 是  | 任务 ID                    |
| video\_url    | string | 是  | 生成视频的 CDN URL（平台侧用此地址播放） |
| duration\_sec | int    | 是  | 视频时长（秒），必须 > 0           |
| cover\_url    | string | 否  | 封面图 URL                  |

**回调后的系统行为：**

1. `lp_ai_task` 状态 → `completed`，记录 `video_url` / `duration_sec`
2. 自动创建 `lp_room_play_task`（互动播放任务，优先级 50）
3. 调用状态机 `enterInteraction(roomId)` → 若房间处于 `public_live` 且无特权运行，则进入 `switching` → `interaction_live`
4. 通过 Redis Stream 下发 Channel Worker 切换指令
5. 通过 Redis List 广播 `interaction_ready` 给 ws-webman → 推送给前端

**响应：**

```json
{
  "code": "00000",
  "msg": "任务完成，已排入互动播单",
  "data": {
    "task_id": 1,
    "task_no": "AI202605231430120001",
    "status": "completed",
    "play_task_id": 5,
    "switch_task": {
      "id": 3,
      "task_no": "SI202605231430190002"
    },
    "finished_at": "2026-05-23 14:30:30"
  }
}
```

### 1.5 任务失败回调

```
POST /api/v1/ai/tasks/fail
Content-Type: application/json

{
  "task_id": 1,
  "error_msg": "GPU 资源不足，生成超时"
}
```

| 字段         | 类型     | 必填 | 说明    |
| ---------- | ------ | -- | ----- |
| task\_id   | int    | 是  | 任务 ID |
| error\_msg | string | 否  | 失败原因  |

### 1.6 获取实时推流参数（实时推流模式）

```
GET /api/v1/ai/tasks/stream-token?room_id=1
```

返回 RTMP 推流地址 + HLS 播放地址，附带签名 token 防盗推。适用于 AI 端直接用 ffmpeg/OBS 推实时互动流。

**响应：**

```json
{
  "code": "00000",
  "data": {
    "room_id": 1,
    "stream_alias": "room/1/interaction",
    "push_url": "rtmp://127.0.0.1:1935/live/room/1/interaction",
    "play_hls": "/hls/room/1/interaction.m3u8",
    "token": "abc123...",
    "expire_at": 1769149800
  }
}
```

| 字段               | 说明                                  |
| ---------------- | ----------------------------------- |
| push\_url        | RTMP 推流地址（AI 端用 ffmpeg 推此地址）        |
| play\_hls        | 前端播放用的相对 HLS 路径                     |
| token            | 签名 token，用于 SRS 鉴权                  |
| expire\_at       | Token 过期时间戳（1 小时后）                  |
| max\_stream\_sec | **最大允许推流时长**（默认 120 秒），超时后平台会强制切回待机 |

### 1.7 推流结束通知（AI 主动通知）

```
POST /api/v1/ai/tasks/stream-end
Content-Type: application/json

{
  "task_id": 1,
  "duration_sec": 15,
  "reason": "generation_done"
}
```

| 字段            | 类型     | 必填 | 说明                     |
| ------------- | ------ | -- | ---------------------- |
| task\_id      | int    | 是  | 任务 ID                  |
| duration\_sec | int    | 否  | 实际推流时长（秒）              |
| reason        | string | 否  | 结束原因：`ai_notify`(主动通知) |

**回调后的系统行为：**

1. `lp_ai_task` 状态 → `completed`（如果尚未完成），记录 `duration_sec`
2. 调用状态机 `leaveInteraction(roomId)` → `interaction_live` → `switching` → `public_live`
3. 通过 Redis Stream 下发 Channel Worker 切回公共流指令
4. 通过 Redis List 广播 `interaction_ended` 给 ws-webman → 推送给前端

**响应：**

```json
{
  "code": "00000",
  "msg": "推流已结束，房间已切回待机模式",
  "data": {
    "task_id": 1,
    "task_no": "AI202605231430120001",
    "room_id": 1,
    "status": "completed",
    "duration_sec": 15,
    "finished_at": "2026-05-23 14:30:45"
  }
}
```

### 1.8 SRS 推流断开回调（服务器自动检测）

```
POST /api/v1/srs/unpublish
Content-Type: application/x-www-form-urlencoded

action=on_unpublish&stream=room%2F1%2Finteraction&secret=srs-callback-secret-2026
```

SRS 配置 `on_unpublish` HTTP 回调，当 AI 推流断开时 SRS 自动通知平台。平台解析 `stream` 字段提取 `room_id`，自动触发切回公共流。

> 也支持 GET 请求，方便 SRS 调试。

| 字段     | 类型     | 必填 | 说明                                   |
| ------ | ------ | -- | ------------------------------------ |
| action | string | 否  | `on_unpublish` 时触发                   |
| stream | string | 是  | 推流名称，如 `room/1/interaction`          |
| secret | string | 否  | 共享密钥，用于验证回调来源（通过 `AI_SRS_SECRET` 配置） |

**SRS 配置示例：**

```nginx
vhost __defaultVhost__ {
    http_hooks {
        enabled on;
        on_unpublish http://127.0.0.1:7090/api/v1/srs/unpublish?secret=srs-callback-secret-2026;
    }
}
```

***

## 二、内部接口（ws-webman → ThinkPHP）

### 2.1 弹幕触发 AI 任务创建

```
POST /api/v1/ai/tasks/create
Content-Type: application/json

{
  "room_id": 1,
  "user_id": 3,
  "nickname": "Test004",
  "content": "你好呀"
}
```

| 字段          | 类型     | 必填 | 说明             |
| ----------- | ------ | -- | -------------- |
| room\_id    | int    | 是  | 房间 ID          |
| user\_id    | int    | 否  | 发送用户 ID（游客为 0） |
| nickname    | string | 否  | 用户昵称           |
| content     | string | 是  | 弹幕文本内容         |
| persona\_id | int    | 否  | 绑定的人设 ID       |

此接口由 ws-webman ChatService 在每次弹幕发送成功后调用，**无需鉴权**（内部调用）。任务 `deadline_at` 自动设为创建时间 + 5 分钟。

***

## 三、客户端 API（前端可调用）

### 3.1 用户认证

| 方法   | 路由                         | 鉴权           | 说明                                                                     |
| ---- | -------------------------- | ------------ | ---------------------------------------------------------------------- |
| POST | `/api/live/register`       | 无            | 注册 `{ username, password, nickname }`                                  |
| POST | `/api/live/login`          | 无            | 登录 `{ username, password }` → 返回 access\_token + refresh\_token + user |
| POST | `/api/live/refreshToken`   | 无            | 刷新 token `{ refresh_token }`                                           |
| POST | `/api/live/logout`         | Bearer Token | 退出登录                                                                   |
| GET  | `/api/live/profile`        | Bearer Token | 获取个人信息                                                                 |
| PUT  | `/api/live/update-profile` | Bearer Token | 更新 `{ nickname, gender, bio }`                                         |

**登录响应示例：**

```json
{
  "code": "00000",
  "data": {
    "access_token": "eyJhbG...",
    "refresh_token": "eyJhbG...",
    "expires_in": 7200,
    "user": {
      "id": 3,
      "user_no": "U20260523001",
      "nickname": "Test004",
      "avatar": "",
      "level": 1
    }
  }
}
```

### 3.2 直播信息流

```
GET /api/v1/feed/live?cursor=&limit=10
```

| 参数     | 类型     | 默认 | 说明       |
| ------ | ------ | -- | -------- |
| cursor | string | "" | 游标（首次为空） |
| limit  | int    | 10 | 每页条数     |

**响应：**

```json
{
  "code": "00000",
  "data": {
    "list": [],
    "cursor": null,
    "has_more": false
  }
}
```

`list` 中每条记录为 `LiveRoom` 对象，结构如下（**仅首页列表返回的字段**，不含 play 信息——播放地址需单独拉取详情）：

| 字段          | 类型        | 说明                                                 |
| ----------- | --------- | -------------------------------------------------- |
| room\_id    | int       | 房间 ID                                              |
| room\_no    | string    | 房间号                                                |
| title       | string    | 房间标题                                               |
| subtitle    | string    | 副标题                                                |
| status      | string    | 房间状态文本                                             |
| sort\_score | int       | 排序分                                                |
| cover\_url  | string    | 封面图 URL                                            |
| persona     | object    | `{ id, name, tags:[] }` 人设信息                       |
| display     | object    | `{ badge_text, online_text, like_text }` 展示信息      |
| state       | object    | `{ mode: "public", privilege_active: false }` 房间状态 |
| room\_tags  | string\[] | 房间标签                                               |

### 3.3 房间详情

```
GET /api/v1/rooms/{room_id}
```

返回完整房间信息，包括播放地址和礼物面板。

**响应中的额外字段（相比列表接口）：**

| 字段                  | 类型     | 说明                                                                        |
| ------------------- | ------ | ------------------------------------------------------------------------- |
| preview\_video\_url | string | 预览视频 URL                                                                  |
| play                | object | `{ stream_alias, webrtc_url, hls_url, play_token, expire_at }`            |
| interaction         | object | `{ allow_chat: true, allow_like: true, allow_gift: true }`                |
| gift\_panel         | object | `{ currency_name: "钻石", quick_gifts: [...] }`                             |
| binding             | object | `{ room_group_code, room_group_name, playlist_name, srs_enabled }` 后端绑定信息 |

**礼物列表项 (quick\_gifts)：**

| 字段                     | 类型     | 说明                                        |
| ---------------------- | ------ | ----------------------------------------- |
| gift\_id               | int    | 礼物 ID                                     |
| name                   | string | 礼物名称（如"玫瑰"、"专属礼物"）                        |
| price                  | number | 价格（钻石）                                    |
| trigger\_mode          | string | 触发模式：`none` / `privilege` / `interaction` |
| trigger\_duration\_sec | int    | 触发时长（秒），如 `30`                            |

### 3.4 房间切换

| 方法   | 路由                                  | 说明                                                   |
| ---- | ----------------------------------- | ---------------------------------------------------- |
| POST | `/api/v1/rooms/switch/privilege`    | 触发特权 `{ room_id, gift_id, duration_sec, gift_name }` |
| POST | `/api/v1/rooms/switch/confirm`      | 确认切换完成 `{ room_id, task_id, target_mode }`           |
| POST | `/api/v1/rooms/switch/fail`         | 切换失败 `{ room_id, task_id, reason }`                  |
| POST | `/api/v1/rooms/switch/expire-check` | 过期检查                                                 |
| GET  | `/api/v1/rooms/switch/status`       | 查询切换状态 `?room_id=1`                                  |

***

## 四、WebSocket 信令协议

**连接地址**：`ws://{host}:8788`

### 4.1 鉴权

**正式用户（JWT）：**

```json
{
  "type": "auth",
  "trace_id": "...",
  "token": "{access_token}",
  "room_id": 1
}
```

**游客模式（降级）：**

```json
{
  "type": "auth",
  "trace_id": "...",
  "play_token": "{房间play_token}",
  "expire_at": 1769149800,
  "nickname": "现场观众",
  "room_id": 1
}
```

### 4.2 客户端 → 服务端

| type         | 说明      | payload                                    |
| ------------ | ------- | ------------------------------------------ |
| `auth`       | 鉴权      | 见上                                         |
| `heartbeat`  | 心跳（15s） | `{ trace_id }`                             |
| `join_room`  | 加入房间    | `{ room_id, trace_id }`                    |
| `leave_room` | 离开房间    | `{ trace_id }`                             |
| `send_chat`  | 发弹幕     | `{ room_id, content, trace_id }`           |
| `send_gift`  | 送礼物     | `{ room_id, gift_id, quantity, trace_id }` |

### 4.3 服务端 → 客户端

| type                | 触发时机       | data 核心字段                                                        |
| ------------------- | ---------- | ---------------------------------------------------------------- |
| `auth_ok`           | 鉴权成功       | `{ user_id, user_no, nickname, auth_mode }`                      |
| `joined_room`       | 加入房间成功     | `{ room_id }`                                                    |
| `room_snapshot`     | 入房 / 在线数变化 | `{ online_count, like_count }`                                   |
| `chat_message`      | 收到弹幕       | `{ message_id, user: { nickname }, content }`                    |
| `gift_message`      | 收到礼物       | `{ order_no, gift: { name, trigger_mode }, user: { nickname } }` |
| `privilege_started` | 特权流开始      | `{ duration_sec, gift_name }`                                    |
| `privilege_ended`   | 特权流结束      | `{}`                                                             |
| `stream_reload`     | 需要重载播放器    | `{}`                                                             |
| `error`             | 错误消息       | `{ code, msg }`                                                  |

***

## 五、后台管理 API

> **Base**：`http://{host}:{port}/admin/`
>
> **格式**：`POST /admin/live.{Controller}/{action}`
>
> **鉴权**：BuildAdmin 后台会话（Cookie session 或 Token）
>
> 所有列表接口返回格式：`{ code: "00000", data: { list, total }, msg: "成功" }`
>
> 所有增删改返回：`{ code: "00000", data: null, msg: "xxx成功" }`

### 5.1 人设管理 `live.Persona`

| 方法     | URL                          | 说明                      |
| ------ | ---------------------------- | ----------------------- |
| `POST` | `/admin/live.Persona/index`  | 人设列表（分页）                |
| `POST` | `/admin/live.Persona/add`    | 新增人设                    |
| `POST` | `/admin/live.Persona/edit`   | 编辑人设                    |
| `POST` | `/admin/live.Persona/del`    | 删除人设                    |
| `POST` | `/admin/live.Persona/select` | 下拉选择（仅返回 status=1 的启用项） |

**字段说明：**

| 字段         | 类型          | 必填 | 说明                          |
| ---------- | ----------- | -- | --------------------------- |
| code       | string(32)  | 是  | 人设编码，唯一（如 `shenye_qinggan`） |
| name       | string(64)  | 是  | 人设名称（如"深夜情感电台"）             |
| tags       | string(255) | 是  | 标签，逗号分隔（如"情感,深夜,电台"）        |
| cover\_url | string(255) | 否  | 封面图 URL                     |
| status     | int         | 是  | 0=禁用, 1=启用                  |

### 5.2 素材管理 `live.MediaAsset`

| 方法     | URL                             | 说明                                     |
| ------ | ------------------------------- | -------------------------------------- |
| `POST` | `/admin/live.MediaAsset/index`  | 素材列表（分页，支持 scene\_type/asset\_type 筛选） |
| `POST` | `/admin/live.MediaAsset/add`    | 新增素材                                   |
| `POST` | `/admin/live.MediaAsset/edit`   | 编辑素材                                   |
| `POST` | `/admin/live.MediaAsset/del`    | 删除素材                                   |
| `POST` | `/admin/live.MediaAsset/select` | 下拉选择（支持筛选）                             |

**字段说明：**

| 字段           | 类型          | 必填   | 说明                                                                      |
| ------------ | ----------- | ---- | ----------------------------------------------------------------------- |
| asset\_code  | string(64)  | 自动生成 | 素材编码（M + 时间戳）                                                           |
| asset\_type  | string(32)  | 是    | 素材类型：`video` / `image` / `audio` / `subtitle`                           |
| scene\_type  | string(32)  | 是    | 场景类型：`public`(公共待机) / `privilege`(特权) / `interaction`(互动) / `cover`(封面) |
| title        | string(128) | 是    | 素材标题                                                                    |
| file\_url    | string(255) | 是    | 文件 CDN URL                                                              |
| duration\_ms | int         | 否    | 时长（毫秒），视频/音频素材填写                                                        |
| status       | int         | 是    | 0=禁用, 1=启用                                                              |

> **scene\_type 用途**：
>
> - `public`：公共流的待机轮播素材（观众没送礼时循环播放）
> - `privilege`：特权流素材（送礼触发后播放的特权视频）
> - `interaction`：互动流素材（AI 生成的回放文件引用此类型）
> - `cover`：封面图素材

### 5.3 房间管理 `live.Room`

| 方法     | URL                       | 说明                 |
| ------ | ------------------------- | ------------------ |
| `POST` | `/admin/live.Room/index`  | 房间列表（分页）           |
| `POST` | `/admin/live.Room/add`    | 新增房间               |
| `POST` | `/admin/live.Room/edit`   | 编辑房间               |
| `POST` | `/admin/live.Room/del`    | 删除房间（联动删除绑定/播单/标签） |
| `POST` | `/admin/live.Room/select` | 下拉选择               |

**字段说明：**

| 字段             | 类型          | 必填   | 说明                      |
| -------------- | ----------- | ---- | ----------------------- |
| room\_no       | string(32)  | 自动生成 | 房间号（R + 时间戳）            |
| title          | string(128) | 是    | 房间标题                    |
| subtitle       | string(255) | 否    | 副标题                     |
| persona\_id    | int         | 是    | 绑定人设 ID                 |
| room\_type     | string(32)  | 是    | 房间类型，默认 `live`          |
| status         | int         | 是    | 0=关闭, 1=启用, 2=维护        |
| cover\_url     | string(255) | 否    | 封面图 URL                 |
| sort           | int         | 否    | 排序分（越大越靠前）              |
| tag\_names     | string      | 否    | 标签，逗号分隔（如"热门,推荐,情感"）    |
| asset\_ids     | string      | 否    | 素材 ID 列表，逗号分隔（如"1,2,3"） |
| playlist\_name | string      | 否    | 播单名称                    |

**创建房间时的联动操作：**

1. 插入 `lp_room` 记录
2. 插入 `lp_room_tag` 标签记录
3. 插入 `lp_room_binding`（绑默认流模板 + 房间分组）
4. 插入 `lp_playlist_template`（播单模板）
5. 插入 `lp_playlist_template_item`（播单项，按 asset\_ids 顺序排列）

### 5.4 礼物管理 `live.Gift`

| 方法     | URL                       | 说明       |
| ------ | ------------------------- | -------- |
| `POST` | `/admin/live.Gift/index`  | 礼物列表（分页） |
| `POST` | `/admin/live.Gift/add`    | 新增礼物     |
| `POST` | `/admin/live.Gift/edit`   | 编辑礼物     |
| `POST` | `/admin/live.Gift/del`    | 删除礼物     |
| `POST` | `/admin/live.Gift/select` | 下拉选择     |

**字段说明：**

| 字段                     | 类型            | 必填 | 说明                                                             |
| ---------------------- | ------------- | -- | -------------------------------------------------------------- |
| gift\_code             | string(32)    | 是  | 礼物编码，唯一（如 `rose`、`vip_30s`）                                    |
| name                   | string(64)    | 是  | 礼物名称（如"玫瑰"、"专属礼物"）                                             |
| price\_diamond         | decimal(18,2) | 是  | 价格（钻石数）                                                        |
| trigger\_mode          | string(32)    | 是  | 触发模式：`none`(普通礼物) / `privilege`(触发特权流) / `interaction`(触发互动插播) |
| trigger\_duration\_sec | int           | 否  | 触发时长（秒），trigger\_mode 不为 none 时填写                              |
| effect\_code           | string(64)    | 否  | 特效编码（前端动效匹配用）                                                  |
| status                 | int           | 是  | 0=下架, 1=启用                                                     |

**触发模式说明：**

- `none`：普通礼物，发送后只广播消息，不改变房间播放状态
- `privilege`：特权礼物，发送后触发房间切换到特权流（`public_live` → `switching` → `privilege_live`），到期自动切回
- `interaction`：互动礼物，发送后触发 AI 互动插播队列

**现有数据：**

| gift\_code | name | price\_diamond | trigger\_mode | trigger\_duration\_sec |
| ---------- | ---- | -------------- | ------------- | ---------------------- |
| `rose`     | 玫瑰   | 10             | none          | 0                      |
| `vip_30s`  | 专属礼物 | 199            | privilege     | 30                     |

***

## 六、定时任务

在 ThinkPHP 项目根目录执行：

```bash
# AI 任务过期 + 推流超时检查（建议每分钟执行）
php think ai:task:cron

# 特权切换过期检查（建议每 5 秒执行）
php think room:switch:cron
```

| 命令                 | 功能                                                                                                                                                                  |
| ------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `ai:task:cron`     | 1. 扫描 `lp_ai_task` 中 `status=pending` 且 `deadline_at <= now` 的任务，标记为 `expired`；2. 扫描 `lp_room_switch_task` 中 `to_mode=interaction` 且已超 `max_stream_sec` 的任务，自动切回公共流 |
| `room:switch:cron` | 扫描 `lp_room_switch_task` 中 `status=accepted` 且到期时间已过的特权切换任务，自动切回公共流                                                                                                 |

***

## 七、数据库 ER 关系速查

```
lp_persona (人设)
  │
  └──→ lp_room (房间)
         │
         ├──→ lp_room_tag (标签)
         │
         ├──→ lp_room_binding
         │      ├──→ lp_room_group (房间分组)
         │      ├──→ lp_stream_template (流模板: webrtc_app, stream_alias_prefix)
         │      └──→ lp_playlist_template (播单模板)
         │             └──→ lp_playlist_template_item
         │                    └──→ lp_media_asset (素材: file_url, scene_type, duration_ms)
         │
         └──→ lp_room_state_snapshot (状态快照: current_state, current_mode, privilege_expire_at)

lp_gift (礼物: price_diamond, trigger_mode, trigger_duration_sec)
  │
  └──→ lp_gift_order (礼物订单)

lp_ai_task (AI任务: content, status, video_url, duration_sec)
  │
  └──→ lp_ai_task_log (任务日志)

lp_room_play_task (播放任务: task_type=interaction, ref_task_id→lp_ai_task.id)
lp_room_switch_task (切换任务: from_mode, to_mode, status, duration_sec)
```

***

## 八、AI 客户端完整工作流

### 8.1 预生成视频模式

```
┌─────────────────────────────────────────────────────────────────┐
│ 1. 启动                                                         │
│    配置 X-Api-Key 头                                            │
│    循环调用 GET /api/v1/ai/tasks/pull?count=10                   │
│    （阻塞模式，有任务当场返回，无任务 3 秒后返回空）              │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│ 2. 发现新任务                                                   │
│    POST /api/v1/ai/tasks/accept  { task_id }                    │
│    → 读取 content 字段（观众弹幕文本）                            │
│    → 读取 persona_id 字段（人设 ID，用于风格匹配）               │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│ 3. 生成互动视频                                                 │
│    AI 根据 content + persona 生成视频文件                        │
│    → 可选：POST progress 上报进度                               │
│    → 生成完成后上传到 CDN                                       │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│ 4. 回调完成                                                     │
│    POST /api/v1/ai/tasks/complete                               │
│    { task_id, video_url, duration_sec, cover_url? }              │
│    → 平台自动：播单入队 → 状态机切换 → Channel Worker 指令下发   │
│    → ws-webman 广播 interaction_ready 给前端                    │
└─────────────────────────────────────────────────────────────────┘
```

### 8.2 实时推流模式（含 3 层结束检测）

```
┌─────────────────────────────────────────────────────────────────┐
│ 1. 启动                                                         │
│    配置 X-Api-Key 头                                            │
│    循环调用 GET /api/v1/ai/tasks/pull?count=10                   │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│ 2. 接单 + 获取推流参数                                          │
│    POST /api/v1/ai/tasks/accept  { task_id }                    │
│    GET /api/v1/ai/tasks/stream-token?room_id=1                  │
│    → 拿到 RTMP push_url + token + max_stream_sec               │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│ 3. 实时推流                                                     │
│    ffmpeg -re -i <input> -c:v libx264 ... -f flv {push_url}     │
│    → AI 推流到 SRS，前端实时播放 HLS                             │
│    → 可选：POST progress 上报进度                               │
└─────────────────────────────────────────────────────────────────┘
                              │ (推流结束)
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│ 4. 3 层结束检测机制                                             │
│                                                                 │
│ 第1层（主通道）：AI 主动通知 → POST /api/v1/ai/tasks/stream-end │
│   { task_id, duration_sec }                                     │
│                                                                 │
│ 第2层（保底）：SRS 自动回调 → POST /api/v1/srs/unpublish        │
│   SRS 检测到 RTMP 断开后自动通知平台                             │
│                                                                 │
│ 第3层（兜底）：定时任务超时 → php think ai:task:cron            │
│   房间在 interaction_live 状态超过 max_stream_sec 自动切回       │
│                                                                 │
│ → 平台自动：状态机切回 public → Channel Worker 切回公共流       │
│ → ws-webman 广播 interaction_ended 给前端                       │
└─────────────────────────────────────────────────────────────────┘
```

**任务状态流转：**

```
pending → accepted → processing → completed (回调成功 / 推流结束)
                   ↘               ↘
                    expired         failed (超时/失败)
```

**任务创建来源：**

- `source_type=chat`：观众弹幕触发（ChatService 自动调用 `/api/v1/ai/tasks/create`）
- `source_type=gift`：礼物触发（预留，GiftService 内未接入）
- `source_type=system`：系统触发（预留）

