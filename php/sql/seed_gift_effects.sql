-- ========================================
-- 礼物特效素材种子数据（来源：45.194.18.126 服务器备份 public/assets/video/）
-- 前置条件：已执行 upgrade_gift_icon.sql
-- 文件位置：php/public/storage/gift_effect/*.webm（部署时需同步到服务器同路径）
-- 说明：素材为黑底竖版 VP9 动画，前端通过 mix-blend-mode:screen 叠加播放
-- ========================================

INSERT INTO `lp_media_asset` (`asset_code`, `asset_type`, `scene_type`, `keywords`, `persona`, `weight`, `title`, `file_url`, `duration_ms`, `status`, `created_at`) VALUES
  ('effect_rocket',             'video', 'gift_effect', '', '', 1, '礼物特效-火箭',     '/storage/gift_effect/rocket.webm',             6041, 1, NOW()),
  ('effect_supercar',           'video', 'gift_effect', '', '', 1, '礼物特效-超级跑车', '/storage/gift_effect/supercar.webm',           3417, 1, NOW()),
  ('rose_effect',               'video', 'gift_effect', '', '', 1, '礼物特效-玫瑰',     '/storage/gift_effect/rose.webm',               3566, 1, NOW()),
  ('effect_heart',              'video', 'gift_effect', '', '', 1, '礼物特效-爱心',     '/storage/gift_effect/heart.webm',             30000, 1, NOW()),
  ('effect_heart_universe',     'video', 'gift_effect', '', '', 1, '礼物特效-宇宙之心', '/storage/gift_effect/heart-of-the-universe.webm', 8333, 1, NOW()),
  ('effect_romantic_carriage',  'video', 'gift_effect', '', '', 1, '礼物特效-浪漫马车', '/storage/gift_effect/romantic-carriage.webm',  8700, 1, NOW()),
  ('effect_carnival',           'video', 'gift_effect', '', '', 1, '礼物特效-嘉年华',   '/storage/gift_effect/carnival.webm',           8041, 1, NOW()),
  ('effect_moon_sea',           'video', 'gift_effect', '', '', 1, '礼物特效-海上生明月', '/storage/gift_effect/moon-rises-over-the-sea.webm', 5292, 1, NOW())
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `file_url` = VALUES(`file_url`), `duration_ms` = VALUES(`duration_ms`);

-- 示范：给现有"玫瑰"礼物挂上特效（礼物关键词触发已配为"比心"）
-- UPDATE `lp_gift` SET `effect_code` = 'effect_rose' WHERE `gift_code` = 'rose';
