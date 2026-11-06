-- ============================================
-- AI 工作室设置：卡片定价 + 后台管理页
-- 执行：source php/sql/upgrade_short_preset_admin.sql   （库 zhibo / live_mysql）
--
-- 设计：
--   1) lp_short_preset 加 price_tokens：-1=跟随站点全局单价(video_gen_price/image_gen_price，
--      即现状默认 50/10)，0=该卡免费，>0=该卡固定代币价。默认 -1 保证各站现状不变。
--   2) 后台菜单「内容运营 → AI 工作室卡片」：列表 + 编辑（价格/上下架/权重），
--      生成参数（prompt/lora_chain/采样等）不开放编辑，防止误改破坏出片。
-- ============================================

-- 1) 价格字段
SET @has_col := (SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'lp_short_preset' AND column_name = 'price_tokens');
SET @sql := IF(@has_col = 0,
  'ALTER TABLE `lp_short_preset` ADD COLUMN `price_tokens` DECIMAL(10,2) NOT NULL DEFAULT -1 COMMENT ''代币价:-1跟随全局,0免费,>0卡片价'' AFTER `duration_sec`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2) 后台菜单「AI 工作室卡片」（挂在「内容运营」目录下）
SET @live_pid = IFNULL((SELECT `id` FROM `ba_admin_rule` WHERE `title` = '内容运营' AND `type` = 'menu_dir' ORDER BY `id` DESC LIMIT 1), 0);

INSERT IGNORE INTO `ba_admin_rule` (`pid`, `type`, `name`, `title`, `icon`, `path`, `component`, `menu_type`, `keepalive`, `weigh`, `status`) VALUES
(@live_pid, 'menu', 'live/shortPreset', 'AI 工作室卡片', 'fa fa-film', 'live/shortPreset', '/src/views/backend/live/shortPreset/index.vue', 'tab', 1, 94, 1);

-- 2.1) 幂等修正：历史版本曾把菜单挂到「直播运营」下，这里统一挪到「内容运营」
UPDATE `ba_admin_rule` r
JOIN (SELECT `id` FROM `ba_admin_rule` WHERE `title` = '内容运营' AND `type` = 'menu_dir' ORDER BY `id` DESC LIMIT 1) c
SET r.`pid` = c.`id`, r.`weigh` = 94
WHERE r.`name` = 'live/shortPreset' AND r.`type` = 'menu' AND r.`pid` <> c.`id`;

SET @sp_pid = (SELECT `id` FROM `ba_admin_rule` WHERE `name` = 'live/shortPreset' AND `type` = 'menu' LIMIT 1);

INSERT IGNORE INTO `ba_admin_rule` (`pid`, `type`, `name`, `title`, `icon`, `path`, `component`, `menu_type`, `keepalive`, `weigh`, `status`) VALUES
(@sp_pid, 'button', 'live/shortPreset/index', '查看', '', '', '', NULL, 0, 10, 1),
(@sp_pid, 'button', 'live/shortPreset/edit', '编辑', '', '', '', NULL, 0, 8, 1);

SELECT `id`, `pid`, `title`, `name`, `component`, `status` FROM `ba_admin_rule` WHERE `name` LIKE 'live/shortPreset%';
