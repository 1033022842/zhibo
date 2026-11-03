-- ============================================
-- Kelymi 中国市场人民币定价（2026-11，按《Kelymi会员与Token定价方案》）
--
-- 计价基准：¥1 = 10 Token（1 Token = ¥0.10）
-- 会员：VIP ¥9.9/月（100 Token/月）、SVIP ¥29.9/月（1,000 Token/月）
-- Token 充值：¥3/30、¥6/60、¥12/130、¥30/350、¥68/850、¥128/1,700
--
-- 说明：
--   1) 站内 usd_price 仍作为「收款数值」使用；CNY 法币通道汇率=1，
--      因此 usd_price=9.90 即实付 ¥9.90（前台暂时仍显示 $ 符号）。
--   2) diamond_price 按 ¥1=10 Token 折算（会员用 Token 支付时的价格）。
--   3) 新套餐 daily_diamond=0，会员 Token 由 VipService::activate 按
--      tokens_month × months 在订阅时一次性发放（每订阅周期发一次）。
--   4) 旧 Premium / Premium Plus 档位全部下架，保留历史订单引用。
--
-- 本脚本幂等，可重复执行。
-- ============================================

-- ---------- 1) 下架旧会员档位 ----------
UPDATE `lp_vip_plan` SET `status` = 0 WHERE `status` = 1;

-- ---------- 2) VIP：复用 premium × 100 tokens/mo 三档 ----------
UPDATE `lp_vip_plan` SET
  `name`               = 'VIP · 1 Month · 100 tokens/mo',
  `usd_price`          = 9.90,
  `diamond_price`      = 99,
  `daily_diamond`      = 0,
  `benefits`           = '["Create your own AI Girlfriend(s)","Generate 18+ Videos","Generate 18+ Images","Full Live Action Experience","Unlimited text messages","100 tokens every month"]',
  `chat_daily_limit`   = 0,
  `image_daily_limit`  = 5,
  `image_daily_refill` = 5,
  `discount_percent`   = 10,
  `custom_video`       = 1,
  `sort`               = 110,
  `status`             = 1
WHERE `level` = 'premium' AND `months` = 1 AND `tokens_month` = 100;

UPDATE `lp_vip_plan` SET
  `name`               = 'VIP · 3 Months · 100 tokens/mo',
  `usd_price`          = 29.70,
  `diamond_price`      = 297,
  `daily_diamond`      = 0,
  `benefits`           = '["Create your own AI Girlfriend(s)","Generate 18+ Videos","Generate 18+ Images","Full Live Action Experience","Unlimited text messages","100 tokens every month"]',
  `chat_daily_limit`   = 0,
  `image_daily_limit`  = 5,
  `image_daily_refill` = 5,
  `discount_percent`   = 10,
  `custom_video`       = 1,
  `sort`               = 120,
  `status`             = 1
WHERE `level` = 'premium' AND `months` = 3 AND `tokens_month` = 100;

UPDATE `lp_vip_plan` SET
  `name`               = 'VIP · 12 Months · 100 tokens/mo',
  `usd_price`          = 118.80,
  `diamond_price`      = 1188,
  `daily_diamond`      = 0,
  `benefits`           = '["Create your own AI Girlfriend(s)","Generate 18+ Videos","Generate 18+ Images","Full Live Action Experience","Unlimited text messages","100 tokens every month"]',
  `chat_daily_limit`   = 0,
  `image_daily_limit`  = 5,
  `image_daily_refill` = 5,
  `discount_percent`   = 10,
  `custom_video`       = 1,
  `sort`               = 130,
  `status`             = 1
WHERE `level` = 'premium' AND `months` = 12 AND `tokens_month` = 100;

-- ---------- 3) SVIP：新增 premium_plus × 1000 tokens/mo 三档 ----------
INSERT INTO `lp_vip_plan`
  (`name`, `level`, `months`, `tokens_month`, `usd_price`, `diamond_price`, `daily_diamond`,
   `benefits`, `chat_daily_limit`, `image_daily_limit`, `image_daily_refill`,
   `discount_percent`, `custom_video`, `sort`, `status`)
VALUES
  ('SVIP · 1 Month · 1000 tokens/mo', 'premium_plus', 1, 1000, 29.90, 299, 0,
   '["Everything in VIP","Generate 18+ Videos","Generate 18+ Images","Full Live Action Experience","Unlimited text messages","1,000 tokens every month"]',
   0, 10, 10, 20, 1, 210, 1),
  ('SVIP · 3 Months · 1000 tokens/mo', 'premium_plus', 3, 1000, 89.70, 897, 0,
   '["Everything in VIP","Generate 18+ Videos","Generate 18+ Images","Full Live Action Experience","Unlimited text messages","1,000 tokens every month"]',
   0, 10, 10, 20, 1, 220, 1),
  ('SVIP · 12 Months · 1000 tokens/mo', 'premium_plus', 12, 1000, 358.80, 3588, 0,
   '["Everything in VIP","Generate 18+ Videos","Generate 18+ Images","Full Live Action Experience","Unlimited text messages","1,000 tokens every month"]',
   0, 10, 10, 20, 1, 230, 1)
ON DUPLICATE KEY UPDATE
  `name`               = VALUES(`name`),
  `usd_price`          = VALUES(`usd_price`),
  `diamond_price`      = VALUES(`diamond_price`),
  `daily_diamond`      = VALUES(`daily_diamond`),
  `benefits`           = VALUES(`benefits`),
  `chat_daily_limit`   = VALUES(`chat_daily_limit`),
  `image_daily_limit`  = VALUES(`image_daily_limit`),
  `image_daily_refill` = VALUES(`image_daily_refill`),
  `discount_percent`   = VALUES(`discount_percent`),
  `custom_video`       = VALUES(`custom_video`),
  `sort`               = VALUES(`sort`),
  `status`             = 1;

-- ---------- 4) Token 充值档位（下架旧档，落 6 档新价） ----------
UPDATE `lp_recharge_package` SET `status` = 0 WHERE `status` = 1;

INSERT INTO `lp_recharge_package` (`tokens`, `price`, `bonus_percent`, `sort`, `status`)
SELECT 30, 3.00, 0, 1, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `lp_recharge_package` WHERE `tokens` = 30 AND `price` = 3.00 AND `status` = 1);

INSERT INTO `lp_recharge_package` (`tokens`, `price`, `bonus_percent`, `sort`, `status`)
SELECT 60, 6.00, 0, 2, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `lp_recharge_package` WHERE `tokens` = 60 AND `price` = 6.00 AND `status` = 1);

INSERT INTO `lp_recharge_package` (`tokens`, `price`, `bonus_percent`, `sort`, `status`)
SELECT 130, 12.00, 0, 3, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `lp_recharge_package` WHERE `tokens` = 130 AND `price` = 12.00 AND `status` = 1);

INSERT INTO `lp_recharge_package` (`tokens`, `price`, `bonus_percent`, `sort`, `status`)
SELECT 350, 30.00, 0, 4, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `lp_recharge_package` WHERE `tokens` = 350 AND `price` = 30.00 AND `status` = 1);

INSERT INTO `lp_recharge_package` (`tokens`, `price`, `bonus_percent`, `sort`, `status`)
SELECT 850, 68.00, 0, 5, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `lp_recharge_package` WHERE `tokens` = 850 AND `price` = 68.00 AND `status` = 1);

INSERT INTO `lp_recharge_package` (`tokens`, `price`, `bonus_percent`, `sort`, `status`)
SELECT 1700, 128.00, 0, 6, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `lp_recharge_package` WHERE `tokens` = 1700 AND `price` = 128.00 AND `status` = 1);

-- ---------- 5) 功能消耗（全局可配项） ----------
-- 普通文字聊天 0（保留现有免费额度/会员不限）
UPDATE `lp_platform_config` SET `value` = '0' WHERE `key` = 'chat_overage_price';
INSERT INTO `lp_platform_config` (`key`, `value`, `remark`)
SELECT 'chat_overage_price', '0', '免费额度用尽后单条聊天价格（0=不能续聊）' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `lp_platform_config` WHERE `key` = 'chat_overage_price');

-- AI Phone Call / Live Audio 3 Token/分钟
UPDATE `lp_platform_config` SET `value` = '3' WHERE `key` = 'call_price_per_minute';
INSERT INTO `lp_platform_config` (`key`, `value`, `remark`)
SELECT 'call_price_per_minute', '3', '语音通话每分钟价格（Token）' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `lp_platform_config` WHERE `key` = 'call_price_per_minute');

-- 普通图片 3 Token/张（高清档位暂沿用同一价格）
UPDATE `lp_platform_config` SET `value` = '3' WHERE `key` = 'image_gen_price';
INSERT INTO `lp_platform_config` (`key`, `value`, `remark`)
SELECT 'image_gen_price', '3', 'AI 图片生成每次扣 Token' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `lp_platform_config` WHERE `key` = 'image_gen_price');

-- AI 视频 30 Token/条（文档建议区间 30~50，先用下限，后续后台可调）
UPDATE `lp_platform_config` SET `value` = '30' WHERE `key` = 'video_gen_price';
INSERT INTO `lp_platform_config` (`key`, `value`, `remark`)
SELECT 'video_gen_price', '30', 'AI 视频生成每次扣 Token' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `lp_platform_config` WHERE `key` = 'video_gen_price');

-- ---------- 6) 人民币通道：启用微信，支付宝待网关开通后手动启用 ----------
UPDATE `lp_fiat_channel` SET
  `name` = 'WeChat Pay', `status` = 1,
  `currency` = 'CNY', `currency_symbol` = '¥',
  `usd_rate` = 1.000000, `decimals` = 2, `min_amount` = 0.01, `diamond_rate` = 10
WHERE `gateway` = 'sjhsll' AND `gateway_ref` = '1';

UPDATE `lp_fiat_channel` SET
  `name` = 'Alipay', `status` = 0,
  `currency` = 'CNY', `currency_symbol` = '¥',
  `usd_rate` = 1.000000, `decimals` = 2, `min_amount` = 0.01, `diamond_rate` = 10
WHERE `gateway` = 'sjhsll' AND `gateway_ref` = '2';
