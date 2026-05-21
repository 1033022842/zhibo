-- ============================================================
-- 直播平台 测试数据
-- 兼容环境: MySQL 8.0+
-- 数据库: live_platform
-- 注意: 导入前请确保已执行 DDL 建表(live_platform.sql)
--        ba_ 系统表数据请通过 ThinkPHP 迁移生成:
--        php think migrate:run
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =========================
-- 1. 用户与认证
-- =========================

INSERT INTO `lp_user` (`id`, `user_no`, `nickname`, `avatar`, `status`, `level`, `created_at`, `updated_at`) VALUES
(1, 'U20250500001', '测试用户阿杰', '/avatar/default_01.png', 1, 5, NOW(), NOW()),
(2, 'U20250500002', '测试用户小雨', '/avatar/default_02.png', 1, 3, NOW(), NOW()),
(3, 'U20250500003', '测试用户大鹏', '/avatar/default_03.png', 1, 4, NOW(), NOW()),
(4, 'U20250500004', '测试用户梦梦', '/avatar/default_04.png', 1, 2, NOW(), NOW()),
(5, 'U20250500005', '测试用户阿飞', '/avatar/default_05.png', 1, 1, NOW(), NOW());

INSERT INTO `lp_user_auth` (`user_id`, `auth_type`, `auth_key`, `password_hash`, `created_at`) VALUES
(1, 'mobile', '13800000001', '$2y$10$dummyhash001', NOW()),
(2, 'mobile', '13800000002', '$2y$10$dummyhash002', NOW()),
(3, 'mobile', '13800000003', '$2y$10$dummyhash003', NOW()),
(4, 'mobile', '13800000004', '$2y$10$dummyhash004', NOW()),
(5, 'mobile', '13800000005', '$2y$10$dummyhash005', NOW());

INSERT INTO `lp_user_profile` (`user_id`, `gender`, `bio`, `country_code`, `last_login_ip`, `last_login_at`) VALUES
(1, 1, '热爱直播，天天在线', '86', '127.0.0.1', NOW()),
(2, 2, '喜欢看才艺直播', '86', '127.0.0.1', NOW()),
(3, 1, '摇滚万岁！', '86', '127.0.0.1', NOW()),
(4, 2, '每天来直播间打卡', '86', '127.0.0.1', NOW()),
(5, 1, '新来的，请多关照', '86', '127.0.0.1', NOW());

-- =========================
-- 2. 钱包与支付
-- =========================

INSERT INTO `lp_wallet_account` (`user_id`, `diamond_balance`, `status`) VALUES
(1, 500.00, 1),
(2, 300.00, 1),
(3, 800.00, 1),
(4, 150.00, 1),
(5, 50.00, 1);

INSERT INTO `lp_wallet_ledger` (`user_id`, `biz_type`, `direction`, `asset_type`, `amount`, `balance_before`, `balance_after`, `biz_id`, `remark`, `created_at`) VALUES
(1, 'recharge', 1, 'diamond', 500.00, 0.00, 500.00, NULL, '首次充值', NOW()),
(2, 'recharge', 1, 'diamond', 300.00, 0.00, 300.00, NULL, '首次充值', NOW()),
(3, 'recharge', 1, 'diamond', 800.00, 0.00, 800.00, NULL, '首次充值', NOW()),
(4, 'recharge', 1, 'diamond', 200.00, 0.00, 200.00, NULL, '首次充值', NOW()),
(4, 'gift', 2, 'diamond', 50.00, 200.00, 150.00, NULL, '送礼消耗', NOW()),
(5, 'recharge', 1, 'diamond', 100.00, 0.00, 100.00, NULL, '首次充值', NOW()),
(5, 'gift', 2, 'diamond', 50.00, 100.00, 50.00, NULL, '送礼消耗', NOW());

INSERT INTO `lp_asset_exchange_rate` (`pay_channel`, `chain_type`, `pay_amount`, `diamond_amount`, `rate_snapshot`, `created_at`) VALUES
('usdt_trc20', 'TRC20', 1.00000000, 100.00, 100.00000000, NOW());

-- =========================
-- 3. 房间与人设
-- =========================

INSERT INTO `lp_persona` (`id`, `code`, `name`, `tags`, `cover_url`, `status`) VALUES
(1, 'sweet_host', '甜心主播', '甜美,唱歌,聊天', '/cover/persona_sweet.png', 1),
(2, 'rock_star', '摇滚达人', '摇滚,乐器,激情', '/cover/persona_rock.png', 1),
(3, 'wise_sister', '知性姐姐', '知识,情感,读书', '/cover/persona_wise.png', 1);

INSERT INTO `lp_room` (`id`, `room_no`, `title`, `subtitle`, `persona_id`, `room_type`, `status`, `cover_url`, `sort`) VALUES
(1, 'RM20250500001', 'AI虚拟人直播间｜甜心', '甜美声线 · 全天候陪伴', 1, 'live', 1, '/cover/room_01.png', 100),
(2, 'RM20250500002', 'AI虚拟人直播间｜摇滚', '燃炸现场 · 摇滚不死', 2, 'live', 1, '/cover/room_02.png', 90),
(3, 'RM20250500003', 'AI虚拟人直播间｜知性', '深夜电台 · 情感陪伴', 3, 'live', 1, '/cover/room_03.png', 80);

INSERT INTO `lp_room_tag` (`room_id`, `tag_name`) VALUES
(1, '热门'),
(1, '推荐'),
(1, '唱歌'),
(2, '摇滚'),
(2, '新人'),
(3, '情感'),
(3, '推荐');

INSERT INTO `lp_room_state_snapshot` (`room_id`, `current_state`, `current_mode`, `version`) VALUES
(1, 'public_ready', 'public', 1),
(2, 'public_ready', 'public', 1),
(3, 'public_ready', 'public', 1);

-- =========================
-- 4. 素材与播单
-- =========================

INSERT INTO `lp_media_asset` (`id`, `asset_code`, `asset_type`, `scene_type`, `title`, `file_url`, `duration_ms`, `status`) VALUES
(1, 'demo_video_01', 'video', 'public', '舞蹈场景演示素材', '/assets/demo_01.mp4', 120000, 1),
(2, 'demo_video_02', 'video', 'public', '唱歌场景演示素材', '/assets/demo_02.mp4', 90000, 1),
(3, 'demo_video_03', 'video', 'public', '聊天场景演示素材', '/assets/demo_03.mp4', 60000, 1),
(4, 'demo_video_04', 'video', 'privilege', '专属互动演示素材', '/assets/demo_04.mp4', 30000, 1),
(5, 'demo_cover_01', 'image', 'cover', '甜心直播间封面', '/cover/room_01.png', 0, 1),
(6, 'demo_cover_02', 'image', 'cover', '摇滚直播间封面', '/cover/room_02.png', 0, 1),
(7, 'demo_cover_03', 'image', 'cover', '知性直播间封面', '/cover/room_03.png', 0, 1);

INSERT INTO `lp_playlist_template` (`id`, `template_code`, `name`, `mode`, `status`) VALUES
(1, 'public_default', '默认公共播单', 'public', 1),
(2, 'privilege_default', '默认特权播单', 'privilege', 1);

INSERT INTO `lp_playlist_template_item` (`template_id`, `asset_id`, `seq`, `loop_count`, `weight`) VALUES
(1, 1, 1, 1, 10),
(1, 2, 2, 1, 10),
(1, 3, 3, 1, 10),
(2, 4, 1, 3, 5);

-- =========================
-- 5. 房间绑定 & 流模板
-- =========================

INSERT INTO `lp_stream_template` (`id`, `template_code`, `webrtc_app`, `stream_alias_prefix`, `auth_required`, `status`) VALUES
(1, 'default_live', 'live', 'room', 1, 1);

INSERT INTO `lp_room_binding` (`room_id`, `stream_template_id`, `playlist_template_id`) VALUES
(1, 1, 1),
(2, 1, 1),
(3, 1, 1);

-- =========================
-- 6. 礼物与互动
-- =========================

INSERT INTO `lp_gift` (`gift_code`, `name`, `price_diamond`, `trigger_mode`, `trigger_duration_sec`, `effect_code`, `status`) VALUES
('rose', '玫瑰', 10.00, 'none', 0, 'rose_effect', 1),
('heart', '爱心', 1.00, 'none', 0, 'heart_effect', 1),
('rocket', '火箭', 99.00, 'none', 0, 'rocket_effect', 1),
('crown', '皇冠', 299.00, 'none', 0, 'crown_effect', 1),
('vip_30s', '专属礼物', 199.00, 'privilege', 30, 'vip_effect', 1);

INSERT INTO `lp_gift_order` (`order_no`, `user_id`, `room_id`, `gift_id`, `quantity`, `total_price`, `status`, `created_at`) VALUES
('GO20250501001', 1, 1, 1, 3, 30.00, 1, DATE_SUB(NOW(), INTERVAL 2 DAY)),
('GO20250501002', 2, 1, 2, 5, 5.00, 1, DATE_SUB(NOW(), INTERVAL 1 DAY)),
('GO20250501003', 3, 2, 3, 1, 99.00, 1, DATE_SUB(NOW(), INTERVAL 1 DAY)),
('GO20250501004', 1, 2, 2, 10, 10.00, 1, DATE_SUB(NOW(), INTERVAL 12 HOUR)),
('GO20250501005', 4, 3, 2, 1, 1.00, 1, DATE_SUB(NOW(), INTERVAL 6 HOUR)),
('GO20250501006', 5, 1, 2, 1, 1.00, 1, DATE_SUB(NOW(), INTERVAL 3 HOUR)),
('GO20250501007', 3, 1, 5, 1, 199.00, 1, DATE_SUB(NOW(), INTERVAL 1 HOUR));

INSERT INTO `lp_chat_message` (`room_id`, `user_id`, `message_type`, `content`, `status`, `created_at`) VALUES
(1, 1, 'text', '主播好美！', 1, DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(1, 2, 'text', '唱首歌吧！', 1, DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(1, 3, 'text', '这个直播间好热闹', 1, DATE_SUB(NOW(), INTERVAL 30 MINUTE)),
(2, 1, 'text', '摇滚太燃了！', 1, DATE_SUB(NOW(), INTERVAL 45 MINUTE)),
(2, 4, 'text', '再来一首！', 1, DATE_SUB(NOW(), INTERVAL 20 MINUTE)),
(3, 5, 'text', '姐姐的声音好温柔', 1, DATE_SUB(NOW(), INTERVAL 15 MINUTE)),
(3, 2, 'text', '每天晚上都来这里', 1, DATE_SUB(NOW(), INTERVAL 10 MINUTE));

INSERT INTO `lp_like_action_log` (`room_id`, `user_id`, `action_count`, `created_at`) VALUES
(1, 1, 10, DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(1, 2, 5, DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(1, 3, 8, DATE_SUB(NOW(), INTERVAL 30 MINUTE)),
(2, 1, 6, DATE_SUB(NOW(), INTERVAL 45 MINUTE)),
(2, 4, 3, DATE_SUB(NOW(), INTERVAL 20 MINUTE)),
(3, 5, 4, DATE_SUB(NOW(), INTERVAL 15 MINUTE)),
(3, 2, 7, DATE_SUB(NOW(), INTERVAL 10 MINUTE));

INSERT INTO `lp_room_online_minute` (`room_id`, `minute_at`, `online_count`, `like_count`, `gift_amount`) VALUES
(1, DATE_SUB(NOW(), INTERVAL 120 MINUTE), 12, 8, 30.00),
(1, DATE_SUB(NOW(), INTERVAL 60 MINUTE), 18, 15, 5.00),
(1, DATE_SUB(NOW(), INTERVAL 30 MINUTE), 22, 20, 199.00),
(2, DATE_SUB(NOW(), INTERVAL 60 MINUTE), 10, 6, 99.00),
(2, DATE_SUB(NOW(), INTERVAL 30 MINUTE), 15, 9, 10.00),
(3, DATE_SUB(NOW(), INTERVAL 30 MINUTE), 8, 11, 1.00);

-- =========================
-- 7. AI 任务（演示数据）
-- =========================

INSERT INTO `lp_ai_task` (`id`, `task_no`, `room_id`, `task_type`, `priority`, `source_type`, `source_ref_id`, `persona_id`, `content`, `callback_mode`, `status`, `created_at`, `updated_at`) VALUES
(1, 'AI20250501001', 1, 'interaction_realtime', 10, 'gift', 7, 1, '用户赠送专属礼物，触发特权互动', 'stream', 'pending', DATE_SUB(NOW(), INTERVAL 1 HOUR), DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(2, 'AI20250501002', 2, 'interaction_realtime', 5, 'chat', NULL, 2, '观众要求再唱一首摇滚', 'file', 'pending', DATE_SUB(NOW(), INTERVAL 45 MINUTE), DATE_SUB(NOW(), INTERVAL 45 MINUTE));

SET FOREIGN_KEY_CHECKS = 1;
