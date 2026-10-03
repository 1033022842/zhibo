-- 创作作品上架站内：记录上架到「首页推荐角色」的 ID（0=未上架）
-- 用途：审核通过时自动上架，靠该字段做幂等，避免重复建卡/重复建角色
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'lp_creator_submission'
    AND COLUMN_NAME = 'home_character_id');
SET @sql := IF(@col = 0,
  'ALTER TABLE `lp_creator_submission` ADD COLUMN `home_character_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''上架首页角色卡ID(0=未上架)'' AFTER `persona_id`',
  'SELECT ''home_character_id already exists''');
PREPARE s FROM @sql;
EXECUTE s;
DEALLOCATE PREPARE s;

-- 校验
SHOW COLUMNS FROM `lp_creator_submission` LIKE 'home_character_id';
