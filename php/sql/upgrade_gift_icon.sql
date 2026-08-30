-- ========================================
-- 礼物图标 + 特效素材支持 - 数据库迁移
-- 执行方式：mysql -uroot -proot live_platform < php/sql/upgrade_gift_icon.sql
-- 关联改动：
--   1. lp_gift.icon_url    礼物图标（后台表单上传，直播间礼物面板展示）
--   2. 礼物特效通过 lp_gift.effect_code -> lp_media_asset.asset_code 关联
--      （特效视频上传到素材库时 scene_type 填 gift_effect，无需额外建表）
-- ========================================

-- 1. lp_gift 增加图标字段
ALTER TABLE `lp_gift`
  ADD COLUMN `icon_url` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '礼物图标URL' AFTER `effect_code`;

-- 注：lp_media_asset 已有 idx_scene_type_status(scene_type,status) 索引，无需新增
