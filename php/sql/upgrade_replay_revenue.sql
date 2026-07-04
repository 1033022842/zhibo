-- 历史切片表
CREATE TABLE IF NOT EXISTS `lp_replay_clip` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `persona_id` bigint(20) UNSIGNED NOT NULL COMMENT '关联角色ID',
  `room_id` bigint(20) UNSIGNED NOT NULL COMMENT '关联房间ID',
  `title` varchar(128) NOT NULL COMMENT '标题',
  `video_url` varchar(255) NOT NULL DEFAULT '' COMMENT '视频路径',
  `cover_url` varchar(255) NOT NULL DEFAULT '' COMMENT '封面图',
  `duration` int(11) NOT NULL DEFAULT 0 COMMENT '时长(秒)',
  `live_date` date NOT NULL COMMENT '直播日期',
  `status` tinyint(4) NOT NULL DEFAULT 1 COMMENT '状态:0下架 1上架',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_persona_id` (`persona_id`) USING BTREE,
  INDEX `idx_room_id` (`room_id`) USING BTREE,
  INDEX `idx_live_date` (`live_date`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='历史切片';

-- 直播数据管理菜单目录
INSERT IGNORE INTO `ba_admin_rule` (`pid`, `type`, `name`, `title`, `icon`, `path`, `component`, `menu_type`, `weigh`, `status`) VALUES
(0, 'menu_dir', 'liveData', '直播数据', 'fa fa-bar-chart', '/admin/liveData', '', 0, 50, 1);

SET @live_data_pid = (SELECT `id` FROM `ba_admin_rule` WHERE `name` = 'liveData' AND `type` = 'menu_dir' LIMIT 1);

-- 收益明细
INSERT IGNORE INTO `ba_admin_rule` (`pid`, `type`, `name`, `title`, `icon`, `path`, `component`, `menu_type`, `weigh`, `status`) VALUES
(@live_data_pid, 'menu', 'live/revenue', '收益明细', 'fa fa-list-alt', '/admin/live/revenue', 'live/revenue/index', 0, 1, 1);

-- 收入排行榜
INSERT IGNORE INTO `ba_admin_rule` (`pid`, `type`, `name`, `title`, `icon`, `path`, `component`, `menu_type`, `weigh`, `status`) VALUES
(@live_data_pid, 'menu', 'live/leaderboard', '收入排行榜', 'fa fa-trophy', '/admin/live/leaderboard', 'live/leaderboard/index', 0, 2, 1);

-- 历史切片管理
INSERT IGNORE INTO `ba_admin_rule` (`pid`, `type`, `name`, `title`, `icon`, `path`, `component`, `menu_type`, `weigh`, `status`) VALUES
(@live_data_pid, 'menu', 'live/replayClip', '历史切片', 'fa fa-video-camera', '/admin/live/replayClip', 'live/replayClip/index', 0, 3, 1);
