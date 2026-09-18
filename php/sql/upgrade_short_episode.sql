-- ============================================
-- 短剧「多集 + 按集收费 + 介绍」升级
-- 1) lp_short_item 增加 介绍(description) / 前N集免费(free_episodes)
-- 2) 新增剧集表 lp_short_episode（每集一个视频、一个价格、可单独解锁）
-- 3) 后台菜单「短剧剧集」
-- 本脚本幂等，可重复执行
-- ============================================

-- --------------------------------------------
-- 1. lp_short_item：介绍 + 前N集免费
-- --------------------------------------------
SET @has_desc = (SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
  WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'lp_short_item' AND `COLUMN_NAME` = 'description');
SET @ddl = IF(@has_desc = 0,
  'ALTER TABLE `lp_short_item` ADD COLUMN `description` TEXT NULL COMMENT ''短剧介绍(前台弹窗展示)'' AFTER `title`',
  'SELECT ''lp_short_item.description 已存在，跳过'' AS msg');
PREPARE _stmt FROM @ddl;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;

SET @has_free = (SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
  WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'lp_short_item' AND `COLUMN_NAME` = 'free_episodes');
SET @ddl = IF(@has_free = 0,
  'ALTER TABLE `lp_short_item` ADD COLUMN `free_episodes` INT NOT NULL DEFAULT 0 COMMENT ''前N集免费(集号<=N 免钻)'' AFTER `description`',
  'SELECT ''lp_short_item.free_episodes 已存在，跳过'' AS msg');
PREPARE _stmt FROM @ddl;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;

-- --------------------------------------------
-- 2. 剧集表
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS `lp_short_episode` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '剧集ID',
  `short_id` int unsigned NOT NULL DEFAULT 0 COMMENT '所属短剧(lp_short_item.id)',
  `episode_no` int NOT NULL DEFAULT 1 COMMENT '第几集',
  `title` varchar(160) NOT NULL DEFAULT '' COMMENT '本集标题',
  `poster` varchar(500) NOT NULL DEFAULT '' COMMENT '本集封面，留空用短剧封面',
  `video_url` varchar(500) NOT NULL DEFAULT '' COMMENT '视频文件URL',
  `duration` varchar(32) NOT NULL DEFAULT '' COMMENT '时长，如 03:12',
  `price` int NOT NULL DEFAULT 0 COMMENT '本集价格(钻石)，0=免费',
  `weigh` int NOT NULL DEFAULT 0 COMMENT '权重，越大越靠前',
  `status` tinyint NOT NULL DEFAULT 1 COMMENT '1上架 0下架',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_short_episode`(`short_id` ASC, `episode_no` ASC) USING BTREE,
  INDEX `idx_short_status`(`short_id` ASC, `status` ASC, `episode_no` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '短剧剧集';

-- --------------------------------------------
-- 3. 后台菜单「短剧剧集」（挂在「直播运营」下）
-- --------------------------------------------
SET @live_pid = IFNULL((SELECT `id` FROM `ba_admin_rule` WHERE `title` = '直播运营' AND `type` = 'menu_dir' ORDER BY `id` DESC LIMIT 1), 0);

INSERT IGNORE INTO `ba_admin_rule` (`pid`, `type`, `name`, `title`, `icon`, `path`, `component`, `menu_type`, `keepalive`, `weigh`, `status`) VALUES
(@live_pid, 'menu', 'live/shortEpisode', '短剧剧集', 'fa fa-list-ol', 'live/shortEpisode', '/src/views/backend/live/shortEpisode/index.vue', 'tab', 1, 49, 1);

SET @ep_pid = (SELECT `id` FROM `ba_admin_rule` WHERE `name` = 'live/shortEpisode' AND `type` = 'menu' LIMIT 1);

INSERT IGNORE INTO `ba_admin_rule` (`pid`, `type`, `name`, `title`, `icon`, `path`, `component`, `menu_type`, `keepalive`, `weigh`, `status`) VALUES
(@ep_pid, 'button', 'live/shortEpisode/index', '查看', '', '', '', NULL, 0, 10, 1),
(@ep_pid, 'button', 'live/shortEpisode/add', '新增', '', '', '', NULL, 0, 9, 1),
(@ep_pid, 'button', 'live/shortEpisode/edit', '编辑', '', '', '', NULL, 0, 8, 1),
(@ep_pid, 'button', 'live/shortEpisode/del', '删除', '', '', '', NULL, 0, 7, 1);

-- --------------------------------------------
-- 4. 校验
-- --------------------------------------------
SELECT '--- lp_short_item 新字段 ---' AS `--`;
SELECT `COLUMN_NAME`, `COLUMN_TYPE`, `COLUMN_COMMENT` FROM `information_schema`.`COLUMNS`
WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'lp_short_item'
  AND `COLUMN_NAME` IN ('description', 'free_episodes');

SELECT '--- 剧集表 ---' AS `--`;
SELECT `TABLE_NAME`, `TABLE_COMMENT` FROM `information_schema`.`TABLES`
WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'lp_short_episode';

SELECT '--- 菜单 ---' AS `--`;
SELECT `id`, `pid`, `title`, `name`, `component`, `status` FROM `ba_admin_rule` WHERE `name` LIKE 'live/shortEpisode%';

SELECT '--- 剧集数据量 ---' AS `--`;
SELECT COUNT(*) AS episodes FROM `lp_short_episode`;
