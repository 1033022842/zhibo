-- ============================================
-- 首页推荐角色：新增「所属板块」字段
-- 在 live_platform（本项目为 zhibo）数据库执行；脚本可重复执行
-- 板块留空 = 出现在首页第一个两排列表；填了板块名 = 归到下方对应板块
-- ============================================

-- 1. 幂等新增 section 字段
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lp_home_character' AND COLUMN_NAME = 'section');
SET @sql := IF(@col = 0,
  'ALTER TABLE `lp_home_character` ADD COLUMN `section` VARCHAR(60) NOT NULL DEFAULT '''' COMMENT ''所属板块'' AFTER `tags`',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. 演示用：把部分已有角色分到两个板块，便于立刻看到下方板块效果
--    （可在后台「首页推荐角色」里逐个修改「所属板块」，或执行下面注释里的语句清空）
UPDATE `lp_home_character` SET `section` = 'Anime' WHERE `name` IN ('Aiko', 'Yuki') AND `section` = '';
UPDATE `lp_home_character` SET `section` = 'Sweet' WHERE `name` IN ('Bonnie', 'Mia') AND `section` = '';

-- 清空板块（如需还原成只有一个列表）：
-- UPDATE `lp_home_character` SET `section` = '';

SELECT `section`, COUNT(*) AS cnt FROM `lp_home_character` GROUP BY `section` ORDER BY `section`;
