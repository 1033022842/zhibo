# 关键词视频推流重构方案

## 概述

将现有"固定顺序播单"推流架构改造为"关键词驱动、按需随机切换"的智能推流系统。

**素材库**：`d:\ever\douyin\douyin\视频成品` — 440 个白毛女角色多风格动作视频

---

## 决策汇总

| # | 决策项 | 结论 |
|---|--------|------|
| 1 | 角色 | 同一个白毛女多风格演绎，命名 `白毛女-关键词` |
| 2 | 触发源 | 观众送礼触发，弹幕不参与 |
| 3 | 礼物→关键词 | 独立配置映射表 `lp_gift_keyword` |
| 4 | 插播时机 | 当前视频播放结束后插入（不打断） |
| 5 | 播放方式 | 每个关键词下随机播放一个视频 |
| 6 | 实现方式 | ffmpeg stdin 管道 + PHP 控制写入，0 间隙无缝 |
| 7 | 视频入库 | 半自动提取关键词 + 人工补漏 |
| 8 | 正常播单 | 从关键词视频库随机轮播（public_live） |
| 9 | 特权模式 | 保留。public_live 随机轮播 / privilege_live 礼物触发 / interaction_live 待定 |
| 10 | 数据库 | 扩 `lp_media_asset` 加 `keywords/persona/weight` |
| 11 | 通信方式 | 复用现有 Redis Stream: `stream:room:switch` |
| 12 | 房间绑定 | `lp_room_binding` 简化，绑定 `persona` 代替 `playlist_template_id` |

---

## 数据库变更

### 1. `lp_media_asset` 扩展

```sql
ALTER TABLE `lp_media_asset`
  ADD COLUMN `keywords` VARCHAR(500) DEFAULT '' COMMENT '关键词标签，逗号分隔：比心,飞吻,挥手' AFTER `scene_type`,
  ADD COLUMN `persona` VARCHAR(64) DEFAULT '' COMMENT '人设：白毛女' AFTER `keywords`,
  ADD COLUMN `weight` INT NOT NULL DEFAULT 1 COMMENT '随机权重' AFTER `persona`,
  ADD INDEX `idx_persona_kw` (`persona`, `keywords`(100));
```

### 2. `lp_room_binding` 简化

```sql
ALTER TABLE `lp_room_binding`
  ADD COLUMN `persona` VARCHAR(64) DEFAULT '' COMMENT '绑定人设' AFTER `room_group_id`;

-- playlist_template_id 允许为 NULL（不再强制绑定固定播单）
ALTER TABLE `lp_room_binding`
  MODIFY COLUMN `playlist_template_id` BIGINT UNSIGNED NULL;
```

### 3. 新建 `lp_gift_keyword` 礼物关键词映射表

```sql
DROP TABLE IF EXISTS `lp_gift_keyword`;
CREATE TABLE `lp_gift_keyword` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `gift_id` BIGINT UNSIGNED NOT NULL COMMENT '礼物ID',
  `keyword` VARCHAR(64) NOT NULL COMMENT '触发关键词',
  `priority` INT NOT NULL DEFAULT 0 COMMENT '优先级(同礼物多关键词时)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_gift_kw` (`gift_id`, `keyword`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='礼物关键词映射';
```

### 4. `lp_gift` 扩展

```sql
ALTER TABLE `lp_gift`
  MODIFY COLUMN `trigger_mode` VARCHAR(32) NOT NULL DEFAULT 'keyword' COMMENT '触发模式:none privilege interaction keyword';
```

---

## 架构变更

### 推流方式对比

```
【旧】固定播单循环
  ffmpeg -f concat -i room_X_playlist.txt -stream_loop -1
  └── playlist.txt 是启动时一次性生成的静态文件，无法动态改

【新】stdin 管道控制
  ffmpeg -f concat -i pipe:0 ...
  └── channel-worker 逐行写入 stdin，写完一个视频等下一个
  └── 通过 Redis Stream 接收关键词指令，随时插入
```

### 数据流

```
观众送礼物 → GiftService → 查 lp_gift_keyword 获取关键词
  → 写 Redis Stream {room_id, action:'keyword', keyword:'比心'}
  → channel-worker 消费 → 从 lp_media_asset 随机取关键词视频
  → 写入 ffmpeg stdin → 无缝播放 → 播完回正常轮播
```

### ChannelWorker 新主循环

```
while(true):
  1. 检查 Redis Stream 有无关键词插播指令
     └── 有 → 从 DB 随机取关键词视频 → 写入 stdin
  2. 没有 → 从 DB 随机取一个正常轮播视频 → 写入 stdin
  3. 等 ffmpeg 播完当前视频（stdin 写入下一行即触发切换）
```

---

## 分步执行计划

### 步骤 1 — 数据库迁移
- 扩展 `lp_media_asset`（keywords/persona/weight）
- 简化 `lp_room_binding`（playlist_template_id 允许 NULL，新增 persona）
- 创建 `lp_gift_keyword` 表
- 扩展 `lp_gift`（trigger_mode 默认 keyword）

### 步骤 2 — 视频扫描入库脚本
- 扫描 `视频成品/` 下 440 个 mp4
- 半自动提取关键词（从文件名解析）
- 写入 `lp_media_asset`（persona=白毛女, keywords=提取的关键词）
- 输出无法自动识别的文件清单，人工标注

### 步骤 3 — ChannelWorker 重写
- 改为 stdin 管道模式（ffmpeg -f concat -i pipe:0）
- 实现 Redis Stream 消费（消费关键词插播指令）
- 实现关键词随机取视频逻辑
- 实现正常轮播随机取视频逻辑

### 步骤 4 — 礼物触发链路
- GiftService 增加关键词查询（lp_gift_keyword）
- 送礼后写入 Redis Stream 指令
- 掉现有的固定播单切换逻辑

### 步骤 5 — 管理后台适配
- 房间编辑：persona 选择代替 playlist_template
- 礼物管理：关键词映射配置界面
- 素材管理：关键词标签编辑

### 步骤 6 — 联调测试
- 正常轮播测试
- 礼物触发关键词测试
- 连续多个礼物触发排队测试
- 特权模式兼容测试
