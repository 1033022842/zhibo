-- ========================================
-- 步骤1：关键词视频推流重构 - 数据库迁移
-- 执行方式：source php/sql/upgrade_keyword_stream.sql
-- ========================================

-- 1. 扩展 lp_media_asset
ALTER TABLE `lp_media_asset`
  ADD COLUMN `keywords` VARCHAR(500) DEFAULT '' COMMENT '关键词标签，逗号分隔：比心,飞吻,挥手' AFTER `scene_type`,
  ADD COLUMN `persona` VARCHAR(64) DEFAULT '' COMMENT '人设：白毛女' AFTER `keywords`,
  ADD COLUMN `weight` INT NOT NULL DEFAULT 1 COMMENT '随机权重，数值越大播放概率越高' AFTER `persona`,
  ADD INDEX `idx_persona_kw` (`persona`, `keywords`(100));

-- 2. 简化 lp_room_binding
ALTER TABLE `lp_room_binding`
  ADD COLUMN `persona` VARCHAR(64) DEFAULT '' COMMENT '绑定人设' AFTER `room_group_id`;

ALTER TABLE `lp_room_binding`
  MODIFY COLUMN `playlist_template_id` BIGINT UNSIGNED NULL COMMENT '公共播单模板ID（关键词模式下可为NULL）';

-- 3. 创建礼物关键词映射表
DROP TABLE IF EXISTS `lp_gift_keyword`;
CREATE TABLE `lp_gift_keyword` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `gift_id` BIGINT UNSIGNED NOT NULL COMMENT '礼物ID',
  `keyword` VARCHAR(64) NOT NULL COMMENT '触发关键词',
  `priority` INT NOT NULL DEFAULT 0 COMMENT '优先级（同礼物多关键词时按优先级取）',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_gift_kw` (`gift_id`, `keyword`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='礼物关键词映射';

-- 4. 扩展 lp_gift trigger_mode
ALTER TABLE `lp_gift`
  MODIFY COLUMN `trigger_mode` VARCHAR(32) NOT NULL DEFAULT 'keyword' COMMENT '触发模式:none privilege interaction keyword';
