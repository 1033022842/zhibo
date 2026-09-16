-- ============================================
-- AI 女友端「Posts 动态」功能
-- 执行方式：source php/sql/upgrade_posts.sql（数据库 live_platform）
--
-- 说明：
--   1) 新增动态表 lp_post_item（后台可配置，前台 posts 页展示）
--   2) 预置 candy.ai 帖子存档抽取的 6 条真实数据，后台可自行增删改
--   3) 新增后台菜单「动态管理」（挂在「直播运营」目录下）
-- ============================================

-- 1. 动态表
CREATE TABLE IF NOT EXISTS `lp_post_item` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `post_id` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '原站帖子id',
  `character_name` VARCHAR(120) NOT NULL DEFAULT '' COMMENT '角色名',
  `character_avatar` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '角色头像(相对路径或外链)',
  `character_url` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '角色主页链接',
  `video_url` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '帖子视频URL',
  `poster_url` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '视频封面URL(可空)',
  `description` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '帖子描述(可空)',
  `likes` INT NOT NULL DEFAULT 0 COMMENT '点赞数',
  `views` INT NOT NULL DEFAULT 0 COMMENT '浏览数',
  `weigh` INT NOT NULL DEFAULT 0 COMMENT '权重(越大越靠前)',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态(1上架0下架)',
  `created_at` DATETIME DEFAULT NULL COMMENT '创建时间',
  `updated_at` DATETIME DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_status_weigh` (`status`, `weigh`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI女友端帖子动态';

-- 2. 预置 candy.ai 帖子存档数据（按原站顺序，weigh 递减）
INSERT INTO `lp_post_item`
  (`post_id`, `character_name`, `character_avatar`, `character_url`, `video_url`, `poster_url`, `description`, `likes`, `views`, `weigh`)
SELECT * FROM (
  SELECT '163' AS pid, 'Fernanda' AS cn, '/posts_files/252342953-567b5def-6d64-425f-bfdf-9df6ab892688-webp90' AS ca, 'https://candy.ai/ai-girlfriend/fernanda-montoya' AS cu, 'https://cdn.candy.ai/feed-post-c1e0f2dc-0ae5-4b58-a58f-ae94edf45236' AS vu,
         '' AS pu, '' AS ds, 19675 AS lk, 0 AS vw, 60 AS w
  UNION ALL
  SELECT '227' AS pid, 'Daniela' AS cn, '/posts_files/237115223-919b9bce-bff0-4f00-a522-b1d9721a6929-webp90' AS ca, 'https://candy.ai/ai-girlfriend/daniela-estrada' AS cu, 'https://cdn.candy.ai/feed-post-cdbfb309-04fb-443c-85c6-6047d6c32a30' AS vu,
         '' AS pu, '' AS ds, 0 AS lk, 0 AS vw, 50 AS w
  UNION ALL
  SELECT '281' AS pid, 'Brooke' AS cn, '/posts_files/199759738-8fe6dc4f-ed18-4034-a5f5-65de62ed8431-webp90' AS ca, 'https://candy.ai/ai-girlfriend/brooke-madison' AS cu, 'https://cdn.candy.ai/feed-post-ab43c59f-12a3-425f-a8b1-6661108bb01d' AS vu,
         '' AS pu, '' AS ds, 0 AS lk, 0 AS vw, 40 AS w
  UNION ALL
  SELECT '819' AS pid, 'Kristen' AS cn, '/posts_files/250834990-7d3a5ee3-d6ab-4965-9b82-9adab7a52aa4-webp90' AS ca, 'https://candy.ai/ai-girlfriend/kristen-hale' AS cu, 'https://cdn.candy.ai/feed-post-6e397c5b-db17-47f9-97d1-b01cb9ed4429' AS vu,
         '' AS pu, '' AS ds, 0 AS lk, 0 AS vw, 30 AS w
  UNION ALL
  SELECT '545' AS pid, 'Elodie' AS cn, '/posts_files/283438799-d4ce3e32-3c00-417d-95a3-127bee1095da-webp90' AS ca, 'https://candy.ai/ai-girlfriend/elodie-valmont' AS cu, 'https://cdn.candy.ai/feed-post-4f67af68-51a1-4df0-b542-8f13f58c8a38' AS vu,
         '' AS pu, '' AS ds, 0 AS lk, 0 AS vw, 20 AS w
  UNION ALL
  SELECT '167' AS pid, 'Layla' AS cn, '/posts_files/214124264-83e962d0-e04a-4817-843b-75e09093ff2b-webp90' AS ca, 'https://candy.ai/ai-girlfriend/layla-farah' AS cu, 'https://cdn.candy.ai/feed-post-40da34de-fdd2-4da6-86f3-feff1eac4728' AS vu,
         '' AS pu, '' AS ds, 0 AS lk, 0 AS vw, 10 AS w
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM `lp_post_item`);

UPDATE `lp_post_item` SET `status` = 1, `created_at` = NOW(), `updated_at` = NOW() WHERE `created_at` IS NULL;

-- 3. 后台菜单「动态管理」（挂在「直播运营」目录下）
SET @live_pid = IFNULL((SELECT `id` FROM `ba_admin_rule` WHERE `title` = '直播运营' AND `type` = 'menu_dir' ORDER BY `id` DESC LIMIT 1), 0);

INSERT IGNORE INTO `ba_admin_rule` (`pid`, `type`, `name`, `title`, `icon`, `path`, `component`, `menu_type`, `keepalive`, `weigh`, `status`) VALUES
(@live_pid, 'menu', 'live/postItem', '动态管理', 'fa fa-camera-retro', 'live/postItem', '/src/views/backend/live/postItem/index.vue', 'tab', 1, 39, 1);

SET @post_pid = (SELECT `id` FROM `ba_admin_rule` WHERE `name` = 'live/postItem' AND `type` = 'menu' LIMIT 1);

INSERT IGNORE INTO `ba_admin_rule` (`pid`, `type`, `name`, `title`, `icon`, `path`, `component`, `menu_type`, `keepalive`, `weigh`, `status`) VALUES
(@post_pid, 'button', 'live/postItem/index', '查看', '', '', '', NULL, 0, 10, 1),
(@post_pid, 'button', 'live/postItem/add', '新增', '', '', '', NULL, 0, 9, 1),
(@post_pid, 'button', 'live/postItem/edit', '编辑', '', '', '', NULL, 0, 8, 1),
(@post_pid, 'button', 'live/postItem/del', '删除', '', '', '', NULL, 0, 7, 1);

SELECT `id`, `pid`, `title`, `name`, `component`, `status` FROM `ba_admin_rule` WHERE `name` LIKE 'live/postItem%';
SELECT COUNT(*) AS post_items FROM `lp_post_item`;
