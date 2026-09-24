-- ============================================
-- 短剧页筛选升级：分类标签 + 角色类型
-- 1) lp_short_item 增加
--      category       分类标签(逗号分隔)，对应前台分类 pill（All / New / Spicy + 数据里出现的标签）
--      character_type 角色类型 creator/character/other，对应前台「Candy Creators / Candy characters / Other characters」
-- 2) 按标题关键词给存量数据补一次分类（只填还为空的行），后台改标签后不会被覆盖
-- 本脚本幂等，可重复执行
-- ============================================

-- --------------------------------------------
-- 1. 加字段
-- --------------------------------------------
SET @has_cat = (SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
  WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'lp_short_item' AND `COLUMN_NAME` = 'category');
SET @ddl = IF(@has_cat = 0,
  'ALTER TABLE `lp_short_item` ADD COLUMN `category` VARCHAR(128) NOT NULL DEFAULT '''' COMMENT ''分类标签(逗号分隔),对应前台分类筛选'' AFTER `description`',
  'SELECT ''lp_short_item.category 已存在，跳过'' AS msg');
PREPARE _stmt FROM @ddl;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;

SET @has_type = (SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
  WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'lp_short_item' AND `COLUMN_NAME` = 'character_type');
SET @ddl = IF(@has_type = 0,
  'ALTER TABLE `lp_short_item` ADD COLUMN `character_type` VARCHAR(16) NOT NULL DEFAULT '''' COMMENT ''角色类型:creator=Candy Creators / character=Candy characters / other=Other characters'' AFTER `category`',
  'SELECT ''lp_short_item.character_type 已存在，跳过'' AS msg');
PREPARE _stmt FROM @ddl;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;

-- --------------------------------------------
-- 2. 存量数据按标题关键词自动打分类（只补空值，可重复执行）
-- --------------------------------------------
UPDATE `lp_short_item` SET `category` = 'Stepsister'
  WHERE (`category` = '' OR `category` IS NULL)
    AND (`title` LIKE '%step-sister%' OR `title` LIKE '%stepsister%' OR `title` LIKE '%step sister%'
         OR `title` LIKE '%step-daughter%' OR `title` LIKE '%step daughter%');

UPDATE `lp_short_item` SET `category` = 'Stepmom'
  WHERE (`category` = '' OR `category` IS NULL)
    AND (`title` LIKE '%stepmom%' OR `title` LIKE '%step-mom%' OR `title` LIKE '%step mom%');

UPDATE `lp_short_item` SET `category` = 'Milf'
  WHERE (`category` = '' OR `category` IS NULL)
    AND (`title` LIKE '%milf%' OR `title` LIKE '% mom%' OR `title` LIKE '%mother%' OR `title` LIKE '%fianc%');

UPDATE `lp_short_item` SET `category` = 'Wife'
  WHERE (`category` = '' OR `category` IS NULL) AND `title` LIKE '%wife%';

UPDATE `lp_short_item` SET `category` = 'Teacher'
  WHERE (`category` = '' OR `category` IS NULL)
    AND (`title` LIKE '%teacher%' OR `title` LIKE '%professor%' OR `title` LIKE '%student%');

UPDATE `lp_short_item` SET `category` = 'Babysitter'
  WHERE (`category` = '' OR `category` IS NULL) AND `title` LIKE '%babysitter%';

UPDATE `lp_short_item` SET `category` = 'Nun'
  WHERE (`category` = '' OR `category` IS NULL) AND `title` LIKE '%nun%';

UPDATE `lp_short_item` SET `category` = 'Cuckold'
  WHERE (`category` = '' OR `category` IS NULL) AND `title` LIKE '%cuckold%';

UPDATE `lp_short_item` SET `category` = 'Nympho'
  WHERE (`category` = '' OR `category` IS NULL) AND `title` LIKE '%nympho%';

UPDATE `lp_short_item` SET `category` = 'Maid'
  WHERE (`category` = '' OR `category` IS NULL) AND `title` LIKE '%maid%';

UPDATE `lp_short_item` SET `category` = 'Fantasy'
  WHERE (`category` = '' OR `category` IS NULL)
    AND (`title` LIKE '%fantasy%' OR `title` LIKE '%vampire%' OR `title` LIKE '%witch%' OR `title` LIKE '%elf%');

-- 角色类型：存量统一归到 Candy characters（本站官方角色内容），需要区分时在后台改
UPDATE `lp_short_item` SET `character_type` = 'character'
  WHERE `character_type` = '' OR `character_type` IS NULL;

-- --------------------------------------------
-- 3. 校验
-- --------------------------------------------
SELECT '--- lp_short_item 新字段 ---' AS `--`;
SELECT COLUMN_NAME, COLUMN_TYPE, COLUMN_COMMENT FROM `information_schema`.`COLUMNS`
  WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'lp_short_item'
    AND `COLUMN_NAME` IN ('category', 'character_type');
SELECT '--- 分类分布 ---' AS `--`;
SELECT `category`, COUNT(*) AS n FROM `lp_short_item` GROUP BY `category` ORDER BY n DESC;
SELECT '--- 角色类型分布 ---' AS `--`;
SELECT `character_type`, COUNT(*) AS n FROM `lp_short_item` GROUP BY `character_type`;
