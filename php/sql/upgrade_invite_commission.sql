-- ============================================
-- 邀请充值佣金（只做一级：好友充值 → 直接邀请人拿佣金）
--
--   1) lp_platform_config.invite_recharge_rate：全局返佣比例（0.1 = 10%，0 = 关闭）
--   2) lp_user.invite_recharge_rate：单个用户（博主/KOL）专属比例，空 = 用全局
--   3) lp_invite_commission：每笔充值佣金明细（order_id 唯一，保证同一订单只发一次）
--
-- 计算口径：佣金 = 充值实付 USD × 比例 ÷ diamond_to_usdt_rate（代币单价），
--          以代币入账，与站内代币/提现体系一致。
-- 本脚本幂等，可重复执行
-- 2026-10-31
-- ============================================

-- --------------------------------------------
-- 1. 全局返佣比例
-- --------------------------------------------
INSERT IGNORE INTO `lp_platform_config` (`key`, `value`, `remark`, `updated_at`) VALUES
  ('invite_recharge_rate', '0', '邀请充值返佣比例（0.1=10%，0=关闭）', NOW());

-- --------------------------------------------
-- 2. 单个用户专属返佣比例
-- --------------------------------------------
SET @tbl = 'lp_user';
SET @has = (SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
  WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = @tbl AND `COLUMN_NAME` = 'invite_recharge_rate');
SET @ddl = IF(@has = 0,
  'ALTER TABLE `lp_user` ADD COLUMN `invite_recharge_rate` DECIMAL(6,4) NULL DEFAULT NULL COMMENT ''专属充值返佣比例（0.1=10%，NULL=用全局）''',
  'SELECT ''lp_user.invite_recharge_rate 已存在，跳过'' AS msg');
PREPARE _stmt FROM @ddl;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;

-- --------------------------------------------
-- 3. 充值佣金明细
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS `lp_invite_commission` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `inviter_id` bigint(20) UNSIGNED NOT NULL COMMENT '邀请人 user_id（佣金获得者）',
  `invitee_id` bigint(20) UNSIGNED NOT NULL COMMENT '被邀请人 user_id（充值人）',
  `order_id` bigint(20) UNSIGNED NOT NULL COMMENT 'lp_recharge_order.id',
  `order_no` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '充值订单号',
  `pay_usd` decimal(12,2) NOT NULL DEFAULT 0 COMMENT '充值实付 USD',
  `rate` decimal(6,4) NOT NULL DEFAULT 0 COMMENT '返佣比例（0.1=10%）',
  `tokens` decimal(18,2) NOT NULL DEFAULT 0 COMMENT '发放代币',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_order`(`order_id` ASC) USING BTREE,
  INDEX `idx_inviter`(`inviter_id` ASC) USING BTREE,
  INDEX `idx_invitee`(`invitee_id` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '邀请充值佣金明细' ROW_FORMAT = Dynamic;

-- --------------------------------------------
-- 校验
-- --------------------------------------------
SELECT '--- invite_recharge_rate ---' AS section;
SELECT `value`, `remark` FROM `lp_platform_config` WHERE `key` = 'invite_recharge_rate';

SELECT '--- lp_user.invite_recharge_rate ---' AS section;
SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_COMMENT
  FROM `information_schema`.`COLUMNS`
 WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'lp_user' AND `COLUMN_NAME` = 'invite_recharge_rate';

SELECT '--- lp_invite_commission ---' AS section;
SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_COMMENT
  FROM `information_schema`.`COLUMNS`
 WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'lp_invite_commission'
 ORDER BY `ORDINAL_POSITION`;
