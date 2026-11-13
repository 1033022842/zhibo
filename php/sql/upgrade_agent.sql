-- ============================================
-- 代理体系（独立代理门户 /agent + 总后台代理管理/代理数据）
-- 1) lp_agent            代理账号（独立于 ba_admin）
-- 2) lp_agent_domain     代理专属推广域名（代理在 /agent 自助添加，总部只读）
-- 3) lp_agent_login_log  代理登录日志
-- 4) lp_user             增加 reg_agent_id / reg_agent_name（注册归因快照）
-- 5) lp_promo_link       增加 agent_id（链接归属代理）
-- 6) lp_promo_pixel      增加 agent_id（渠道分配代理，0=平台）
-- 7) 后台菜单：代理管理 / 代理数据（挂在「营销推广」目录下）
--
-- 本脚本幂等，可重复执行。
-- 注意：代码已对未建表场景做降级（注册归因会跳过），但后台页面需要建表后才可用。
-- ============================================

-- --------------------------------------------
-- 1. 代理账号
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS `lp_agent` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `agent_no`      VARCHAR(32)  NOT NULL DEFAULT '' COMMENT '代理编号，如 A0001',
  `name`          VARCHAR(64)  NOT NULL DEFAULT '' COMMENT '代理名称',
  `username`      VARCHAR(64)  NOT NULL COMMENT '登录账号',
  `password_hash` VARCHAR(255) NOT NULL COMMENT '登录密码 hash',
  `contact`       VARCHAR(128) NOT NULL DEFAULT '' COMMENT '联系方式',
  `perms`         TEXT         NULL COMMENT '代理端权限 JSON 数组',
  `rate`          DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT '结算比例%，预留',
  `status`        TINYINT      NOT NULL DEFAULT 1 COMMENT '1启用 0停用',
  `last_login_at` DATETIME     NULL DEFAULT NULL,
  `last_login_ip` VARCHAR(64)  NOT NULL DEFAULT '',
  `remark`        VARCHAR(255) NOT NULL DEFAULT '',
  `created_at`    DATETIME     NULL DEFAULT NULL,
  `updated_at`    DATETIME     NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_agent_no` (`agent_no`),
  UNIQUE KEY `uk_username` (`username`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='代理账号';

-- --------------------------------------------
-- 2. 代理专属域名
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS `lp_agent_domain` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `agent_id`   INT UNSIGNED NOT NULL DEFAULT 0,
  `domain`     VARCHAR(120) NOT NULL DEFAULT '' COMMENT '专属推广域名（不含协议）',
  `is_primary` TINYINT      NOT NULL DEFAULT 0 COMMENT '1主域名',
  `status`     TINYINT      NOT NULL DEFAULT 1 COMMENT '1启用 0停用',
  `created_at` DATETIME     NULL DEFAULT NULL,
  `updated_at` DATETIME     NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_domain` (`domain`),
  KEY `idx_agent` (`agent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='代理专属推广域名';

-- --------------------------------------------
-- 3. 代理登录日志
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS `lp_agent_login_log` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `agent_id`   INT UNSIGNED NOT NULL DEFAULT 0,
  `username`   VARCHAR(64)  NOT NULL DEFAULT '',
  `ip`         VARCHAR(64)  NOT NULL DEFAULT '',
  `ua`         VARCHAR(255) NOT NULL DEFAULT '',
  `success`    TINYINT      NOT NULL DEFAULT 0,
  `created_at` DATETIME     NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_agent_time` (`agent_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='代理登录日志';

-- --------------------------------------------
-- 4. lp_user 归因字段
-- --------------------------------------------
SET @has_col = (SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
  WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'lp_user' AND `COLUMN_NAME` = 'reg_agent_id');
SET @ddl = IF(@has_col = 0,
  'ALTER TABLE `lp_user`
     ADD COLUMN `reg_agent_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''注册归属代理ID'',
     ADD COLUMN `reg_agent_name` VARCHAR(64) NOT NULL DEFAULT '''' COMMENT ''注册归属代理名称快照'',
     ADD INDEX `idx_reg_agent` (`reg_agent_id`)',
  'SELECT ''lp_user.reg_agent_id 已存在，跳过'' AS msg');
PREPARE _stmt FROM @ddl; EXECUTE _stmt; DEALLOCATE PREPARE _stmt;

-- --------------------------------------------
-- 5. lp_promo_link 归属代理
-- --------------------------------------------
SET @has_col = (SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
  WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'lp_promo_link' AND `COLUMN_NAME` = 'agent_id');
SET @ddl = IF(@has_col = 0,
  'ALTER TABLE `lp_promo_link`
     ADD COLUMN `agent_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''归属代理ID，0=平台'' ,
     ADD INDEX `idx_agent` (`agent_id`)',
  'SELECT ''lp_promo_link.agent_id 已存在，跳过'' AS msg');
PREPARE _stmt FROM @ddl; EXECUTE _stmt; DEALLOCATE PREPARE _stmt;

-- --------------------------------------------
-- 6. lp_promo_pixel 渠道分配代理
-- --------------------------------------------
SET @has_col = (SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
  WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'lp_promo_pixel' AND `COLUMN_NAME` = 'agent_id');
SET @ddl = IF(@has_col = 0,
  'ALTER TABLE `lp_promo_pixel`
     ADD COLUMN `agent_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''归属代理ID，0=平台'' ,
     ADD INDEX `idx_agent` (`agent_id`)',
  'SELECT ''lp_promo_pixel.agent_id 已存在，跳过'' AS msg');
PREPARE _stmt FROM @ddl; EXECUTE _stmt; DEALLOCATE PREPARE _stmt;

-- --------------------------------------------
-- 6.1 代理层级（总部 → 代理 → 子代理，可多级）
-- --------------------------------------------
SET @has_col = (SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
  WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'lp_agent' AND `COLUMN_NAME` = 'parent_id');
SET @ddl = IF(@has_col = 0,
  'ALTER TABLE `lp_agent`
     ADD COLUMN `parent_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''上级代理ID，0=总部直属'',
     ADD INDEX `idx_parent` (`parent_id`)',
  'SELECT ''lp_agent.parent_id 已存在，跳过'' AS msg');
PREPARE _stmt FROM @ddl; EXECUTE _stmt; DEALLOCATE PREPARE _stmt;

-- --------------------------------------------
-- 6.2 代理绑定总后台账号（复用 BuildAdmin 界面）
-- --------------------------------------------
SET @has_col = (SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
  WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'lp_agent' AND `COLUMN_NAME` = 'admin_id');
SET @ddl = IF(@has_col = 0,
  'ALTER TABLE `lp_agent`
     ADD COLUMN `admin_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''绑定的后台管理员ID'',
     ADD COLUMN `admin_group_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''代理专属后台角色组ID'',
     ADD COLUMN `admin_rules` TEXT NULL COMMENT ''代理后台菜单规则ID JSON''',
  'SELECT ''lp_agent.admin_id 已存在，跳过'' AS msg');
PREPARE _stmt FROM @ddl; EXECUTE _stmt; DEALLOCATE PREPARE _stmt;

-- --------------------------------------------
-- 6.3 代理独立客服：客服渠道加归属代理（0=总部全局）
-- --------------------------------------------
SET @has_col = (SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
  WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'lp_cs_channel' AND `COLUMN_NAME` = 'agent_id');
SET @ddl = IF(@has_col = 0,
  'ALTER TABLE `lp_cs_channel`
     ADD COLUMN `agent_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''归属代理ID，0=总部全局'',
     ADD INDEX `idx_agent` (`agent_id`)',
  'SELECT ''lp_cs_channel.agent_id 已存在，跳过'' AS msg');
PREPARE _stmt FROM @ddl; EXECUTE _stmt; DEALLOCATE PREPARE _stmt;

-- --------------------------------------------
-- 7. 历史数据回填（按推广链接名称 / 来源域名反推，近似归属）
-- --------------------------------------------
-- 注意：各站 lp_user 的来源字段可能是 utf8mb4_0900_ai_ci，而推广表是 utf8mb4_unicode_ci，
-- 直接 JOIN 会报 Illegal mix of collations，这里统一强制成 utf8mb4_unicode_ci。
UPDATE `lp_user` u
JOIN `lp_promo_link` l
  ON l.`name` COLLATE utf8mb4_unicode_ci = u.`reg_link_name` COLLATE utf8mb4_unicode_ci
 AND l.`agent_id` > 0
SET u.`reg_agent_id` = l.`agent_id`
WHERE u.`reg_agent_id` = 0;

UPDATE `lp_user` u
JOIN `lp_agent_domain` d
  ON d.`domain` COLLATE utf8mb4_unicode_ci = u.`reg_domain` COLLATE utf8mb4_unicode_ci
SET u.`reg_agent_id` = d.`agent_id`
WHERE u.`reg_agent_id` = 0 AND d.`status` = 1;

UPDATE `lp_user` u
JOIN `lp_agent` a ON a.`id` = u.`reg_agent_id`
SET u.`reg_agent_name` = a.`name`
WHERE u.`reg_agent_id` > 0 AND u.`reg_agent_name` = '';

-- --------------------------------------------
-- 8. 后台菜单
-- --------------------------------------------
SET @mkt_pid = IFNULL((SELECT `id` FROM `ba_admin_rule` WHERE `name` = 'marketing' AND `type` = 'menu_dir' ORDER BY `id` DESC LIMIT 1), 0);

INSERT INTO `ba_admin_rule` (`pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`, `component`, `keepalive`, `extend`, `remark`, `weigh`, `status`)
SELECT @mkt_pid, 'menu', '代理管理', 'live/agent', 'live/agent', 'fa fa-users', 'iframe', '/admin/live.Agent/index', '', 0, 'none', '创建代理账号、分配专属域名与渠道、勾选代理端权限', 94, 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `ba_admin_rule` WHERE `name` = 'live/agent');

INSERT INTO `ba_admin_rule` (`pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`, `component`, `keepalive`, `extend`, `remark`, `weigh`, `status`)
SELECT @mkt_pid, 'menu', '代理数据', 'live/agentReport', 'live/agentReport', 'fa fa-bar-chart', 'iframe', '/admin/live.AgentReport/index', '', 0, 'none', '按代理维度统计注册、充值、订阅与转化', 95, 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `ba_admin_rule` WHERE `name` = 'live/agentReport');

-- --------------------------------------------
-- 9. 校验
-- --------------------------------------------
SHOW TABLES LIKE 'lp_agent';
SHOW TABLES LIKE 'lp_agent_domain';
SELECT `COLUMN_NAME`, `COLUMN_TYPE`, `COLUMN_COMMENT` FROM `information_schema`.`COLUMNS`
WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'lp_user'
  AND `COLUMN_NAME` IN ('reg_agent_id', 'reg_agent_name');
SELECT `id`, `pid`, `title`, `name`, `url`, `status` FROM `ba_admin_rule` WHERE `name` IN ('live/agent', 'live/agentReport');
