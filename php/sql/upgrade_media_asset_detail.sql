-- ============================================================================
-- 迁移：素材池 lp_media_asset 增加更详细的素材信息字段
-- 目的：AI 女友端上传直播素材时补充描述、标签、风格、情绪、清晰度、18+ 等信息
-- 执行：mysql -u root -p zhibo < php/sql/upgrade_media_asset_detail.sql
-- ============================================================================

ALTER TABLE `lp_media_asset`
  ADD COLUMN `description` TEXT         NULL                COMMENT '素材描述' AFTER `title`,
  ADD COLUMN `cover_url`   VARCHAR(500) NOT NULL DEFAULT '' COMMENT '封面图地址' AFTER `file_url`,
  ADD COLUMN `tags`        VARCHAR(255) NOT NULL DEFAULT '' COMMENT '标签，逗号分隔，如：比心,甜妹,竖屏' AFTER `keywords`,
  ADD COLUMN `style`       VARCHAR(32)  NOT NULL DEFAULT '' COMMENT '风格：realistic写实/anime二次元/3d/cyberpunk赛博朋克/chinese古风/korean韩系/western欧美' AFTER `tags`,
  ADD COLUMN `mood`        VARCHAR(32)  NOT NULL DEFAULT '' COMMENT '情绪/氛围：happy开心/cute撒娇/shy害羞/cold高冷/sexy性感/healing治愈/funny搞笑/serious认真' AFTER `style`,
  ADD COLUMN `resolution`  VARCHAR(16)  NOT NULL DEFAULT '' COMMENT '清晰度：720P/1080P/2K/4K' AFTER `mood`,
  ADD COLUMN `is_adult`    TINYINT(1)   NOT NULL DEFAULT 0  COMMENT '是否18+内容：0否 1是' AFTER `resolution`;

-- 索引：按风格 / 18+ 筛选
ALTER TABLE `lp_media_asset`
  ADD INDEX `idx_style` (`style`),
  ADD INDEX `idx_is_adult` (`is_adult`);
