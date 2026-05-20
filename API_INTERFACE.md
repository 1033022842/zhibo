# 当前接口文档

适用范围：
- `php` 后端当前已实现接口
- `apps/ws-webman` 当前已实现 WebSocket 协议
- 供前端联调用

## 1. 基础信息

### 1.1 HTTP

- 本地基地址：`http://127.0.0.1:8083`
- 当前接口示例统一携带：`?server=1`
- 鉴权头：

```http
Authorization: Bearer {access_token}
```

### 1.2 WebSocket

- 本地地址：`ws://127.0.0.1:8788`
- 连接成功后，先发 `auth`
- 业务异常不依赖 HTTP 状态码，统一看返回体里的 `code`

## 2. HTTP 返回结构

### 2.1 成功

```json
{
  "code": "00000",
  "msg": "ok",
  "data": {}
}
```

### 2.2 游标分页

```json
{
  "code": "00000",
  "msg": "ok",
  "data": {
    "list": [],
    "cursor": "",
    "has_more": false
  }
}
```

### 2.3 失败

```json
{
  "code": "A0100",
  "msg": "Token无效",
  "data": null
}
```

## 3. 用户模块

### 3.1 注册

- 方法：`POST`
- 路径：`/api/live/register?server=1`
- 是否鉴权：否

请求体：

```json
{
  "username": "test001",
  "password": "123456",
  "nickname": "测试用户"
}
```

成功响应：

```json
{
  "code": "00000",
  "msg": "ok",
  "data": {
    "user_id": 1,
    "user_no": "U202605170001",
    "nickname": "测试用户"
  }
}
```

### 3.2 登录

- 方法：`POST`
- 路径：`/api/live/login?server=1`
- 是否鉴权：否

请求体：

```json
{
  "username": "test001",
  "password": "123456"
}
```

成功响应：

```json
{
  "code": "00000",
  "msg": "ok",
  "data": {
    "access_token": "jwt",
    "refresh_token": "jwt",
    "expire_in": 7200,
    "user": {
      "id": 1,
      "user_no": "U202605170001",
      "nickname": "测试用户",
      "avatar": "",
      "gender": 0,
      "bio": ""
    }
  }
}
```

### 3.3 刷新 Token

- 方法：`POST`
- 路径：`/api/live/refreshToken?server=1`
- 是否鉴权：否

请求体：

```json
{
  "refresh_token": "jwt"
}
```

成功响应结构与登录相同。

### 3.4 获取个人资料

- 方法：`GET`
- 路径：`/api/live/profile?server=1`
- 是否鉴权：是

成功响应：

```json
{
  "code": "00000",
  "msg": "ok",
  "data": {
    "id": 1,
    "user_no": "U202605170001",
    "nickname": "测试用户",
    "avatar": "",
    "gender": 0,
    "bio": ""
  }
}
```

### 3.5 更新个人资料

- 方法：`PUT`
- 路径：`/api/live/updateProfile?server=1`
- 是否鉴权：是

请求体：

```json
{
  "nickname": "新昵称",
  "gender": 1,
  "bio": "hello"
}
```

成功响应：

```json
{
  "code": "00000",
  "msg": "ok",
  "data": null
}
```

### 3.6 退出登录

- 方法：`POST`
- 路径：`/api/live/logout?server=1`
- 是否鉴权：是

请求体：

```json
{
  "refresh_token": "jwt"
}
```

成功响应：

```json
{
  "code": "00000",
  "msg": "ok",
  "data": null
}
```

## 4. 房间与人设

### 4.1 直播流列表

- 方法：`GET`
- 路径：`/api/v1/feed/live?server=1&limit=10&cursor=`
- 是否鉴权：否

查询参数：
- `limit`：每页数量，默认 `10`，最大 `20`
- `cursor`：游标，首屏可不传

成功响应：

```json
{
  "code": "00000",
  "msg": "ok",
  "data": {
    "list": [
      {
        "room_id": 1,
        "room_no": "R1001",
        "title": "深夜情感电台",
        "subtitle": "陪你聊天到天亮",
        "status": "living",
        "sort_score": 100,
        "cover_url": "https://cdn.example.com/room/1001.jpg",
        "preview_video_url": "https://cdn.example.com/room/1001.jpg",
        "persona": {
          "id": 1,
          "name": "夜聊陪伴",
          "tags": ["温柔", "陪伴", "夜间"]
        },
        "display": {
          "badge_text": "热门",
          "online_text": "0",
          "like_text": "0"
        },
        "state": {
          "mode": "public",
          "privilege_active": false,
          "privilege_expire_at": 0,
          "room_group_code": "group_demo"
        },
        "play": {
          "stream_alias": "room/1",
          "webrtc_url": "webrtc://127.0.0.1/live/room/1",
          "hls_url": "http://127.0.0.1:8083/hls/room/1.m3u8",
          "play_token": "token",
          "expire_at": 1778990000
        },
        "interaction": {
          "allow_chat": true,
          "allow_like": true,
          "allow_gift": true
        },
        "gift_panel": {
          "currency_name": "钻石",
          "quick_gifts": [
            {"gift_id": 1, "name": "玫瑰", "price": 10},
            {"gift_id": 12, "name": "特权礼物", "price": 199, "trigger_mode": "privilege", "trigger_duration_sec": 30}
          ]
        },
        "room_tags": ["热门", "情感"]
      }
    ],
    "cursor": "",
    "has_more": false
  }
}
```

### 4.2 房间详情

- 方法：`GET`
- 路径：`/api/v1/rooms/{id}?server=1`
- 是否鉴权：否

示例：
- `/api/v1/rooms/1?server=1`

成功响应：

```json
{
  "code": "00000",
  "msg": "ok",
  "data": {
    "room_id": 1,
    "room_no": "R1001",
    "title": "深夜情感电台",
    "subtitle": "陪你聊天到天亮",
    "status": "living",
    "sort_score": 100,
    "cover_url": "https://cdn.example.com/room/1001.jpg",
    "preview_video_url": "https://cdn.example.com/room/1001.jpg",
    "persona": {
      "id": 1,
      "name": "夜聊陪伴",
      "tags": ["温柔", "陪伴", "夜间"]
    },
    "state": {
      "mode": "public",
      "privilege_active": false,
      "privilege_expire_at": 0,
      "room_group_code": "group_demo"
    },
    "play": {
      "stream_alias": "room/1",
      "webrtc_url": "webrtc://127.0.0.1/live/room/1",
      "hls_url": "http://127.0.0.1:8083/hls/room/1.m3u8",
      "play_token": "token",
      "expire_at": 1778990000
    },
    "interaction": {
      "allow_chat": true,
      "allow_like": true,
      "allow_gift": true
    },
    "gift_panel": {
      "currency_name": "钻石",
      "quick_gifts": [
        {"gift_id": 1, "name": "玫瑰", "price": 10},
        {"gift_id": 12, "name": "特权礼物", "price": 199, "trigger_mode": "privilege", "trigger_duration_sec": 30}
      ]
    },
    "room_tags": ["热门", "情感"],
    "tags": ["热门", "情感"],
    "binding": {
      "room_group_id": 1,
      "source_group_code": "group_demo",
      "stream_template_id": 1,
      "playlist_template_id": 1,
      "stream_template_code": "default_live"
    }
  }
}
```

房间不存在：

```json
{
  "code": "E0100",
  "msg": "房间不存在",
  "data": null
}
```

## 5. WebSocket 协议

### 5.1 通用包结构

客户端发包：

```json
{
  "type": "message_type",
  "trace_id": "uuid-or-custom-id"
}
```

服务端回包：

```json
{
  "type": "message_type",
  "trace_id": "same-as-request",
  "code": "00000",
  "msg": "ok",
  "data": {},
  "ts": 1778980000
}
```

### 5.2 鉴权

客户端发送：

```json
{
  "type": "auth",
  "trace_id": "t-auth",
  "token": "access_token"
}
```

服务端响应：

```json
{
  "type": "auth_ok",
  "trace_id": "t-auth",
  "code": "00000",
  "msg": "ok",
  "data": {
    "user_id": 3,
    "user_no": "U202605170001",
    "nickname": "Test004"
  },
  "ts": 1778980000
}
```

### 5.3 心跳

客户端发送：

```json
{
  "type": "heartbeat",
  "trace_id": "t-heartbeat",
  "ts": 1778980000
}
```

服务端响应：

```json
{
  "type": "heartbeat_ack",
  "trace_id": "t-heartbeat",
  "code": "00000",
  "msg": "ok",
  "data": {
    "server_ts": 1778980000
  },
  "ts": 1778980000
}
```

说明：
- 服务端当前配置为 `30` 秒无心跳断开

### 5.4 入房

客户端发送：

```json
{
  "type": "join_room",
  "trace_id": "t-join",
  "room_id": 1
}
```

服务端先回入房确认：

```json
{
  "type": "joined_room",
  "trace_id": "t-join",
  "code": "00000",
  "msg": "ok",
  "data": {
    "room_id": 1
  },
  "ts": 1778980000
}
```

随后推房间快照：

```json
{
  "type": "room_snapshot",
  "trace_id": "t-join",
  "code": "00000",
  "msg": "ok",
  "data": {
    "room_id": 1,
    "state": "public_live",
    "online_count": 1,
    "like_count": 0,
    "current_mode": "public",
    "privilege_expire_at": 0
  },
  "ts": 1778980000
}
```

说明：
- `online_count` 由 WebSocket 服务基于 Redis key `room:online:{room_id}` 实时维护
- `like_count` 字段已预留，当前默认 `0`
- 同一连接切房时，旧房间和新房间都会收到新的 `room_snapshot`

### 5.5 离房

客户端发送：

```json
{
  "type": "leave_room",
  "trace_id": "t-leave",
  "room_id": 1
}
```

服务端响应：

```json
{
  "type": "left_room",
  "trace_id": "t-leave",
  "code": "00000",
  "msg": "ok",
  "data": {
    "room_id": 1
  },
  "ts": 1778980000
}
```

说明：
- 离房成功后，房间内其他连接会收到新的 `room_snapshot`
- 当前已实现在线人数动态变化：进房 `+1`，离房 `-1`

### 5.6 发送弹幕

客户端发送：

```json
{
  "type": "send_chat",
  "trace_id": "t-chat",
  "room_id": 1,
  "content": "你好呀"
}
```

成功时，房间内广播：

```json
{
  "type": "chat_message",
  "trace_id": "t-chat",
  "code": "00000",
  "msg": "ok",
  "data": {
    "room_id": 1,
    "message_id": 1,
    "user": {
      "id": 3,
      "nickname": "Test004"
    },
    "content": "你好呀",
    "created_at": 1778980000
  },
  "ts": 1778980000
}
```

当前行为：
- 必须先 `auth`
- 必须先 `join_room`
- 会写入 `lp_chat_message`
- 命中敏感词时只落库，不广播
- 代码中会尝试向 Redis 执行 `XADD stream:danmu:ingest ...`
- 如果本地 Redis 版本不支持 Stream 命令，会自动忽略该步骤，不影响前端联调

### 5.7 WebSocket 错误码

- `WS0401`：未鉴权 / Token 无效
- `WS0001`：消息格式错误或未知消息类型
- `WS0002`：`room_id` 非法
- `WS2001`：请先加入房间
- `WS2002`：消息不能为空
- `WS2003`：消息长度超过限制
- `WS2004`：发送过于频繁
- `WS2005`：消息包含敏感词，已拦截
- `WS5000`：服务端处理失败

## 6. 前端联调顺序建议

### 6.1 HTTP

1. 登录拿 `access_token`
2. 拉首页 `GET /api/v1/feed/live`
3. 进入详情 `GET /api/v1/rooms/{id}`

### 6.2 WebSocket

1. 建立 `ws://127.0.0.1:8788`
2. 发送 `auth`
3. 发送 `join_room`
4. 定时发送 `heartbeat`
5. 发送 `send_chat`
6. 监听 `room_snapshot` / `chat_message`

### 6.3 实时状态展示

建议前端使用规则：

1. 进入房间后以最新一条 `room_snapshot.data.online_count` 更新在线人数
2. 使用 `room_snapshot.data.like_count` 作为点赞数展示字段
3. 当前点赞数为预留字段，后续实现 `2.3` 后可直接复用，无需改字段名

## 7. 说明

- 当前文档基于本地已实现代码生成
- 如果后续继续做 `2.3` 点赞、`2.4` 在线人数聚合，这份文档需要继续补充
