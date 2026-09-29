-- ============================================
-- 邀请好友（Affiliate Program / Inviter 页）完整闭环
--
--   1) lp_user 增加「我的邀请码」：自定义短码（3-25 位小写字母 / 数字 / 连字符），懒生成
--   2) 新增 lp_invite_record：好友注册时绑定邀请关系；好友成为高级会员时双方各得代币
--
-- 规则：邀请链接 https://<站点>/?code=<邀请码> → 好友注册时落库绑定 →
--      好友开通/成为高级会员（VIP 生效）时，双方各得 invite_reward_tokens 个代币（默认 100），每次邀请只发一次
-- 本脚本幂等，可重复执行
-- 2026-09-29
-- ============================================

-- --------------------------------------------
-- 1. lp_user.invite_code
-- --------------------------------------------
SET @tbl = 'lp_user';
SET @has = (SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
  WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = @tbl AND `COLUMN_NAME` = 'invite_code');
SET @ddl = IF(@has = 0,
  'ALTER TABLE `lp_user` ADD COLUMN `invite_code` VARCHAR(25) NULL DEFAULT NULL COMMENT ''我的邀请码（3-25 位小写字母/数字/连字符）''',
  'SELECT ''lp_user.invite_code 已存在，跳过'' AS msg');
PREPARE _stmt FROM @ddl;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;

SET @has = (SELECT COUNT(*) FROM `information_schema`.`STATISTICS`
  WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = @tbl AND `INDEX_NAME` = 'uk_invite_code');
SET @ddl = IF(@has = 0,
  'ALTER TABLE `lp_user` ADD UNIQUE INDEX `uk_invite_code` (`invite_code`)',
  'SELECT ''uk_invite_code 已存在，跳过'' AS msg');
PREPARE _stmt FROM @ddl;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;

-- --------------------------------------------
-- 2. lp_invite_record：邀请关系与奖励记录
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS `lp_invite_record` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `inviter_id` bigint(20) UNSIGNED NOT NULL COMMENT '邀请人 user_id',
  `invitee_id` bigint(20) UNSIGNED NOT NULL COMMENT '被邀请人 user_id',
  `invite_code` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '注册时使用的邀请码',
  `registered_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '注册（绑定）时间',
  `premium_at` datetime NULL DEFAULT NULL COMMENT '首次成为高级会员时间',
  `reward_tokens` decimal(18,2) NOT NULL DEFAULT 0 COMMENT '单边已发放代币（双方各这么多）',
  `reward_at` datetime NULL DEFAULT NULL COMMENT '发奖时间（为空=未发）',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_invitee`(`invitee_id` ASC) USING BTREE,
  INDEX `idx_inviter`(`inviter_id` ASC) USING BTREE,
  INDEX `idx_reward_at`(`reward_at` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '邀请关系与奖励记录' ROW_FORMAT = Dynamic;

-- --------------------------------------------
-- 校验
-- --------------------------------------------
SELECT '--- lp_user.invite_code ---' AS section;
SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_COMMENT
  FROM `information_schema`.`COLUMNS`
 WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'lp_user' AND `COLUMN_NAME` = 'invite_code';

SELECT '--- lp_invite_record ---' AS section;
SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_COMMENT
  FROM `information_schema`.`COLUMNS`
 WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'lp_invite_record'
 ORDER BY `ORDINAL_POSITION`;
