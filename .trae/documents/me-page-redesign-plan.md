# /me 个人中心页重设计方案

## 一、现状分析

### 当前页面问题

当前的 `Me.vue`（896行）是一个完整的抖音个人主页克隆版，包含大量平台根本不存在的内容：

* 封面背景图、视频作品网格（作品/私密/喜欢/收藏四个 tab）

* 学校信息（添加学校、选择院系、展示类型）

* 关注/粉丝/获赞统计

* 抖音号、二维码名片、求更新

* 右侧抽屉菜单（订单、钱包、观看历史、创作者中心、小程序、未成年保护工具等）

* 全部数据来自 mock，调用的是 `/user/panel`、`/video/my`、`/video/like` 等不存在的接口

### 后台已有的真实能力

| 接口                             | 说明     | 返回数据                                                                        |
| ------------------------------ | ------ | --------------------------------------------------------------------------- |
| `GET /api/live/profile`        | 获取个人信息 | id, user\_no, nickname, avatar, level, status, gender, bio, last\_login\_at |
| `PUT /api/live/update-profile` | 修改个人信息 | nickname, gender (0/1/2), bio                                               |
| `POST /api/live/logout`        | 退出登录   | —                                                                           |

### 数据存储侧已有的能力

* `lp_user`：id, user\_no, nickname, avatar, status, level

* `lp_user_profile`：user\_id, gender, bio, country\_code, last\_login\_ip, last\_login\_at

* `lp_wallet_account`：diamond\_balance（**暂无查询 API**）

* `lp_gift_order`：送礼订单记录（**暂无查询 API**）

***

## 二、设计原则

> **"贴合现在后台已有功能，不要多余的东西"**

1. **只展示有 API 支撑的数据** — 不展示钱包余额（无 API）、不展示统计数字（无接口）
2. **保持平台风格一致** — 沿用现有直播平台的暗色主题、字体、间距体系
3. **简洁干净** — 个人中心只做信息展示 + 编辑 + 退出，不做多余菜单
4. **复用现有基础设施** — 使用已有的 request 封装、JWT 认证、Pinia store、全局组件

***

## 三、页面结构设计

```
┌────────────────────────────────────┐
│         个人中心 (Header)           │
├────────────────────────────────────┤
│                                    │
│         ┌────────────┐             │
│         │   头像      │  (可点击更换) │
│         │   (圆形)    │             │
│         └────────────┘             │
│                                    │
│          用户昵称                    │
│          ID: U20240xxx             │
│          Lv.1  │  ♂ 男             │
│                                    │
│   ┌─────────────────────────────┐  │
│   │ 个人简介: xxx                 │  │
│   └─────────────────────────────┘  │
│                                    │
│   ┌────────── 编辑资料 ──────────┐  │
│   └──────────────────────────────┘  │
│                                    │
│   ┌──────────────────────────┐     │
│   │ 🔐 账号安全              > │     │
│   ├──────────────────────────┤     │
│   │ ℹ️ 关于平台              > │     │
│   ├──────────────────────────┤     │
│   │ 🚪 退出登录               │     │
│   └──────────────────────────┘     │
│                                    │
└────────────────────────────────────┘
```

### 3.1 顶部区域 — 个人信息卡片

* **头像**：大圆形头像，`lp_user.avatar`，默认占位图

  * 待定：是否需要头像上传？目前后台没有头像上传 API，先做只读展示

* **昵称**：大字号，`lp_user.nickname`

* **用户编号**：小字灰色，`lp_user.user_no`

* **等级 & 性别**：标签式展示，level → "Lv.X"，gender → "♂男/♀女/未知"

### 3.2 中间 — 个人简介

* 显示 `lp_user_profile.bio`，为空时显示 "暂无简介，去编辑吧\~"

* 与编辑资料联动

### 3.3 编辑资料入口

* 按钮形式，点击进入编辑弹窗/页面

* 可编辑字段（对应 `PUT /api/live/update-profile`）：

  * 昵称（nickname）

  * 性别（gender：0未知/1男/2女）

  * 个人简介（bio）

* 保存后刷新本页数据

### 3.4 功能列表（菜单项）

* **账号安全**：修改密码（如有 API 则进入）、绑定信息展示

* **关于平台**：版本号、平台介绍

* **退出登录**：红色文字，点击确认后调用 `POST /api/live/logout`，清除本地 token，跳转登录页

***

## 四、技术实现计划

### 4.1 前端改动范围

| 文件                                           | 操作     | 说明                              |
| -------------------------------------------- | ------ | ------------------------------- |
| `vue/src/pages/me/Me.vue`                    | **重写** | 全新组件，移除所有 mock 依赖               |
| `vue/src/pages/me/Me.less`                   | **重写** | 对应新设计的样式                        |
| `vue/src/pages/me/userinfo/EditUserInfo.vue` | **重写** | 只保留后端支持的3个字段                    |
| `vue/src/pages/me/rightMenu/Setting.vue`     | **重写** | 精简为 账号安全 + 关于 + 退出              |
| `vue/src/router/routes.ts`                   | **修改** | 移除不需要的 /me 子路由                  |
| `vue/src/store/pinia.ts`                     | **修改** | 确保 auth 信息正确同步到 userinfo        |
| `vue/src/api/live.ts`                        | **修改** | 新增 profile/updateProfile API 封装 |

### 4.2 具体实现步骤

#### 步骤 1：清理路由

* 删除 `routes.ts` 中所有不需要的 `/me/*` 子路由

* 保留：`/me`、`/me/edit-userinfo`、`/me/setting`

* 其余全部移除（school、country、card、collect、music、look-history、minor-protection、request-update 等）

#### 步骤 2：新增 API 封装

* 在 `vue/src/api/live.ts` 新增：

  * `getProfile()` → `GET /api/live/profile`

  * `updateProfile(data)` → `PUT /api/live/update-profile`

#### 步骤 3：Store 层补充

* 在 `pinia.ts` 补充 `fetchProfile()` 调用的数据同步到 userinfo

* 保证 Me.vue 可以直接从 store 读取最新 profile 数据

#### 步骤 4：重写 Me.vue

* 使用 Composition API（`<script setup lang="ts">`）或保持 Options API 风格（与项目一致，项目目前用 Options API）

* 从 pinia store 读取 `authNickname`、`authAvatar` 等作为初始值

* `onMounted` 时调用 `getProfile()` 获取最新数据

* 布局：flex column，顶部头像区 + 信息 + 菜单列表

* 退出登录：调用 store 的 `doLogout()`，清除 token，跳转登录页

#### 步骤 5：重写 EditUserInfo.vue

* 表单字段：昵称（input）、性别（选择器：男/女/未知）、个人简介（textarea）

* 提交：调用 `updateProfile()`

* 成功后返回上一页并触发数据刷新

#### 步骤 6：重写 Setting.vue

* 账号安全：展示绑定的认证方式（email/mobile）

* 关于平台：版本号

* 退出登录：确认弹窗 → 调用 logout → 清空 store → 跳转首页

#### 步骤 7：清理文件

* 删除不再使用的 /me 子页面组件文件夹

* 删除不再引用的 mock 数据文件（与 /me 相关的 mock 路由）

***

## 五、不需要做的（明确排除）

* ❌ 视频作品网格 / 收藏 / 喜欢列表 — 后台无此功能

* ❌ 关注/粉丝/获赞数字 — 后台无此统计

* ❌ 学校/院系/位置编辑 — 无关功能

* ❌ 二维码名片 — 无关功能

* ❌ 右侧抽屉菜单 — 精简为内联菜单

* ❌ 钱包余额展示 — 虽有数据库表但无查询 API

* ❌ 求更新 / 观看历史 / 未成年保护 — 无关功能

* ❌ 头像上传 — 后台无上传 API，后续按需增加

* ❌ 修改密码 — 后台无此 API（仅 BuildAdmin 的 ba\_user 有此接口，lp\_user 无），后续按需增加

