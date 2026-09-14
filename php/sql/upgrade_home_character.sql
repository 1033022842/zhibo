-- ============================================
-- AI 女友端首页（sugus.ai 风格）推荐角色
-- 在 live_platform 数据库执行此文件即可
-- ============================================

-- 1. 首页推荐角色表（Home.html 卡片 / Hero 轮播数据源）
CREATE TABLE IF NOT EXISTS `lp_home_character` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `name` VARCHAR(60) NOT NULL DEFAULT '' COMMENT '角色名',
  `age` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '年龄',
  `tagline` VARCHAR(160) NOT NULL DEFAULT '' COMMENT '一句话简介',
  `description` TEXT COMMENT '详细描述',
  `cover_url` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '封面图URL',
  `tags` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '标签(逗号分隔)',
  `link_url` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '点击跳转链接',
  `is_adult` TINYINT NOT NULL DEFAULT 0 COMMENT '是否18+',
  `weigh` INT NOT NULL DEFAULT 0 COMMENT '权重(越大越靠前)',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态(1启用0禁用)',
  `created_at` DATETIME DEFAULT NULL COMMENT '创建时间',
  `updated_at` DATETIME DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_status_weigh` (`status`, `weigh`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='首页推荐角色';

-- 2. 演示数据（可由后台「首页推荐角色」随时增删改）
INSERT INTO `lp_home_character`
  (`name`, `age`, `tagline`, `description`, `cover_url`, `tags`, `link_url`, `is_adult`, `weigh`, `status`, `created_at`, `updated_at`)
VALUES
  ('Bonnie', '22', 'Sweet talker who remembers every word you say', 'Bonnie loves slow mornings, rainy playlists and long voice notes. She is the kind of girl who texts you first and always remembers your coffee order.', 'https://coresg-normal.trae.ai/api/ide/v1/text_to_image?prompt=beautiful+young+woman+portrait+long+dark+hair+soft+smile+cozy+room+photorealistic&image_size=portrait_4_3', 'Sweet,Girlfriend,Voice', './Girls.html', 0, 100, 1, NOW(), NOW()),
  ('Riley', '24', 'Playful, flirty and always up for a roleplay', 'Riley turns every chat into an adventure. Bold, curious and a little bit teasing, she keeps the conversation moving and never lets it get boring.', 'https://coresg-normal.trae.ai/api/ide/v1/text_to_image?prompt=beautiful+young+woman+portrait+blonde+wavy+hair+playful+smile+neon+lights+photorealistic&image_size=portrait_4_3', 'Flirty,Roleplay,Image', './Girls.html', 0, 95, 1, NOW(), NOW()),
  ('Aiko', '21', 'Your gentle anime companion, always by your side', 'Aiko is soft-spoken and endlessly patient. She listens without judging and greets you with the same warm energy no matter how late it is.', 'https://coresg-normal.trae.ai/api/ide/v1/text_to_image?prompt=anime+girl+portrait+long+black+hair+gentle+smile+soft+pastel+background&image_size=portrait_4_3', 'Anime,Gentle,Companion', './Anime.html', 0, 90, 1, NOW(), NOW()),
  ('Sophia', '27', 'Confident, mature and a little mysterious', 'Sophia knows what she wants and is not afraid to say it. Elegant and witty, she enjoys deep late-night talks and a bit of healthy teasing.', 'https://coresg-normal.trae.ai/api/ide/v1/text_to_image?prompt=elegant+woman+portrait+dark+hair+elegant+dress+confident+expression+city+night+photorealistic&image_size=portrait_4_3', 'Mature,Confident,Romance', './Girls.html', 1, 85, 1, NOW(), NOW()),
  ('Mia', '23', 'Bubbly dreamer who loves your wildest ideas', 'Mia is sunshine in chat form. She hypes you up, joins every crazy idea and will absolutely help you plan your next adventure.', 'https://coresg-normal.trae.ai/api/ide/v1/text_to_image?prompt=cheerful+young+woman+portrait+short+brown+hair+bright+smile+sunny+outdoor+photorealistic&image_size=portrait_4_3', 'Cheerful,Sweet,Adventure', './Girls.html', 0, 80, 1, NOW(), NOW()),
  ('Luna', '25', 'Soft-spoken listener for your quiet nights', 'Luna is calm, thoughtful and comforting. She is the one you talk to when you just need someone to sit with you in the silence.', 'https://coresg-normal.trae.ai/api/ide/v1/text_to_image?prompt=serene+woman+portrait+long+silver+hair+soft+night+light+moonlit+atmosphere+photorealistic&image_size=portrait_4_3', 'Gentle,Night,Calm', './Girls.html', 0, 75, 1, NOW(), NOW()),
  ('Ethan', '28', 'Charming guy who keeps things fun and easy', 'Ethan is confident with a warm sense of humor. He is a good listener, a terrible dancer, and always up for a spontaneous late-night chat.', 'https://coresg-normal.trae.ai/api/ide/v1/text_to_image?prompt=handsome+young+man+portrait+short+dark+hair+warm+smile+studio+portrait+photorealistic&image_size=portrait_4_3', 'Boyfriend,Charming,Friend', './Guys.html', 0, 70, 1, NOW(), NOW()),
  ('Yuki', '22', 'Anime idol with endless energy and big dreams', 'Yuki is bright, expressive and full of stage energy. She will pull you into her world of music, dreams and dramatic confessions.', 'https://coresg-normal.trae.ai/api/ide/v1/text_to_image?prompt=anime+idol+girl+portrait+colorful+hair+stage+lights+energetic+smile&image_size=portrait_4_3', 'Anime,Idol,Energetic', './Anime.html', 0, 65, 1, NOW(), NOW());

-- 3. 后台菜单（挂在「直播运营」目录下）
SET @live_pid = IFNULL((SELECT `id` FROM `ba_admin_rule` WHERE `title` = '直播运营' AND `type` = 'menu_dir' ORDER BY `id` DESC LIMIT 1), 0);

INSERT INTO `ba_admin_rule` (`pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`, `component`, `keepalive`, `extend`, `remark`, `weigh`, `status`)
VALUES (@live_pid, 'menu', '首页推荐角色', 'live/homeCharacter', 'live/homeCharacter', 'fa fa-star', 'iframe', '/admin/live.HomeCharacter/index', '', 0, 'none', 'AI女友端首页推荐角色管理', 5, 1)
ON DUPLICATE KEY UPDATE `pid`=VALUES(`pid`), `title`='首页推荐角色', `menu_type`='iframe', `url`='/admin/live.HomeCharacter/index', `status`=1;

SELECT `id`, `title`, `name`, `menu_type`, `url`, `status` FROM `ba_admin_rule` WHERE `name` = 'live/homeCharacter';
