-- ============================================================
-- AI 生成额度：每天补充 N 次，余额封顶
-- 2026-10-18
--
-- 变更点：
--   1) 新增 lp_user_gen_credit：记录每个用户的生成次数余额
--   2) lp_vip_plan 增加 image_daily_refill：每天补充次数（默认 1）
--
-- 幂等：可重复执行。
-- 说明：旧逻辑是「每天 0 点把已用次数清零 = 恢复满额」，
--       新逻辑是「每天 +1，最多累积到上限」。首次访问时按
--       「上限 - 当天已用」初始化，保证灰度当天行为不变。
-- ============================================================

-- ---- 1. 生成额度余额表 ----
CREATE TABLE IF NOT EXISTS `lp_user_gen_credit` (
    `user_id`          BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `balance`          INT NOT NULL DEFAULT 0 COMMENT '当前剩余生成次数',
    `last_refill_date` DATE NOT NULL COMMENT '上次补充日期',
    `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='AI生成额度余额（每天补充，上限封顶）';

-- ---- 2. VIP 套餐增加「每天补充次数」----
DROP PROCEDURE IF EXISTS `_add_gen_refill_col`;
DELIMITER $$
CREATE PROCEDURE `_add_gen_refill_col`()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lp_vip_plan'
                     AND COLUMN_NAME = 'image_daily_refill') THEN
        ALTER TABLE `lp_vip_plan`
            ADD COLUMN `image_daily_refill` INT NOT NULL DEFAULT 1
            COMMENT '每天补充的生成次数（余额封顶=image_daily_limit）' AFTER `image_daily_limit`;
    END IF;
END$$
DELIMITER ;
CALL `_add_gen_refill_col`();
DROP PROCEDURE IF EXISTS `_add_gen_refill_col`;

-- ---- 3. 存量套餐按 1 次/天补齐（已有列默认值即是 1，这里只兜底 NULL）----
UPDATE `lp_vip_plan` SET `image_daily_refill` = 1 WHERE `image_daily_refill` IS NULL;

SELECT 'gen credit upgrade done' AS result;
