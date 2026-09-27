-- ============================================
-- 用户偏好设置表：给设置页的「Global chat customization」等落库
-- 现有 setting_key：
--   chat_mode     聊天模式  default-1 / balanced-v69-1 / thoughtful-26b-1 / powerful-26blong-1
--   reply_length  回复长度  0 很短 / 1 短 / 2 中 / 3 长 / 4 超长(Premium Plus)
--   narration     动作旁白  0 关 / 1 开
--   timezone      时区      off 或 tz 标识符（如 Asia/Shanghai），时区感知为 Premium Plus
-- 本脚本幂等，可重复执行
-- ============================================

CREATE TABLE IF NOT EXISTS `lp_user_setting` (
  `id`            bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `user_id`       bigint(20) UNSIGNED NOT NULL COMMENT '用户ID',
  `setting_key`   varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '设置项',
  `setting_value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '设置值',
  `created_at`    datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at`    datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_user_key`(`user_id`, `setting_key`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '用户偏好设置（聊天定制等）' ROW_FORMAT = Dynamic;

-- --------------------------------------------
-- 校验
-- --------------------------------------------
SELECT '--- lp_user_setting 字段 ---' AS `--`;
SELECT COLUMN_NAME, COLUMN_TYPE, COLUMN_COMMENT FROM `information_schema`.`COLUMNS`
  WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'lp_user_setting';
SELECT '--- 索引 ---' AS `--`;
SHOW INDEX FROM `lp_user_setting`;
