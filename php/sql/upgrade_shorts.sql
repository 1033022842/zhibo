-- ============================================
-- AI 女友端「Candy Shorts 短剧」功能
-- 执行方式：source php/sql/upgrade_shorts.sql（数据库 live_platform）
--
-- 说明：
--   1) 新增短剧卡片表 lp_short_item（后台可配置，前台 /api/live/shorts 读取）
--   2) 预置 candy.ai/candy-shorts 存档页抽取的 34 张卡片
--      section 取值：continue_watching / top_series / explore
--      （原 JSON 里的 key 为 continue_watching / top_series / explore_all_shorts，
--        第三条统一归一化为 explore）
--   3) 新增后台菜单「短剧管理」（挂在「直播运营」目录下）
--
-- 本文件由 .trae/gen_shorts_seed.py 生成，改动数据请改脚本后重新生成。
-- ============================================

-- 1. 短剧卡片表
CREATE TABLE IF NOT EXISTS `lp_short_item` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `title` VARCHAR(160) NOT NULL DEFAULT '' COMMENT '卡片标题',
  `poster` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '封面图(相对路径或外链)',
  `href` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '卡片兜底链接',
  `section` VARCHAR(32) NOT NULL DEFAULT 'explore' COMMENT '分区(continue_watching/top_series/explore)',
  `rank` INT NOT NULL DEFAULT 0 COMMENT '排行榜名次(仅 top_series 用)',
  `progress` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT '观看进度百分比',
  `spicy` TINYINT NOT NULL DEFAULT 0 COMMENT '是否 SPICY 角标',
  `featured` VARCHAR(16) NOT NULL DEFAULT '' COMMENT '高亮变体(空/ring/gradient)',
  `new_episodes` TINYINT NOT NULL DEFAULT 0 COMMENT '是否 New Episodes 角标',
  `weigh` INT NOT NULL DEFAULT 0 COMMENT '权重(越大越靠前)',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态(1上架0下架)',
  `created_at` DATETIME DEFAULT NULL COMMENT '创建时间',
  `updated_at` DATETIME DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_status_section_weigh` (`status`, `section`, `weigh`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI女友端短剧卡片';

-- 2. 预置原站存档的 34 张短剧卡片（仅表为空时插入）
INSERT INTO `lp_short_item` (`title`, `poster`, `href`, `section`, `rank`, `progress`, `spicy`, `featured`, `new_episodes`, `weigh`)
SELECT * FROM (
  SELECT 'Good Boy For Stepmom' AS t, '/candy-shorts_files/a7787809-3284-4cf1-807d-2a96f2c48995.webp' AS p, 'https://candy.ai/candy-shorts/series/good-boy-for-stepmom?episode_id=666&origin=continue_watching_shelf' AS h, 'continue_watching' AS s, 0 AS rk, 0.00 AS pr, 1 AS sp, '' AS ft, 0 AS ne, 40 AS w
  UNION ALL SELECT 'My Girlfriend''s Stepsister', '/candy-shorts_files/d05a857d-7b13-4516-a18b-a997a7c5461a.webp', 'https://candy.ai/candy-shorts/series/my-girlfriend-s-stepsister?episode_id=743&origin=continue_watching_shelf', 'continue_watching', 0, 0.00, 1, '', 0, 30
  UNION ALL SELECT 'My Step-Sister''s Dormitory', '/candy-shorts_files/288fc591-da10-400e-bff7-0cc873cd6f08.webp', 'https://candy.ai/candy-shorts/series/my-step-sister-s-dormitory?episode_id=632&origin=continue_watching_shelf', 'continue_watching', 0, 20.00, 0, '', 0, 20
  UNION ALL SELECT 'YES MA''AM', '/candy-shorts_files/73a1c69e-1a1c-4cd3-a1e2-e5db1d3a8ba6.webp', 'https://candy.ai/candy-shorts/series/82?episode_id=358&origin=continue_watching_shelf', 'continue_watching', 0, 0.00, 0, '', 0, 10
  UNION ALL SELECT 'My Girlfriend''s Stepsister', '/candy-shorts_files/d05a857d-7b13-4516-a18b-a997a7c5461a.webp', 'https://candy.ai/candy-shorts/series/my-girlfriend-s-stepsister?origin=top_ten_shelf', 'top_series', 1, 0.00, 1, 'ring', 1, 100
  UNION ALL SELECT 'My Step-Sister''s Dormitory', '/candy-shorts_files/288fc591-da10-400e-bff7-0cc873cd6f08.webp', 'https://candy.ai/candy-shorts/series/my-step-sister-s-dormitory?origin=top_ten_shelf', 'top_series', 2, 0.00, 0, 'gradient', 0, 90
  UNION ALL SELECT 'Bella, my step-daughter', '/candy-shorts_files/1c825698-ec26-40c5-b768-9428571344d3.webp', 'https://candy.ai/candy-shorts/series/bella-my-step-daughter?origin=top_ten_shelf', 'top_series', 3, 0.00, 1, 'ring', 0, 80
  UNION ALL SELECT 'My Girlfriend''s Twin', '/candy-shorts_files/e0319e41-35fe-4842-90cc-c1736adc2d3a.webp', 'https://candy.ai/candy-shorts/series/my-girlfriend-s-twin?origin=top_ten_shelf', 'top_series', 4, 0.00, 0, '', 1, 70
  UNION ALL SELECT 'I Couldn''t Say No to Our Babysitter', '/candy-shorts_files/6ad25327-527e-438d-827f-fe7b827b1aac.webp', 'https://candy.ai/candy-shorts/series/i-couldn-t-say-no-to-our-babysitter?origin=top_ten_shelf', 'top_series', 5, 0.00, 0, '', 0, 60
  UNION ALL SELECT 'Student Bodies', '/candy-shorts_files/d018047e-c869-428d-a70f-3629234a1f78.webp', 'https://candy.ai/candy-shorts/series/student-bodies?origin=top_ten_shelf', 'top_series', 6, 0.00, 0, 'gradient', 1, 50
  UNION ALL SELECT 'Mona: My Wild Step-Sister', '/candy-shorts_files/aa43a4c6-cb6d-4535-9601-04d18083e47e.webp', 'https://candy.ai/candy-shorts/series/mona-my-wild-step-sister?origin=top_ten_shelf', 'top_series', 7, 0.00, 1, 'ring', 0, 40
  UNION ALL SELECT 'Yes Professor', '/candy-shorts_files/152b537b-7452-42ac-8390-96257e626d0d.webp', 'https://candy.ai/candy-shorts/series/yes-professor?origin=top_ten_shelf', 'top_series', 8, 0.00, 0, '', 0, 30
  UNION ALL SELECT 'YES, DIRECTOR', '/candy-shorts_files/d60bda87-eecf-4bb0-b53e-02cea9b790be.webp', 'https://candy.ai/candy-shorts/series/yes-director?origin=top_ten_shelf', 'top_series', 9, 0.00, 0, '', 1, 20
  UNION ALL SELECT 'Good Boy For Stepmom', '/candy-shorts_files/a7787809-3284-4cf1-807d-2a96f2c48995.webp', 'https://candy.ai/candy-shorts/series/good-boy-for-stepmom?origin=top_ten_shelf', 'top_series', 10, 0.00, 1, 'ring', 0, 10
  UNION ALL SELECT 'Student Bodies', '/candy-shorts_files/d018047e-c869-428d-a70f-3629234a1f78(1).webp', 'https://candy.ai/candy-shorts/series/student-bodies?origin=library_grid', 'explore', 0, 0.00, 0, 'gradient', 1, 200
  UNION ALL SELECT 'My Wife''s Best Friend', '/candy-shorts_files/ea9a7db6-ce11-4b98-9ec8-eb9c44ffb08f.webp', 'https://candy.ai/candy-shorts/series/my-wife-s-best-friend?origin=library_grid', 'explore', 0, 0.00, 0, 'gradient', 1, 190
  UNION ALL SELECT 'My Stepmom''s Weekend', '/candy-shorts_files/ecb7e805-8669-48a9-9cde-813e24500a9f.webp', 'https://candy.ai/candy-shorts/series/my-stepmom-s-weekend?origin=library_grid', 'explore', 0, 0.00, 0, 'gradient', 1, 180
  UNION ALL SELECT 'My Stepmom Needs A Plus One', '/candy-shorts_files/5ad25462-d040-4be3-8557-dc455b63c12a.webp', 'https://candy.ai/candy-shorts/series/my-stepmom-needs-a-plus-one?origin=library_grid', 'explore', 0, 0.00, 0, '', 1, 170
  UNION ALL SELECT 'Got Stuck With My Girlfriend''s Stepmom', '/candy-shorts_files/fec884f9-4366-4b1a-ae6d-85b4e2811e3e.webp', 'https://candy.ai/candy-shorts/series/got-stuck-with-my-girlfriend-s-stepmom?origin=library_grid', 'explore', 0, 0.00, 0, '', 1, 160
  UNION ALL SELECT 'Room Service', '/candy-shorts_files/12f29943-0287-4db4-ab7a-11bc7577ebaf.webp', 'https://candy.ai/candy-shorts/series/room-service?origin=library_grid', 'explore', 0, 0.00, 0, '', 1, 150
  UNION ALL SELECT 'Tori''s broken vows', '/candy-shorts_files/c6f0d412-42da-4f50-b7a9-e2a20d0211c5.webp', 'https://candy.ai/candy-shorts/series/tori-s-broken-vows?origin=library_grid', 'explore', 0, 0.00, 1, 'ring', 1, 140
  UNION ALL SELECT 'My Bachelor Gift was My Fiancée Mom', '/candy-shorts_files/6705dded-34a1-4846-bce7-21e99e1a14f0.webp', 'https://candy.ai/candy-shorts/series/my-bachelor-gift-was-my-fiancee-mom?origin=library_grid', 'explore', 0, 0.00, 0, '', 1, 130
  UNION ALL SELECT 'I Dated My Best Friend Mom', '/candy-shorts_files/06be88af-c419-46ff-ac04-58e81f81a678.webp', 'https://candy.ai/candy-shorts/series/i-dated-my-best-friend-mom?origin=library_grid', 'explore', 0, 0.00, 0, '', 1, 120
  UNION ALL SELECT 'The Nuns Took Me In', '/candy-shorts_files/3b72a650-4a68-40b9-b94e-425a0bf87de3.webp', 'https://candy.ai/candy-shorts/series/the-nuns-took-me-in?origin=library_grid', 'explore', 0, 0.00, 0, '', 1, 110
  UNION ALL SELECT 'My Girlfriend''s Stepsister', '/candy-shorts_files/d05a857d-7b13-4516-a18b-a997a7c5461a(1).webp', 'https://candy.ai/candy-shorts/series/my-girlfriend-s-stepsister?origin=library_grid', 'explore', 0, 0.00, 1, 'ring', 1, 100
  UNION ALL SELECT 'YES, DIRECTOR', '/candy-shorts_files/d60bda87-eecf-4bb0-b53e-02cea9b790be(1).webp', 'https://candy.ai/candy-shorts/series/yes-director?origin=library_grid', 'explore', 0, 0.00, 0, '', 1, 90
  UNION ALL SELECT 'My Girlfriend''s Twin', '/candy-shorts_files/e0319e41-35fe-4842-90cc-c1736adc2d3a(1).webp', 'https://candy.ai/candy-shorts/series/my-girlfriend-s-twin?origin=library_grid', 'explore', 0, 0.00, 0, '', 1, 80
  UNION ALL SELECT 'Bachelor Party with My Fiancée''s Mother', '/candy-shorts_files/64643c4d-d5fa-465c-b887-1ef6dce34b4a.webp', 'https://candy.ai/candy-shorts/series/bachelor-party-with-my-fiancee-s-mother?origin=library_grid', 'explore', 0, 0.00, 0, '', 0, 70
  UNION ALL SELECT 'The Nun in Our Guest Room', '/candy-shorts_files/cd1143fb-9ee0-4461-8dce-620d3b9ab491.webp', 'https://candy.ai/candy-shorts/series/the-nun-in-our-guest-room?origin=library_grid', 'explore', 0, 0.00, 0, '', 0, 60
  UNION ALL SELECT 'Midnight Compartment', '/candy-shorts_files/34c562a5-00e5-45ef-9bb3-ff37c1375164.webp', 'https://candy.ai/candy-shorts/series/midnight-compartment?origin=library_grid', 'explore', 0, 0.00, 0, '', 0, 50
  UNION ALL SELECT 'I Couldn''t Say No to Our Babysitter', '/candy-shorts_files/6ad25327-527e-438d-827f-fe7b827b1aac(1).webp', 'https://candy.ai/candy-shorts/series/i-couldn-t-say-no-to-our-babysitter?origin=library_grid', 'explore', 0, 0.00, 0, '', 0, 40
  UNION ALL SELECT 'Bella, my step-daughter', '/candy-shorts_files/1c825698-ec26-40c5-b768-9428571344d3(1).webp', 'https://candy.ai/candy-shorts/series/bella-my-step-daughter?origin=library_grid', 'explore', 0, 0.00, 1, 'ring', 0, 30
  UNION ALL SELECT 'My Young Stepmom Needs a Date', '/candy-shorts_files/16b66b92-2b4e-480f-a2d2-0a67711f0ee6.webp', 'https://candy.ai/candy-shorts/series/my-young-stepmom-needs-a-date?origin=library_grid', 'explore', 0, 0.00, 0, '', 0, 20
  UNION ALL SELECT 'One Room with My Fiancée’s Mother', '/candy-shorts_files/ca20ccdd-4273-492a-840c-f37227dec3bc.webp', 'https://candy.ai/candy-shorts/series/one-room-with-my-fiancee-s-mother?origin=library_grid', 'explore', 0, 0.00, 0, '', 0, 10
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM `lp_short_item`);

UPDATE `lp_short_item` SET `status` = 1, `created_at` = NOW(), `updated_at` = NOW() WHERE `created_at` IS NULL;

-- 3. 后台菜单「短剧管理」（挂在「直播运营」目录下）
SET @live_pid = IFNULL((SELECT `id` FROM `ba_admin_rule` WHERE `title` = '直播运营' AND `type` = 'menu_dir' ORDER BY `id` DESC LIMIT 1), 0);

INSERT IGNORE INTO `ba_admin_rule` (`pid`, `type`, `name`, `title`, `icon`, `path`, `component`, `menu_type`, `keepalive`, `weigh`, `status`) VALUES
(@live_pid, 'menu', 'live/shortItem', '短剧管理', 'fa fa-film', 'live/shortItem', '/src/views/backend/live/shortItem/index.vue', 'tab', 1, 50, 1);

SET @short_pid = (SELECT `id` FROM `ba_admin_rule` WHERE `name` = 'live/shortItem' AND `type` = 'menu' LIMIT 1);

INSERT IGNORE INTO `ba_admin_rule` (`pid`, `type`, `name`, `title`, `icon`, `path`, `component`, `menu_type`, `keepalive`, `weigh`, `status`) VALUES
(@short_pid, 'button', 'live/shortItem/index', '查看', '', '', '', NULL, 0, 10, 1),
(@short_pid, 'button', 'live/shortItem/add', '新增', '', '', '', NULL, 0, 9, 1),
(@short_pid, 'button', 'live/shortItem/edit', '编辑', '', '', '', NULL, 0, 8, 1),
(@short_pid, 'button', 'live/shortItem/del', '删除', '', '', '', NULL, 0, 7, 1);

SELECT `id`, `pid`, `title`, `name`, `component`, `status` FROM `ba_admin_rule` WHERE `name` LIKE 'live/shortItem%';
SELECT COUNT(*) AS short_items FROM `lp_short_item`;
