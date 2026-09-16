-- ============================================
-- 后台「直播运营」下四个模块的菜单重复修复 + 测试数据
-- 执行方式：source php/sql/fix_menu_dup_and_seed_test.sql（数据库 live_platform）
--
-- 背景：
--   ba_admin_rule 表只有 PRIMARY(id) 和 pid 两个索引，name 上没有唯一索引，
--   而 upgrade_shop/shorts/posts/private_content.sql 里写的是 INSERT IGNORE
--   （本意是靠唯一键去重）。迁移脚本每执行一次就多插一份，导致后台菜单里
--   「商品管理」出现 4 份、「短剧管理 / 动态管理 / 私密内容管理」各 2 份，
--   每份菜单下的 查看/新增/编辑/删除 四个按钮也跟着成倍重复。
--
-- 本文件做三件事：
--   1) 把 name 重复的行去掉，只保留 id 最小的那一份，并把子节点 pid 重指过去
--   2) 给 ba_admin_rule.name 补上唯一索引，让 INSERT IGNORE 真正生效（幂等）
--   3) 给四张业务表各插入 3 条测试数据，用于验证后台 CRUD 与前台展示
--
-- 本文件可重复执行。
-- ============================================

-- --------------------------------------------
-- 1. 菜单去重
-- --------------------------------------------
DROP TEMPORARY TABLE IF EXISTS `_dup_keep`;
CREATE TEMPORARY TABLE `_dup_keep` AS
SELECT `name`, MIN(`id`) AS `keep_id`
FROM `ba_admin_rule`
GROUP BY `name`
HAVING COUNT(*) > 1;

-- 1.1) 先把挂在「将被删掉的重复父节点」下的子节点，改挂到保留下来的父节点
UPDATE `ba_admin_rule` c
JOIN `ba_admin_rule` p ON c.`pid` = p.`id`
JOIN `_dup_keep` d ON p.`name` = d.`name`
SET c.`pid` = d.`keep_id`
WHERE c.`pid` <> d.`keep_id`;

-- 1.2) 再删除重复行
DELETE r FROM `ba_admin_rule` r
JOIN `_dup_keep` d ON r.`name` = d.`name`
WHERE r.`id` > d.`keep_id`;

DROP TEMPORARY TABLE IF EXISTS `_dup_keep`;

-- --------------------------------------------
-- 2. 给 name 补唯一索引（已存在则跳过）
-- --------------------------------------------
SET @has_uk = (SELECT COUNT(*) FROM `information_schema`.`STATISTICS`
               WHERE `TABLE_SCHEMA` = DATABASE()
                 AND `TABLE_NAME` = 'ba_admin_rule'
                 AND `INDEX_NAME` = 'uk_name');
SET @ddl = IF(@has_uk = 0,
              'ALTER TABLE `ba_admin_rule` ADD UNIQUE KEY `uk_name` (`name`)',
              'SELECT ''uk_name 已存在，跳过'' AS msg');
PREPARE _stmt FROM @ddl;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;

-- --------------------------------------------
-- 3. 测试数据（各 3 条，标题带 [测试] 前缀，便于识别与清理）
--    封面/头像等资源均从已有行复制，保证前台能正常渲染
-- --------------------------------------------

-- 3.1) 短剧
INSERT INTO `lp_short_item`
  (`title`, `poster`, `href`, `section`, `rank`, `progress`, `spicy`, `featured`, `new_episodes`, `weigh`, `status`, `created_at`, `updated_at`)
SELECT CONCAT('[测试] ', `title`), `poster`, `href`, 'explore', 0, 0.00, 0, '', 0, 5, 1, NOW(), NOW()
FROM `lp_short_item`
WHERE `id` IN (1, 2, 3)
  AND NOT EXISTS (SELECT 1 FROM (SELECT `id` FROM `lp_short_item` WHERE `title` LIKE '[测试] %' LIMIT 1) x);

-- 3.2) 动态
INSERT INTO `lp_post_item`
  (`post_id`, `character_name`, `character_avatar`, `character_url`, `video_url`, `poster_url`, `description`, `likes`, `views`, `weigh`, `status`, `created_at`, `updated_at`)
SELECT CONCAT('test-', `post_id`), CONCAT('[测试] ', `character_name`), `character_avatar`, `character_url`,
       `video_url`, `poster_url`, `description`, 0, 0, 5, 1, NOW(), NOW()
FROM `lp_post_item`
WHERE `post_id` NOT LIKE 'test-%'
  AND NOT EXISTS (SELECT 1 FROM (SELECT `id` FROM `lp_post_item` WHERE `post_id` LIKE 'test-%' LIMIT 1) x)
ORDER BY `id` ASC
LIMIT 3;

-- 3.3) 私密内容（取文案最短的 3 条，避免加前缀后超出 title 长度）
INSERT INTO `lp_private_item`
  (`title`, `poster`, `avatar`, `creator`, `price`, `like_rate`, `media_type`, `video_count`, `duration`, `image_count`, `badge`, `purchase_url`, `weigh`, `status`, `created_at`, `updated_at`)
SELECT CONCAT('[测试] ', `title`), `poster`, `avatar`, `creator`, `price`, 0, `media_type`, `video_count`,
       `duration`, `image_count`, '', `purchase_url`, 5, 1, NOW(), NOW()
FROM `lp_private_item`
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id` FROM `lp_private_item` WHERE `title` LIKE '[测试] %' LIMIT 1) x)
ORDER BY CHAR_LENGTH(`title`) ASC
LIMIT 3;

-- 3.4) 商品
INSERT INTO `lp_shop_item`
  (`title`, `description`, `cover_url`, `video_url`, `images`, `price`, `rating`, `reviews`, `weigh`, `status`, `created_at`, `updated_at`)
SELECT CONCAT('[测试] ', `title`), `description`, `cover_url`, `video_url`, `images`, `price`, 5.0, 0, 5, 1, NOW(), NOW()
FROM `lp_shop_item`
WHERE `id` IN (1, 2, 3)
  AND NOT EXISTS (SELECT 1 FROM (SELECT `id` FROM `lp_shop_item` WHERE `title` LIKE '[测试] %' LIMIT 1) x);

-- --------------------------------------------
-- 4. 结果校验
-- --------------------------------------------
SELECT '--- 剩余 name 重复（应为空）---' AS `--`;
SELECT `name`, COUNT(*) AS c FROM `ba_admin_rule` GROUP BY `name` HAVING c > 1;

SELECT '--- 四个模块菜单（应各 5 行：1 菜单 + 4 按钮）---' AS `--`;
SELECT `id`, `pid`, `type`, `name`, `title`, `weigh`, `status` FROM `ba_admin_rule`
WHERE `name` LIKE 'live/shopItem%' OR `name` LIKE 'live/shortItem%'
   OR `name` LIKE 'live/postItem%' OR `name` LIKE 'live/privateItem%'
ORDER BY `name`;

SELECT '--- 各表总数 / 测试数据数 ---' AS `--`;
SELECT 'lp_shop_item' AS t, COUNT(*) AS total, SUM(`title` LIKE '[测试] %') AS test_rows FROM `lp_shop_item`
UNION ALL SELECT 'lp_short_item', COUNT(*), SUM(`title` LIKE '[测试] %') FROM `lp_short_item`
UNION ALL SELECT 'lp_post_item', COUNT(*), SUM(`post_id` LIKE 'test-%') FROM `lp_post_item`
UNION ALL SELECT 'lp_private_item', COUNT(*), SUM(`title` LIKE '[测试] %') FROM `lp_private_item`;
