-- ============================================
-- 用户人格设定（设置页「Your personas」弹窗）
-- 一个用户一条记录（uk_user），聊天时由 Live::chat() 读取并注入 system 提示词
-- 本脚本幂等，可重复执行
-- ============================================

CREATE TABLE IF NOT EXISTS `lp_user_persona` (
  `id`            bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `user_id`       bigint(20) UNSIGNED NOT NULL COMMENT '用户ID',
  `name`          varchar(32)  NOT NULL DEFAULT '' COMMENT '称呼',
  `age_bucket`    varchar(16)  NOT NULL DEFAULT '' COMMENT '年龄',
  `gender`        varchar(32)  NOT NULL DEFAULT '' COMMENT '性别 Man/Woman/Non-binary',
  `location`      varchar(64)  NOT NULL DEFAULT '' COMMENT '所在地',
  `occupation`    varchar(64)  NOT NULL DEFAULT '' COMMENT '职业',
  `height_cm`     smallint(5) UNSIGNED NOT NULL DEFAULT 0 COMMENT '身高(cm)',
  `body_type`     varchar(32)  NOT NULL DEFAULT '' COMMENT '体型',
  `ethnicity`     varchar(64)  NOT NULL DEFAULT '' COMMENT '种族(Premium+)',
  `hair_color`    varchar(32)  NOT NULL DEFAULT '' COMMENT '发色',
  `hair_length`   varchar(32)  NOT NULL DEFAULT '' COMMENT '发型',
  `genders_liked` varchar(255) NOT NULL DEFAULT '' COMMENT '喜欢的性别，JSON数组',
  `looking_for`   varchar(512) NOT NULL DEFAULT '' COMMENT '期望关系，JSON数组',
  `hobby_types`   varchar(512) NOT NULL DEFAULT '' COMMENT '兴趣爱好，JSON数组',
  `active`        tinyint(1)   NOT NULL DEFAULT 1 COMMENT '是否启用 1=启用',
  `created_at`    datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at`    datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_user`(`user_id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '用户人格设定（Your personas）' ROW_FORMAT = Dynamic;

-- --------------------------------------------
-- 校验
-- --------------------------------------------
SELECT '--- lp_user_persona 字段 ---' AS section;
SELECT COLUMN_NAME, COLUMN_TYPE, COLUMN_COMMENT
  FROM `information_schema`.`COLUMNS`
 WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'lp_user_persona'
 ORDER BY `ORDINAL_POSITION`;
SELECT '--- 索引 ---' AS section;
SHOW INDEX FROM `lp_user_persona`;
