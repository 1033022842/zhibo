-- ============================================
-- AI 工作室收藏（账号级）—— lp_short_preset 的用户收藏关系
-- 执行：source php/sql/upgrade_ai_short_fav.sql   （库 zhibo / live_mysql）
--
-- 设计：
--   1) 每用户每预设一行，(user_id, preset_code) 唯一，重复收藏幂等。
--   2) 收藏存 code（lp_short_preset.code，如 gs-01 / d11-05），预设下架
--      不影响收藏记录；前端按 code 在样片库里过滤展示。
--   3) 接口：GET /api/aiShort/favs、POST /api/aiShort/favToggle（AiShort::favs/favToggle）。
-- ============================================

CREATE TABLE IF NOT EXISTS `lp_short_preset_fav` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `user_id`     INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户ID',
  `preset_code` VARCHAR(32)  NOT NULL DEFAULT '' COMMENT '预设 code',
  `created_at`  DATETIME DEFAULT NULL COMMENT '收藏时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_code` (`user_id`, `preset_code`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI工作室收藏(用户-预设)';
