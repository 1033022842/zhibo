-- 定时维护任务表
CREATE TABLE IF NOT EXISTS `lp_maintenance_task` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `name` varchar(128) NOT NULL COMMENT '维护任务名称',
  `due_date` date NOT NULL COMMENT '到期日期',
  `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  `repeat_remind` tinyint(4) NOT NULL DEFAULT 0 COMMENT '重复提醒:0关闭 1开启',
  `status` tinyint(4) NOT NULL DEFAULT 0 COMMENT '状态:0待通知 1已通知 2已关闭',
  `last_notify_at` datetime DEFAULT NULL COMMENT '上次通知时间',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_due_date` (`due_date`) USING BTREE,
  INDEX `idx_status` (`status`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='定时维护任务';

-- TG通知配置表
CREATE TABLE IF NOT EXISTS `lp_maintenance_config` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `bot_token` varchar(255) NOT NULL DEFAULT '' COMMENT 'Telegram Bot Token',
  `chat_id` varchar(255) NOT NULL DEFAULT '' COMMENT 'Telegram Chat ID (多个用逗号分隔)',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='TG通知配置';

-- 插入默认配置行
INSERT IGNORE INTO `lp_maintenance_config` (`id`, `bot_token`, `chat_id`) VALUES (1, '', '');

-- ========== 菜单 ==========
-- 定时维护 菜单目录（挂在直播运营下）
SET @live_pid = (SELECT `id` FROM `ba_admin_rule` WHERE `name` IN ('live', 'liveOps', 'liveData') AND `type` = 'menu_dir' AND `pid` = 0 LIMIT 1);

INSERT IGNORE INTO `ba_admin_rule` (`pid`, `type`, `name`, `title`, `icon`, `path`, `component`, `menu_type`, `keepalive`, `weigh`, `status`) VALUES
(@live_pid, 'menu_dir', 'live/maintenance', '定时维护', 'fa fa-clock-o', 'live/maintenance', '', NULL, 0, 4, 1);

SET @maintenance_pid = (SELECT `id` FROM `ba_admin_rule` WHERE `name` = 'live/maintenance' AND `type` = 'menu_dir' LIMIT 1);

INSERT IGNORE INTO `ba_admin_rule` (`pid`, `type`, `name`, `title`, `icon`, `path`, `component`, `menu_type`, `keepalive`, `weigh`, `status`) VALUES
(@maintenance_pid, 'menu', 'live/maintenanceTask', '维护任务', 'fa fa-list', 'live/maintenanceTask', '/src/views/backend/live/maintenanceTask/index.vue', 'tab', 1, 1, 1);

-- 维护任务按钮权限
SET @task_pid = (SELECT `id` FROM `ba_admin_rule` WHERE `name` = 'live/maintenanceTask' AND `type` = 'menu' LIMIT 1);
INSERT IGNORE INTO `ba_admin_rule` (`pid`, `type`, `name`, `title`, `icon`, `path`, `component`, `menu_type`, `keepalive`, `weigh`, `status`) VALUES
(@task_pid, 'button', 'live/maintenanceTask/index', '查看', '', '', '', NULL, 0, 10, 1),
(@task_pid, 'button', 'live/maintenanceTask/add', '新增', '', '', '', NULL, 0, 9, 1),
(@task_pid, 'button', 'live/maintenanceTask/edit', '编辑', '', '', '', NULL, 0, 8, 1),
(@task_pid, 'button', 'live/maintenanceTask/del', '删除', '', '', '', NULL, 0, 7, 1);

INSERT IGNORE INTO `ba_admin_rule` (`pid`, `type`, `name`, `title`, `icon`, `path`, `component`, `menu_type`, `keepalive`, `weigh`, `status`) VALUES
(@maintenance_pid, 'menu', 'live/maintenanceConfig', 'TG通知配置', 'fa fa-telegram', 'live/maintenanceConfig', '/src/views/backend/live/maintenanceConfig/index.vue', 'tab', 1, 2, 1);
