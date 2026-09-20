-- ============================================
-- 会员套餐对齐 candy /payments 参考页（taocan.html）
-- 1) lp_vip_plan 增加 tokens_month（每月 tokens 档位）并加唯一键防重
-- 2) 落 18 档真实套餐：2 层级 × 1/3/12 月 × 3 档 tokens
--    Premium      100 / 200 / 300 tokens/mo
--    Premium Plus 330 / 500 / 700 tokens/mo
-- 3) 旧的 6 档（tokens_month = 0）置为下架，保留历史订单引用
-- 执行：mysql -u 用户 -p 库名 < upgrade_vip_plans_v2.sql
-- ============================================

-- 1) tokens_month 字段
DROP PROCEDURE IF EXISTS `upgrade_vip_plan_tokens`;
DELIMITER //
CREATE PROCEDURE `upgrade_vip_plan_tokens`()
BEGIN
  DECLARE db_name VARCHAR(100);
  SET db_name = DATABASE();

  IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = db_name AND TABLE_NAME = 'lp_vip_plan' AND COLUMN_NAME = 'tokens_month') THEN
    ALTER TABLE `lp_vip_plan` ADD COLUMN `tokens_month` int(11) NOT NULL DEFAULT 0 COMMENT '每月 tokens 档位,0=旧档位' AFTER `months`;
  END IF;

  IF NOT EXISTS (SELECT * FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = db_name AND TABLE_NAME = 'lp_vip_plan' AND INDEX_NAME = 'uk_level_months_tokens') THEN
    ALTER TABLE `lp_vip_plan` ADD UNIQUE KEY `uk_level_months_tokens` (`level`, `months`, `tokens_month`);
  END IF;
END //
DELIMITER ;
CALL `upgrade_vip_plan_tokens`();
DROP PROCEDURE IF EXISTS `upgrade_vip_plan_tokens`;

-- 2) 18 档套餐（价格照参考页；usd_price = 该档实付总额）
--    diamond_price = usd_price × 100（沿用原有钻石定价比例）
INSERT IGNORE INTO `lp_vip_plan`
  (`name`, `level`, `months`, `tokens_month`, `usd_price`, `diamond_price`, `daily_diamond`,
   `benefits`, `chat_daily_limit`, `image_daily_limit`, `discount_percent`, `custom_video`, `sort`, `status`)
VALUES
-- ===== Premium（100 / 200 / 300 tokens/mo）=====
('Premium · 12 Months · 100 tokens/mo', 'premium', 12, 100, 47.88, 4788, 30,
 '["Create your own AI Girlfriend(s)","Generate 18+ Videos","Generate 18+ Images","Full Live Action Experience","Unlimited text messages","100 tokens per month"]',
 0, 3, 10, 1, 130, 1),
('Premium · 12 Months · 200 tokens/mo', 'premium', 12, 200, 119.88, 11988, 30,
 '["Create your own AI Girlfriend(s)","Generate 18+ Videos","Generate 18+ Images","Full Live Action Experience","Unlimited text messages","200 tokens per month"]',
 0, 3, 10, 1, 131, 1),
('Premium · 12 Months · 300 tokens/mo', 'premium', 12, 300, 191.88, 19188, 30,
 '["Create your own AI Girlfriend(s)","Generate 18+ Videos","Generate 18+ Images","Full Live Action Experience","Unlimited text messages","300 tokens per month"]',
 0, 3, 10, 1, 132, 1),

('Premium · 3 Months · 100 tokens/mo', 'premium', 3, 100, 26.97, 2697, 25,
 '["Create your own AI Girlfriend(s)","Generate 18+ Videos","Generate 18+ Images","Full Live Action Experience","Unlimited text messages","100 tokens per month"]',
 0, 3, 10, 1, 120, 1),
('Premium · 3 Months · 200 tokens/mo', 'premium', 3, 200, 44.97, 4497, 25,
 '["Create your own AI Girlfriend(s)","Generate 18+ Videos","Generate 18+ Images","Full Live Action Experience","Unlimited text messages","200 tokens per month"]',
 0, 3, 10, 1, 121, 1),
('Premium · 3 Months · 300 tokens/mo', 'premium', 3, 300, 62.97, 6297, 25,
 '["Create your own AI Girlfriend(s)","Generate 18+ Videos","Generate 18+ Images","Full Live Action Experience","Unlimited text messages","300 tokens per month"]',
 0, 3, 10, 1, 122, 1),

('Premium · 1 Month · 100 tokens/mo', 'premium', 1, 100, 13.99, 1399, 20,
 '["Create your own AI Girlfriend(s)","Generate 18+ Videos","Generate 18+ Images","Full Live Action Experience","Unlimited text messages","100 tokens per month"]',
 0, 3, 10, 1, 110, 1),
('Premium · 1 Month · 200 tokens/mo', 'premium', 1, 200, 19.99, 1999, 20,
 '["Create your own AI Girlfriend(s)","Generate 18+ Videos","Generate 18+ Images","Full Live Action Experience","Unlimited text messages","200 tokens per month"]',
 0, 3, 10, 1, 111, 1),
('Premium · 1 Month · 300 tokens/mo', 'premium', 1, 300, 25.99, 2599, 20,
 '["Create your own AI Girlfriend(s)","Generate 18+ Videos","Generate 18+ Images","Full Live Action Experience","Unlimited text messages","300 tokens per month"]',
 0, 3, 10, 1, 112, 1),

-- ===== Premium Plus（330 / 500 / 700 tokens/mo）=====
('Premium Plus · 12 Months · 330 tokens/mo', 'premium_plus', 12, 330, 215.99, 21599, 80,
 '["Everything in Premium","Generate 18+ Videos","Generate 18+ Images","Full Live Action Experience","Unlimited text messages","330 tokens per month"]',
 0, 10, 20, 1, 230, 1),
('Premium Plus · 12 Months · 500 tokens/mo', 'premium_plus', 12, 500, 335.99, 33599, 80,
 '["Everything in Premium","Generate 18+ Videos","Generate 18+ Images","Full Live Action Experience","Unlimited text messages","500 tokens per month"]',
 0, 10, 20, 1, 231, 1),
('Premium Plus · 12 Months · 700 tokens/mo', 'premium_plus', 12, 700, 455.99, 45599, 80,
 '["Everything in Premium","Generate 18+ Videos","Generate 18+ Images","Full Live Action Experience","Unlimited text messages","700 tokens per month"]',
 0, 10, 20, 1, 232, 1),

('Premium Plus · 3 Months · 330 tokens/mo', 'premium_plus', 3, 330, 116.99, 11699, 60,
 '["Everything in Premium","Generate 18+ Videos","Generate 18+ Images","Full Live Action Experience","Unlimited text messages","330 tokens per month"]',
 0, 10, 20, 1, 220, 1),
('Premium Plus · 3 Months · 500 tokens/mo', 'premium_plus', 3, 500, 146.99, 14699, 60,
 '["Everything in Premium","Generate 18+ Videos","Generate 18+ Images","Full Live Action Experience","Unlimited text messages","500 tokens per month"]',
 0, 10, 20, 1, 221, 1),
('Premium Plus · 3 Months · 700 tokens/mo', 'premium_plus', 3, 700, 176.99, 17699, 60,
 '["Everything in Premium","Generate 18+ Videos","Generate 18+ Images","Full Live Action Experience","Unlimited text messages","700 tokens per month"]',
 0, 10, 20, 1, 222, 1),

('Premium Plus · 1 Month · 330 tokens/mo', 'premium_plus', 1, 330, 59.99, 5999, 50,
 '["Everything in Premium","Generate 18+ Videos","Generate 18+ Images","Full Live Action Experience","Unlimited text messages","330 tokens per month"]',
 0, 10, 20, 1, 210, 1),
('Premium Plus · 1 Month · 500 tokens/mo', 'premium_plus', 1, 500, 69.99, 6999, 50,
 '["Everything in Premium","Generate 18+ Videos","Generate 18+ Images","Full Live Action Experience","Unlimited text messages","500 tokens per month"]',
 0, 10, 20, 1, 211, 1),
('Premium Plus · 1 Month · 700 tokens/mo', 'premium_plus', 1, 700, 79.99, 7999, 50,
 '["Everything in Premium","Generate 18+ Videos","Generate 18+ Images","Full Live Action Experience","Unlimited text messages","700 tokens per month"]',
 0, 10, 20, 1, 212, 1);

-- 3) 权益文案兜底写回：upgrade_vip_tier.sql 的存量回填会误伤同层级的 premium 行，
--    这里按 tokens 档位重写一次，保证重复执行也稳定
UPDATE `lp_vip_plan` SET `benefits` = CONCAT(
    '["Create your own AI Girlfriend(s)","Generate 18+ Videos","Generate 18+ Images","Full Live Action Experience","Unlimited text messages","',
    `tokens_month`, ' tokens per month"]')
WHERE `level` = 'premium' AND `tokens_month` > 0;

UPDATE `lp_vip_plan` SET `benefits` = CONCAT(
    '["Everything in Premium","Generate 18+ Videos","Generate 18+ Images","Full Live Action Experience","Unlimited text messages","',
    `tokens_month`, ' tokens per month"]')
WHERE `level` = 'premium_plus' AND `tokens_month` > 0;

-- 4) 旧 6 档下架（tokens_month = 0），保留 lp_vip_order 历史引用
UPDATE `lp_vip_plan` SET `status` = 0 WHERE `tokens_month` = 0;
