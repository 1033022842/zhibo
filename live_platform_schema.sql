SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `live_platform`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `live_platform`;

-- =========================
-- 1. 用户与认证
-- =========================

DROP TABLE IF EXISTS `lp_user`;
CREATE TABLE `lp_user` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `user_no` VARCHAR(32) NOT NULL COMMENT '用户编号',
  `nickname` VARCHAR(64) NOT NULL COMMENT '昵称',
  `avatar` VARCHAR(255) DEFAULT '' COMMENT '头像',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态:0禁用 1正常',
  `level` INT NOT NULL DEFAULT 1 COMMENT '用户等级',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_no` (`user_no`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户主表';

DROP TABLE IF EXISTS `lp_user_auth`;
CREATE TABLE `lp_user_auth` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
  `auth_type` VARCHAR(32) NOT NULL COMMENT '登录方式:mobile email third_party',
  `auth_key` VARCHAR(128) NOT NULL COMMENT '手机号/邮箱/第三方唯一标识',
  `password_hash` VARCHAR(255) DEFAULT '' COMMENT '密码哈希',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_auth_type_key` (`auth_type`, `auth_key`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户认证表';

DROP TABLE IF EXISTS `lp_user_profile`;
CREATE TABLE `lp_user_profile` (
  `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
  `gender` TINYINT NOT NULL DEFAULT 0 COMMENT '性别:0未知 1男 2女',
  `bio` VARCHAR(255) DEFAULT '' COMMENT '简介',
  `country_code` VARCHAR(16) DEFAULT '' COMMENT '国家区号',
  `last_login_ip` VARCHAR(64) DEFAULT '' COMMENT '最近登录IP',
  `last_login_at` DATETIME DEFAULT NULL COMMENT '最近登录时间',
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户扩展资料';

DROP TABLE IF EXISTS `lp_user_device`;
CREATE TABLE `lp_user_device` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
  `device_id` VARCHAR(128) NOT NULL COMMENT '设备ID',
  `platform` VARCHAR(32) NOT NULL DEFAULT 'web' COMMENT '平台',
  `app_version` VARCHAR(32) DEFAULT '' COMMENT '应用版本',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_device` (`user_id`, `device_id`),
  KEY `idx_device_id` (`device_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户设备表';

-- =========================
-- 2. 钱包与支付
-- =========================

DROP TABLE IF EXISTS `lp_wallet_account`;
CREATE TABLE `lp_wallet_account` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
  `diamond_balance` DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT '钻石余额',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态:0冻结 1正常',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_wallet_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='钱包账户';

DROP TABLE IF EXISTS `lp_wallet_ledger`;
CREATE TABLE `lp_wallet_ledger` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
  `biz_type` VARCHAR(32) NOT NULL COMMENT '业务类型:recharge gift refund adjust',
  `direction` TINYINT NOT NULL COMMENT '方向:1收入 2支出',
  `asset_type` VARCHAR(32) NOT NULL DEFAULT 'diamond' COMMENT '资产类型,固定为diamond',
  `amount` DECIMAL(18,2) NOT NULL COMMENT '变动金额',
  `balance_before` DECIMAL(18,2) NOT NULL COMMENT '变动前余额',
  `balance_after` DECIMAL(18,2) NOT NULL COMMENT '变动后余额',
  `biz_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '业务ID',
  `remark` VARCHAR(255) DEFAULT '' COMMENT '备注',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_created` (`user_id`, `created_at`),
  KEY `idx_biz_type_biz_id` (`biz_type`, `biz_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='钱包流水';

DROP TABLE IF EXISTS `lp_recharge_order`;
CREATE TABLE `lp_recharge_order` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `order_no` VARCHAR(64) NOT NULL COMMENT '充值订单号',
  `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
  `pay_channel` VARCHAR(32) NOT NULL COMMENT '支付渠道:usdt_trc20',
  `chain_type` VARCHAR(32) NOT NULL DEFAULT 'TRC20' COMMENT '链类型',
  `pay_amount` DECIMAL(18,8) NOT NULL COMMENT '支付金额',
  `diamond_amount` DECIMAL(18,2) NOT NULL COMMENT '到账钻石数',
  `status` TINYINT NOT NULL DEFAULT 0 COMMENT '状态:0待支付 1已支付 2已过期 3关闭',
  `expire_at` DATETIME DEFAULT NULL COMMENT '过期时间',
  `paid_at` DATETIME DEFAULT NULL COMMENT '支付时间',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_recharge_order_no` (`order_no`),
  KEY `idx_user_status` (`user_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='充值订单';

DROP TABLE IF EXISTS `lp_payment_callback_log`;
CREATE TABLE `lp_payment_callback_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `order_no` VARCHAR(64) NOT NULL COMMENT '订单号',
  `gateway` VARCHAR(32) NOT NULL COMMENT '网关',
  `payload_hash` VARCHAR(64) NOT NULL COMMENT '回调指纹',
  `raw_payload` LONGTEXT NOT NULL COMMENT '回调原文',
  `verify_status` TINYINT NOT NULL DEFAULT 0 COMMENT '验签状态:0待处理 1成功 2失败',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_gateway_payload_hash` (`gateway`, `payload_hash`),
  KEY `idx_order_no` (`order_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='支付回调日志';

DROP TABLE IF EXISTS `lp_asset_exchange_rate`;
CREATE TABLE `lp_asset_exchange_rate` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `pay_channel` VARCHAR(32) NOT NULL COMMENT '支付渠道',
  `chain_type` VARCHAR(32) NOT NULL COMMENT '链类型',
  `pay_amount` DECIMAL(18,8) NOT NULL COMMENT '支付金额',
  `diamond_amount` DECIMAL(18,2) NOT NULL COMMENT '到账钻石',
  `rate_snapshot` DECIMAL(18,8) NOT NULL COMMENT '汇率快照',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_channel_chain_created` (`pay_channel`, `chain_type`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='充值汇率快照';

-- =========================
-- 3. 房间与人设
-- =========================

DROP TABLE IF EXISTS `lp_persona`;
CREATE TABLE `lp_persona` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `code` VARCHAR(32) NOT NULL COMMENT '人设编码',
  `name` VARCHAR(64) NOT NULL COMMENT '人设名称',
  `tags` VARCHAR(255) DEFAULT '' COMMENT '标签,逗号分隔',
  `cover_url` VARCHAR(255) DEFAULT '' COMMENT '封面',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_persona_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='数字人人设';

DROP TABLE IF EXISTS `lp_room`;
CREATE TABLE `lp_room` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `room_no` VARCHAR(32) NOT NULL COMMENT '房间编号',
  `title` VARCHAR(128) NOT NULL COMMENT '标题',
  `subtitle` VARCHAR(255) DEFAULT '' COMMENT '副标题',
  `persona_id` BIGINT UNSIGNED NOT NULL COMMENT '人设ID',
  `room_type` VARCHAR(32) NOT NULL DEFAULT 'live' COMMENT '房间类型',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态:0关闭 1启用 2维护',
  `cover_url` VARCHAR(255) DEFAULT '' COMMENT '封面',
  `sort` INT NOT NULL DEFAULT 0 COMMENT '排序值',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_room_no` (`room_no`),
  KEY `idx_persona_id` (`persona_id`),
  KEY `idx_status_sort` (`status`, `sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='直播房间';

DROP TABLE IF EXISTS `lp_room_group`;
CREATE TABLE `lp_room_group` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `name` VARCHAR(64) NOT NULL COMMENT '分组名称',
  `source_group_code` VARCHAR(64) NOT NULL COMMENT '共享源组编码',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_source_group_code` (`source_group_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='共享源房间分组';

DROP TABLE IF EXISTS `lp_stream_template`;
CREATE TABLE `lp_stream_template` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `template_code` VARCHAR(64) NOT NULL COMMENT '模板编码',
  `webrtc_app` VARCHAR(64) NOT NULL DEFAULT 'live' COMMENT 'SRS app',
  `stream_alias_prefix` VARCHAR(64) NOT NULL DEFAULT 'room' COMMENT '逻辑流前缀',
  `auth_required` TINYINT NOT NULL DEFAULT 1 COMMENT '是否鉴权',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_stream_template_code` (`template_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='流模板';

DROP TABLE IF EXISTS `lp_playlist_template`;
CREATE TABLE `lp_playlist_template` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `template_code` VARCHAR(64) NOT NULL COMMENT '模板编码',
  `name` VARCHAR(128) NOT NULL COMMENT '模板名称',
  `mode` VARCHAR(32) NOT NULL COMMENT '模板模式:public privilege backup',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_playlist_template_code` (`template_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='播单模板';

DROP TABLE IF EXISTS `lp_room_binding`;
CREATE TABLE `lp_room_binding` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `room_id` BIGINT UNSIGNED NOT NULL COMMENT '房间ID',
  `room_group_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '房间分组ID',
  `stream_template_id` BIGINT UNSIGNED NOT NULL COMMENT '流模板ID',
  `playlist_template_id` BIGINT UNSIGNED NOT NULL COMMENT '公共播单模板ID',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_room_id` (`room_id`),
  KEY `idx_room_group_id` (`room_group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='房间绑定配置';

DROP TABLE IF EXISTS `lp_room_tag`;
CREATE TABLE `lp_room_tag` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `room_id` BIGINT UNSIGNED NOT NULL COMMENT '房间ID',
  `tag_name` VARCHAR(32) NOT NULL COMMENT '标签名',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_room_tag` (`room_id`, `tag_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='房间标签';

-- =========================
-- 4. 素材与播单
-- =========================

DROP TABLE IF EXISTS `lp_media_asset`;
CREATE TABLE `lp_media_asset` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `asset_code` VARCHAR(64) NOT NULL COMMENT '素材编码',
  `asset_type` VARCHAR(32) NOT NULL COMMENT '素材类型:video image audio subtitle',
  `scene_type` VARCHAR(32) NOT NULL COMMENT '场景类型:public privilege interaction cover',
  `title` VARCHAR(128) NOT NULL COMMENT '标题',
  `file_url` VARCHAR(255) NOT NULL COMMENT '文件地址',
  `duration_ms` INT NOT NULL DEFAULT 0 COMMENT '时长ms',
  `checksum` VARCHAR(64) DEFAULT '' COMMENT '校验值',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_asset_code` (`asset_code`),
  KEY `idx_scene_type_status` (`scene_type`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='素材池';

DROP TABLE IF EXISTS `lp_playlist_template_item`;
CREATE TABLE `lp_playlist_template_item` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `template_id` BIGINT UNSIGNED NOT NULL COMMENT '播单模板ID',
  `asset_id` BIGINT UNSIGNED NOT NULL COMMENT '素材ID',
  `seq` INT NOT NULL DEFAULT 0 COMMENT '顺序',
  `loop_count` INT NOT NULL DEFAULT 1 COMMENT '循环次数',
  `weight` INT NOT NULL DEFAULT 1 COMMENT '权重',
  `start_offset_ms` INT NOT NULL DEFAULT 0 COMMENT '起始偏移ms',
  PRIMARY KEY (`id`),
  KEY `idx_template_seq` (`template_id`, `seq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='播单模板项';

-- =========================
-- 5. 礼物与互动
-- =========================

DROP TABLE IF EXISTS `lp_gift`;
CREATE TABLE `lp_gift` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `gift_code` VARCHAR(32) NOT NULL COMMENT '礼物编码',
  `name` VARCHAR(64) NOT NULL COMMENT '礼物名称',
  `price_diamond` DECIMAL(18,2) NOT NULL COMMENT '钻石价格',
  `trigger_mode` VARCHAR(32) NOT NULL DEFAULT 'none' COMMENT '触发模式:none privilege interaction',
  `trigger_duration_sec` INT NOT NULL DEFAULT 0 COMMENT '触发时长秒',
  `effect_code` VARCHAR(64) DEFAULT '' COMMENT '特效编码',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_gift_code` (`gift_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='礼物配置';

DROP TABLE IF EXISTS `lp_gift_order`;
CREATE TABLE `lp_gift_order` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `order_no` VARCHAR(64) NOT NULL COMMENT '订单号',
  `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
  `room_id` BIGINT UNSIGNED NOT NULL COMMENT '房间ID',
  `gift_id` BIGINT UNSIGNED NOT NULL COMMENT '礼物ID',
  `quantity` INT NOT NULL DEFAULT 1 COMMENT '数量',
  `total_price` DECIMAL(18,2) NOT NULL COMMENT '总价',
  `trigger_task_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '触发的切流任务ID',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_gift_order_no` (`order_no`),
  KEY `idx_room_created` (`room_id`, `created_at`),
  KEY `idx_user_created` (`user_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='礼物订单';

DROP TABLE IF EXISTS `lp_chat_message`;
CREATE TABLE `lp_chat_message` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `room_id` BIGINT UNSIGNED NOT NULL COMMENT '房间ID',
  `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
  `message_type` VARCHAR(32) NOT NULL DEFAULT 'text' COMMENT '消息类型',
  `content` VARCHAR(500) NOT NULL COMMENT '消息内容',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态:0屏蔽 1正常',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_room_created` (`room_id`, `created_at`),
  KEY `idx_user_created` (`user_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='聊天消息';

DROP TABLE IF EXISTS `lp_like_action_log`;
CREATE TABLE `lp_like_action_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `room_id` BIGINT UNSIGNED NOT NULL COMMENT '房间ID',
  `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
  `action_count` INT NOT NULL DEFAULT 1 COMMENT '点赞次数',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_room_created` (`room_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='点赞日志';

DROP TABLE IF EXISTS `lp_room_online_minute`;
CREATE TABLE `lp_room_online_minute` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `room_id` BIGINT UNSIGNED NOT NULL COMMENT '房间ID',
  `minute_at` DATETIME NOT NULL COMMENT '分钟时间',
  `online_count` INT NOT NULL DEFAULT 0 COMMENT '在线人数',
  `like_count` INT NOT NULL DEFAULT 0 COMMENT '点赞数',
  `gift_amount` DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT '礼物金额',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_room_minute` (`room_id`, `minute_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='房间分钟聚合数据';

-- =========================
-- 6. AI 任务与房间编排
-- =========================

DROP TABLE IF EXISTS `lp_ai_task`;
CREATE TABLE `lp_ai_task` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `task_no` VARCHAR(64) NOT NULL COMMENT '任务号',
  `room_id` BIGINT UNSIGNED NOT NULL COMMENT '房间ID',
  `task_type` VARCHAR(32) NOT NULL COMMENT '任务类型:interaction_async interaction_realtime',
  `priority` INT NOT NULL DEFAULT 0 COMMENT '优先级',
  `source_type` VARCHAR(32) NOT NULL COMMENT '来源类型:chat gift system',
  `source_ref_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '来源业务ID',
  `persona_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '人设ID',
  `content` VARCHAR(1000) DEFAULT '' COMMENT '任务文本',
  `callback_mode` VARCHAR(32) NOT NULL DEFAULT 'file' COMMENT '回调模式:file stream',
  `status` VARCHAR(32) NOT NULL DEFAULT 'pending' COMMENT '任务状态',
  `worker_id` VARCHAR(64) DEFAULT '' COMMENT '处理客户端ID',
  `result_type` VARCHAR(32) DEFAULT '' COMMENT '结果类型:video_file live_stream',
  `video_url` VARCHAR(255) DEFAULT '' COMMENT '视频URL',
  `cover_url` VARCHAR(255) DEFAULT '' COMMENT '封面URL',
  `stream_alias` VARCHAR(128) DEFAULT '' COMMENT '互动流别名',
  `duration_sec` INT NOT NULL DEFAULT 0 COMMENT '时长',
  `deadline_at` DATETIME DEFAULT NULL COMMENT '截止时间',
  `accepted_at` DATETIME DEFAULT NULL COMMENT '接单时间',
  `finished_at` DATETIME DEFAULT NULL COMMENT '完成时间',
  `failed_at` DATETIME DEFAULT NULL COMMENT '失败时间',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_task_no` (`task_no`),
  KEY `idx_room_status_priority` (`room_id`, `status`, `priority`),
  KEY `idx_worker_id` (`worker_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='AI任务';

DROP TABLE IF EXISTS `lp_ai_task_log`;
CREATE TABLE `lp_ai_task_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `task_id` BIGINT UNSIGNED NOT NULL COMMENT '任务ID',
  `event_type` VARCHAR(32) NOT NULL COMMENT '事件类型',
  `payload_json` JSON DEFAULT NULL COMMENT '事件数据',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_task_created` (`task_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='AI任务日志';

DROP TABLE IF EXISTS `lp_room_state_snapshot`;
CREATE TABLE `lp_room_state_snapshot` (
  `room_id` BIGINT UNSIGNED NOT NULL COMMENT '房间ID',
  `current_state` VARCHAR(32) NOT NULL DEFAULT 'public_ready' COMMENT '当前状态',
  `current_mode` VARCHAR(32) NOT NULL DEFAULT 'public' COMMENT '当前模式:public interaction privilege',
  `current_task_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '当前任务ID',
  `privilege_expire_at` DATETIME DEFAULT NULL COMMENT '特权过期时间',
  `version` INT NOT NULL DEFAULT 1 COMMENT '乐观锁版本',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`room_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='房间状态快照';

DROP TABLE IF EXISTS `lp_room_play_task`;
CREATE TABLE `lp_room_play_task` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `room_id` BIGINT UNSIGNED NOT NULL COMMENT '房间ID',
  `task_type` VARCHAR(32) NOT NULL COMMENT '任务类型:public interaction privilege',
  `ref_task_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '关联任务ID',
  `mode` VARCHAR(32) NOT NULL COMMENT '播放模式',
  `priority` INT NOT NULL DEFAULT 0 COMMENT '优先级',
  `status` VARCHAR(32) NOT NULL DEFAULT 'pending' COMMENT '状态',
  `scheduled_at` DATETIME DEFAULT NULL COMMENT '调度时间',
  `started_at` DATETIME DEFAULT NULL COMMENT '开始时间',
  `ended_at` DATETIME DEFAULT NULL COMMENT '结束时间',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_room_status_priority` (`room_id`, `status`, `priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='房间播放任务';

DROP TABLE IF EXISTS `lp_room_switch_task`;
CREATE TABLE `lp_room_switch_task` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `task_no` VARCHAR(64) NOT NULL COMMENT '切换任务号',
  `room_id` BIGINT UNSIGNED NOT NULL COMMENT '房间ID',
  `trigger_type` VARCHAR(32) NOT NULL COMMENT '触发类型:gift timeout system',
  `trigger_ref_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '触发引用ID',
  `from_mode` VARCHAR(32) NOT NULL COMMENT '原模式',
  `to_mode` VARCHAR(32) NOT NULL COMMENT '目标模式',
  `duration_sec` INT NOT NULL DEFAULT 0 COMMENT '持续时长',
  `status` VARCHAR(32) NOT NULL DEFAULT 'pending' COMMENT '状态',
  `scheduled_at` DATETIME DEFAULT NULL COMMENT '调度时间',
  `started_at` DATETIME DEFAULT NULL COMMENT '开始时间',
  `ended_at` DATETIME DEFAULT NULL COMMENT '结束时间',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_switch_task_no` (`task_no`),
  KEY `idx_room_status_scheduled` (`room_id`, `status`, `scheduled_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='房间切换任务';

DROP TABLE IF EXISTS `lp_room_event_log`;
CREATE TABLE `lp_room_event_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `room_id` BIGINT UNSIGNED NOT NULL COMMENT '房间ID',
  `event_type` VARCHAR(32) NOT NULL COMMENT '事件类型',
  `payload_json` JSON DEFAULT NULL COMMENT '事件数据',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_room_created` (`room_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='房间事件日志';

DROP TABLE IF EXISTS `lp_control_command_log`;
CREATE TABLE `lp_control_command_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `room_id` BIGINT UNSIGNED NOT NULL COMMENT '房间ID',
  `task_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '关联任务ID',
  `command_type` VARCHAR(32) NOT NULL COMMENT '命令类型',
  `target_worker` VARCHAR(64) DEFAULT '' COMMENT '目标执行端',
  `request_payload` JSON DEFAULT NULL COMMENT '请求数据',
  `ack_payload` JSON DEFAULT NULL COMMENT '响应数据',
  `status` VARCHAR(32) NOT NULL DEFAULT 'pending' COMMENT '状态',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_room_created` (`room_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='控制命令日志';

-- =========================
-- 7. 初始化数据
-- =========================

INSERT INTO `lp_stream_template` (`template_code`, `webrtc_app`, `stream_alias_prefix`, `auth_required`, `status`)
VALUES
  ('default_live', 'live', 'room', 1, 1);

INSERT INTO `lp_gift` (`gift_code`, `name`, `price_diamond`, `trigger_mode`, `trigger_duration_sec`, `effect_code`, `status`)
VALUES
  ('rose', '玫瑰', 10.00, 'none', 0, 'rose_effect', 1),
  ('vip_30s', '专属礼物', 199.00, 'privilege', 30, 'vip_effect', 1);

SET FOREIGN_KEY_CHECKS = 1;
