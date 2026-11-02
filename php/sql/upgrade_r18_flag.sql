-- ============================================
-- R18 标记升级：短剧 + 私密内容（数字人社图）
-- 1) lp_short_item / lp_private_item 各增加
--      is_r18  R18 标记：1=限制级，前台仅「充值过代币 或 VIP 有效」的用户可见
-- 2) 后台「短剧管理 / 私密内容管理」编辑弹窗勾选，运营自行维护
-- 前台门控见 api/controller/Shorts.php、PrivateContent.php、WatchHistory.php、Shop.php
-- 本脚本幂等，可重复执行
-- ============================================

-- --------------------------------------------
-- 1. lp_short_item 加 is_r18
-- --------------------------------------------
SET @has_short_r18 = (SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
  WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'lp_short_item' AND `COLUMN_NAME` = 'is_r18');
SET @ddl = IF(@has_short_r18 = 0,
  'ALTER TABLE `lp_short_item` ADD COLUMN `is_r18` TINYINT NOT NULL DEFAULT 0 COMMENT ''R18标记:仅充值/VIP用户可见'' AFTER `spicy`',
  'SELECT ''lp_short_item.is_r18 已存在，跳过'' AS msg');
PREPARE _stmt FROM @ddl;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;

-- --------------------------------------------
-- 2. lp_private_item 加 is_r18
-- --------------------------------------------
SET @has_priv_r18 = (SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
  WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'lp_private_item' AND `COLUMN_NAME` = 'is_r18');
SET @ddl = IF(@has_priv_r18 = 0,
  'ALTER TABLE `lp_private_item` ADD COLUMN `is_r18` TINYINT NOT NULL DEFAULT 0 COMMENT ''R18标记:仅充值/VIP用户可见'' AFTER `badge`',
  'SELECT ''lp_private_item.is_r18 已存在，跳过'' AS msg');
PREPARE _stmt FROM @ddl;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;

-- --------------------------------------------
-- 3. 校验
-- --------------------------------------------
SELECT '--- r18 字段 ---' AS `--`;
SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, COLUMN_COMMENT FROM `information_schema`.`COLUMNS`
  WHERE `TABLE_SCHEMA` = DATABASE() AND `COLUMN_NAME` = 'is_r18'
    AND `TABLE_NAME` IN ('lp_short_item', 'lp_private_item');
