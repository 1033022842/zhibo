-- ============================================
-- AI 女友端「Candy Shop 商店」功能
-- 执行方式：source php/sql/upgrade_shop.sql（数据库 live_platform）
--
-- 说明：
--   1) 新增商店商品表 lp_shop_item（后台可配置商品，前台 Shop 页展示）
--   2) 预置 candy.ai 商店的 10 个商品（复刻页面时的原始数据），后台可自行增删改
--   3) 新增后台菜单「商品管理」（挂在「直播运营」目录下）
-- ============================================

-- 1. 商品表
CREATE TABLE IF NOT EXISTS `lp_shop_item` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `title` VARCHAR(120) NOT NULL DEFAULT '' COMMENT '商品名称',
  `description` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '商品描述(详情弹窗用)',
  `cover_url` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '封面图URL(详情弹窗第1帧的海报)',
  `video_url` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '预览视频URL(列表卡片hover播放)',
  `images` TEXT NULL COMMENT '详情弹窗附加图片URL，逗号分隔',
  `price` INT NOT NULL DEFAULT 0 COMMENT '价格(钻石)',
  `rating` DECIMAL(2,1) NOT NULL DEFAULT 5.0 COMMENT '评分(0~5)',
  `reviews` INT NOT NULL DEFAULT 0 COMMENT '评价数',
  `weigh` INT NOT NULL DEFAULT 0 COMMENT '权重(越大越靠前)',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态(1上架0下架)',
  `created_at` DATETIME DEFAULT NULL COMMENT '创建时间',
  `updated_at` DATETIME DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_status_weigh` (`status`, `weigh`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI女友端商店商品';

-- 2. 预置 candy.ai 商店的 10 个商品（按原站展示顺序，weigh 递减）
INSERT INTO `lp_shop_item`
  (`title`, `description`, `cover_url`, `video_url`, `images`, `price`, `rating`, `reviews`, `weigh`)
SELECT * FROM (
  SELECT 'Starlet Cocktail Dress' AS t,
         'A brilliant cocktail dress with a clean neckline and heels: old-Hollywood glamour as one complete outfit for a big entrance.' AS d,
         'https://cdn.candy.ai/candy_shop/items/0c440c2b-b714-4dc9-a921-69407e4c3670.webp' AS c,
         'https://cdn.candy.ai/candy_shop/items/ea9bb29d-8b9f-4ec8-a8a1-e2f0806145e9.mp4' AS v,
         'https://cdn.candy.ai/candy_shop/items/cfd3745a-baa4-4d78-906d-1ba32de986e5.webp' AS im,
         79 AS p, 4.6 AS r, 1084 AS rv, 100 AS w
  UNION ALL SELECT 'Lace Babydoll', '',
         'https://cdn.candy.ai/candy_shop/items/f76aa81c-9b7a-40d6-a325-90298524b63a.webp',
         'https://cdn.candy.ai/candy_shop/items/141bff68-f7d6-4836-8e86-68307fcec37b.mp4', '', 99, 4.5, 1697, 90
  UNION ALL SELECT 'Sheer Lace Bodysuit & Choker', '',
         'https://cdn.candy.ai/candy_shop/items/989aa5cb-765c-4f0c-9e20-01e92f26d617.webp',
         'https://cdn.candy.ai/candy_shop/items/8d4eb55b-6ce0-42f1-a66f-fede8e4bb881.mp4', '', 129, 4.8, 673, 80
  UNION ALL SELECT 'Vinyl Wet-Look Micro Dress', '',
         'https://cdn.candy.ai/candy_shop/items/74854725-473e-45c4-af28-429439f131d2.webp',
         'https://cdn.candy.ai/candy_shop/items/9bec5857-ff89-40b4-9001-b9869450f963.mp4', '', 179, 4.5, 548, 70
  UNION ALL SELECT 'Aviator Officer Uniform', '',
         'https://cdn.candy.ai/candy_shop/items/82dae3c6-93ed-4d26-8499-ef5f53c91580.webp',
         'https://cdn.candy.ai/candy_shop/items/5b957b89-8559-44df-81ed-de8ef5fccede.mp4', '', 49, 4.7, 582, 60
  UNION ALL SELECT 'Bondage Teddy & Leash', '',
         'https://cdn.candy.ai/candy_shop/items/762a5c8e-fd76-4604-a229-f3bcd90c167a.webp',
         'https://cdn.candy.ai/candy_shop/items/0ab178d9-4263-4cd1-9a00-6cbbf8ca3a10.mp4', '', 249, 4.7, 391, 50
  UNION ALL SELECT 'Burgundy Corset Ensemble', '',
         'https://cdn.candy.ai/candy_shop/items/40e09b1b-86d0-4693-89b2-6373743ddd7e.webp',
         'https://cdn.candy.ai/candy_shop/items/e36e11af-97c8-43c1-9d11-282776d60b6b.mp4', '', 169, 4.9, 682, 40
  UNION ALL SELECT 'Kitten Play Set', '',
         'https://cdn.candy.ai/candy_shop/items/f81433d9-abb1-4d22-83b8-90b2f329e6d1.webp',
         'https://cdn.candy.ai/candy_shop/items/01c36914-e356-4c99-882b-fe914f7e6e03.mp4', '', 149, 4.6, 1178, 30
  UNION ALL SELECT 'Crotchless Fishnet & Collar', '',
         'https://cdn.candy.ai/candy_shop/items/9d89aa7d-277f-4e22-9af5-2f1d693d9b6c.webp',
         'https://cdn.candy.ai/candy_shop/items/580b3cde-e275-48f4-bab3-0679b5a505d9.mp4', '', 219, 4.8, 1762, 20
  UNION ALL SELECT 'Sheer Mesh Club Dress', '',
         'https://cdn.candy.ai/candy_shop/items/4b3ad216-d3b9-49db-bbbf-e2236cd97c28.webp',
         'https://cdn.candy.ai/candy_shop/items/2e2d4be7-2bea-40c7-9ea9-41cd2bb34725.mp4', '', 199, 4.9, 794, 10
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM `lp_shop_item`);

UPDATE `lp_shop_item` SET `status` = 1, `created_at` = NOW(), `updated_at` = NOW() WHERE `created_at` IS NULL;

-- 2.1) 修正两条预置商品的封面（首版种子里是无效占位 URL，仅在该值仍是占位时纠正）
UPDATE `lp_shop_item` SET `cover_url` = 'https://cdn.candy.ai/candy_shop/items/82dae3c6-93ed-4d26-8499-ef5f53c91580.webp'
  WHERE `title` = 'Aviator Officer Uniform' AND `cover_url` = 'https://cdn.candy.ai/candy_shop/items/6e2a8f1e-2c9a-4a1f-9a2c-5b0b6c2f6e3a.webp';
UPDATE `lp_shop_item` SET `cover_url` = 'https://cdn.candy.ai/candy_shop/items/40e09b1b-86d0-4693-89b2-6373743ddd7e.webp'
  WHERE `title` = 'Burgundy Corset Ensemble' AND `cover_url` = 'https://cdn.candy.ai/candy_shop/items/1a2b3c4d-5e6f-4a7b-8c9d-0e1f2a3b4c5d.webp';

-- 3. 后台菜单「商品管理」（挂在「直播运营」目录下）
SET @live_pid = IFNULL((SELECT `id` FROM `ba_admin_rule` WHERE `title` = '直播运营' AND `type` = 'menu_dir' ORDER BY `id` DESC LIMIT 1), 0);

INSERT IGNORE INTO `ba_admin_rule` (`pid`, `type`, `name`, `title`, `icon`, `path`, `component`, `menu_type`, `keepalive`, `weigh`, `status`) VALUES
(@live_pid, 'menu', 'live/shopItem', '商品管理', 'fa fa-shopping-bag', 'live/shopItem', '/src/views/backend/live/shopItem/index.vue', 'tab', 1, 40, 1);

SET @shop_pid = (SELECT `id` FROM `ba_admin_rule` WHERE `name` = 'live/shopItem' AND `type` = 'menu' LIMIT 1);

INSERT IGNORE INTO `ba_admin_rule` (`pid`, `type`, `name`, `title`, `icon`, `path`, `component`, `menu_type`, `keepalive`, `weigh`, `status`) VALUES
(@shop_pid, 'button', 'live/shopItem/index', '查看', '', '', '', NULL, 0, 10, 1),
(@shop_pid, 'button', 'live/shopItem/add', '新增', '', '', '', NULL, 0, 9, 1),
(@shop_pid, 'button', 'live/shopItem/edit', '编辑', '', '', '', NULL, 0, 8, 1),
(@shop_pid, 'button', 'live/shopItem/del', '删除', '', '', '', NULL, 0, 7, 1);

SELECT `id`, `pid`, `title`, `name`, `component`, `status` FROM `ba_admin_rule` WHERE `name` LIKE 'live/shopItem%';
SELECT COUNT(*) AS shop_items FROM `lp_shop_item`;
