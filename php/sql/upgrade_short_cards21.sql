-- ============================================
-- lp_short_preset 升级：卡片声明 pipeline / resolution，并入库 21 张抽卡卡片
-- 执行：mysql zhibo < php/sql/upgrade_short_cards21.sql
--
-- 为什么加这两个字段
--   pipeline    legacy | t8
--               legacy = MiniMaxH3ImageToVideo（gs-01..50 一直在用，默认）
--               t8     = MiniMaxH3AudioConditioningT8，支持 Ref2VA/Hybrid
--               A 组卡片虽是 I2VA，但样片由 T8 节点生成，必须声明 t8 才能同参复现
--   resolution  PerfectVideoResolution 档位。抽卡统一用 Preview — 640×640
--               （9:16 约 480×864），不回填就变成 Native 768×1344，构图会变
--
-- 21 张卡片 = 3 组提示词文档的 21 条提示词。每组内 181 条样片里挑一张作封面，
-- 其余样片作为画廊演示条目（clips.json），点击后调同一张卡片的参数生成。
-- ============================================

SET @db := DATABASE();

-- pipeline
SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `lp_short_preset` ADD COLUMN `pipeline` VARCHAR(16) NOT NULL DEFAULT ''legacy'' COMMENT ''legacy|t8（t8 支持 Ref2VA/Hybrid）'' AFTER `task_type`',
  'SELECT 1')
  FROM information_schema.columns
  WHERE table_schema = @db AND table_name = 'lp_short_preset' AND column_name = 'pipeline');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- resolution
SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `lp_short_preset` ADD COLUMN `resolution` VARCHAR(64) NOT NULL DEFAULT ''Preview — 640×640'' COMMENT ''PerfectVideoResolution 档位'' AFTER `steps`',
  'SELECT 1')
  FROM information_schema.columns
  WHERE table_schema = @db AND table_name = 'lp_short_preset' AND column_name = 'resolution');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
