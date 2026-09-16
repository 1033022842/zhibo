-- ============================================
-- AI 女友端「上传图片换脸（固定视频）」功能
-- 执行方式：source php/sql/upgrade_face_swap.sql（数据库 live_platform）
--
-- 说明：
--   1) 新增换脸模板表 lp_face_swap_template（后台可配置多条固定视频供用户选择）
--   2) 复用 lp_ai_task 任务队列（task_type=face_swap），补充换脸任务参数字段
--   3) 新增后台菜单「换脸模板」（挂在「直播运营」目录下）
-- ============================================

-- 1. 换脸模板（固定视频）表
CREATE TABLE IF NOT EXISTS `lp_face_swap_template` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `title` VARCHAR(120) NOT NULL DEFAULT '' COMMENT '模板名称',
  `video_url` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '模板视频URL(固定视频)',
  `cover_url` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '封面图URL',
  `duration_sec` INT NOT NULL DEFAULT 0 COMMENT '模板视频时长(秒)',
  `description` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '模板描述',
  `weigh` INT NOT NULL DEFAULT 0 COMMENT '权重(越大越靠前)',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态(1启用0禁用)',
  `created_at` DATETIME DEFAULT NULL COMMENT '创建时间',
  `updated_at` DATETIME DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_status_weigh` (`status`, `weigh`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI换脸模板(固定视频)';

-- 2. 复用 lp_ai_task 承载换脸任务（task_type=face_swap，room_id=0）
ALTER TABLE `lp_ai_task`
  ADD COLUMN `user_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '发起用户ID(离线任务,如换脸)' AFTER `persona_id`,
  ADD COLUMN `face_image_url` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '换脸人脸图URL' AFTER `content`,
  ADD COLUMN `template_video_url` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '换脸模板视频URL' AFTER `face_image_url`;

ALTER TABLE `lp_ai_task` ADD INDEX `idx_type_status_id` (`task_type`, `status`, `id`);

-- 3. 后台菜单「换脸模板」（挂在「直播运营」目录下）
SET @live_pid = IFNULL((SELECT `id` FROM `ba_admin_rule` WHERE `title` = '直播运营' AND `type` = 'menu_dir' ORDER BY `id` DESC LIMIT 1), 0);

INSERT IGNORE INTO `ba_admin_rule` (`pid`, `type`, `name`, `title`, `icon`, `path`, `component`, `menu_type`, `keepalive`, `weigh`, `status`) VALUES
(@live_pid, 'menu', 'live/faceSwapTemplate', '换脸模板', 'fa fa-magic', 'live/faceSwapTemplate', '/src/views/backend/live/faceSwapTemplate/index.vue', 'tab', 1, 30, 1);

SET @fs_pid = (SELECT `id` FROM `ba_admin_rule` WHERE `name` = 'live/faceSwapTemplate' AND `type` = 'menu' LIMIT 1);

INSERT IGNORE INTO `ba_admin_rule` (`pid`, `type`, `name`, `title`, `icon`, `path`, `component`, `menu_type`, `keepalive`, `weigh`, `status`) VALUES
(@fs_pid, 'button', 'live/faceSwapTemplate/index', '查看', '', '', '', NULL, 0, 10, 1),
(@fs_pid, 'button', 'live/faceSwapTemplate/add', '新增', '', '', '', NULL, 0, 9, 1),
(@fs_pid, 'button', 'live/faceSwapTemplate/edit', '编辑', '', '', '', NULL, 0, 8, 1),
(@fs_pid, 'button', 'live/faceSwapTemplate/del', '删除', '', '', '', NULL, 0, 7, 1);

SELECT `id`, `pid`, `title`, `name`, `component`, `status` FROM `ba_admin_rule` WHERE `name` LIKE 'live/faceSwapTemplate%';
