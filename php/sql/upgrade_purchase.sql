-- ============================================
-- AI 女友端「购买 / 解锁 / 观看 / 点赞」能力
-- 执行方式：source php/sql/upgrade_purchase.sql（数据库 zhibo / live_platform）
--
-- 说明：
--   1) lp_user_item  —— 用户已购（背包）。商品购买与私密内容解锁共用一张表，
--                       用 item_type 区分；唯一键保证同一用户同一内容只有一行。
--   2) lp_post_like  —— 动态点赞。lp_post_item.likes 保留为原站基数，
--                       前台展示值 = 基数 + 本站真实点赞数。
--   3) lp_short_item / lp_private_item 补 video_url / images 字段，
--      让短剧与私密内容也能在本站弹窗里播放（原先只能跳 candy.ai 站外链接）。
--
-- 扣费统一走 app\live\service\WalletService（事务 + 行锁 + 流水）。
-- 本文件可重复执行。
-- ============================================

-- --------------------------------------------
-- 1. 用户已购（背包）
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS `lp_user_item` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `user_id` bigint(20) UNSIGNED NOT NULL COMMENT '用户ID',
  `item_type` varchar(16) NOT NULL DEFAULT 'shop' COMMENT '类型:shop商品 private私密内容',
  `item_id` bigint(20) UNSIGNED NOT NULL DEFAULT 0 COMMENT '业务ID(lp_shop_item.id / lp_private_item.id)',
  `quantity` int(11) NOT NULL DEFAULT 1 COMMENT '拥有数量',
  `price` int(11) NOT NULL DEFAULT 0 COMMENT '成交单价(钻石)',
  `created_at` datetime DEFAULT NULL COMMENT '首次购买时间',
  `updated_at` datetime DEFAULT NULL COMMENT '最近购买时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_user_item`(`user_id` ASC, `item_type` ASC, `item_id` ASC) USING BTREE,
  INDEX `idx_user_type`(`user_id` ASC, `item_type` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '用户已购(背包)';

-- --------------------------------------------
-- 2. 动态点赞
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS `lp_post_like` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `user_id` bigint(20) UNSIGNED NOT NULL COMMENT '用户ID',
  `post_id` varchar(64) NOT NULL DEFAULT '' COMMENT '帖子ID(lp_post_item.post_id)',
  `created_at` datetime DEFAULT NULL COMMENT '点赞时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_user_post`(`user_id` ASC, `post_id` ASC) USING BTREE,
  INDEX `idx_post_id`(`post_id` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '动态点赞';

-- --------------------------------------------
-- 3. 短剧补视频字段（本站弹窗播放）
-- --------------------------------------------
SET @has_short_video = (SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
  WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'lp_short_item' AND `COLUMN_NAME` = 'video_url');
SET @ddl = IF(@has_short_video = 0,
  'ALTER TABLE `lp_short_item` ADD COLUMN `video_url` VARCHAR(500) NOT NULL DEFAULT '''' COMMENT ''视频文件URL(本站弹窗播放,留空则跳 href 外链)'' AFTER `poster`',
  'SELECT ''lp_short_item.video_url 已存在，跳过'' AS msg');
PREPARE _stmt FROM @ddl;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;

-- --------------------------------------------
-- 4. 私密内容补视频 / 图片字段（解锁后本站观看）
-- --------------------------------------------
SET @has_private_video = (SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
  WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'lp_private_item' AND `COLUMN_NAME` = 'video_url');
SET @ddl = IF(@has_private_video = 0,
  'ALTER TABLE `lp_private_item` ADD COLUMN `video_url` VARCHAR(500) NOT NULL DEFAULT '''' COMMENT ''视频文件URL(解锁后本站播放)'' AFTER `poster`',
  'SELECT ''lp_private_item.video_url 已存在，跳过'' AS msg');
PREPARE _stmt FROM @ddl;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;

SET @has_private_images = (SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
  WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'lp_private_item' AND `COLUMN_NAME` = 'images');
SET @ddl = IF(@has_private_images = 0,
  'ALTER TABLE `lp_private_item` ADD COLUMN `images` TEXT NULL COMMENT ''图片URL，英文逗号分隔(解锁后本站查看)'' AFTER `video_url`',
  'SELECT ''lp_private_item.images 已存在，跳过'' AS msg');
PREPARE _stmt FROM @ddl;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;

-- --------------------------------------------
-- 5. 结果校验
-- --------------------------------------------
SELECT '--- 新表 ---' AS `--`;
SELECT `TABLE_NAME`, `TABLE_COMMENT` FROM `information_schema`.`TABLES`
WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` IN ('lp_user_item', 'lp_post_like');

SELECT '--- 新字段 ---' AS `--`;
SELECT `TABLE_NAME`, `COLUMN_NAME`, `COLUMN_TYPE`, `COLUMN_COMMENT` FROM `information_schema`.`COLUMNS`
WHERE `TABLE_SCHEMA` = DATABASE()
  AND ((`TABLE_NAME` = 'lp_short_item' AND `COLUMN_NAME` = 'video_url')
    OR (`TABLE_NAME` = 'lp_private_item' AND `COLUMN_NAME` IN ('video_url', 'images')));
