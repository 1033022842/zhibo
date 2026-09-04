-- ========================================
-- 直播端策略改造：LivePortrait 驱动（单张立绘 + 模特动作驱动）
-- 执行方式：source php/sql/upgrade_liveportrait.sql
--
-- 变更说明：
--   旧策略：440 个预渲染成品视频，服务器 ffmpeg 关键词随机轮播
--   新策略：AI 端 LivePortrait 实时推理推流，仅需单张立绘 + 动作模板库
-- ========================================

-- 1. lp_media_asset 增加驱动角色分类
--    asset_role: ''        成品视频（旧策略，保留兼容）
--                portrait  立绘（单张静态图，LivePortrait 源图）
--                motion    动作模板（模特动作驱动视频）
ALTER TABLE `lp_media_asset`
  ADD COLUMN `asset_role` VARCHAR(32) NOT NULL DEFAULT '' COMMENT '驱动角色:空=成品视频/portrait立绘/motion动作模板' AFTER `asset_type`,
  ADD INDEX `idx_role_persona` (`asset_role`, `persona`);

-- 2. lp_room_binding 增加立绘绑定（指向 asset_role=portrait 的素材）
--    为空时回退使用 lp_persona.cover_url 作为单张立绘
ALTER TABLE `lp_room_binding`
  ADD COLUMN `portrait_asset_id` BIGINT UNSIGNED NULL COMMENT '绑定立绘素材ID(lp_media_asset,asset_role=portrait)' AFTER `persona`;
