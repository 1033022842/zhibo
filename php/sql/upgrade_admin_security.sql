-- ============================================================
-- 后台安全增强：IP 白名单 + 谷歌验证码(2FA)
-- 2026-10-16
--
-- 安全性：本脚本只新增（不改不删）。白名单开关默认 '0'（关闭），
--         因此执行后立即部署 PHP 不会锁死后台。
-- 幂等：全部使用 IF NOT EXISTS / WHERE NOT EXISTS，可重复执行。
-- ============================================================

-- ---- 1. IP 白名单表（应用层拦截 /admin，支持单 IP 与 CIDR）----
CREATE TABLE IF NOT EXISTS `lp_admin_ip_whitelist` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ip`         VARCHAR(64)  NOT NULL DEFAULT '' COMMENT '单个IP或CIDR网段',
    `remark`     VARCHAR(120) NOT NULL DEFAULT '' COMMENT '备注',
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_ip` (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='后台IP白名单';

-- ---- 2. 白名单总开关（默认关闭，避免锁死）----
INSERT INTO `lp_platform_config` (`key`, `value`, `remark`)
SELECT 'admin_ip_whitelist_enabled', '0', '后台IP白名单总开关:1开 0关（默认关）'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `lp_platform_config` WHERE `key` = 'admin_ip_whitelist_enabled');

-- ---- 3. 2FA 启用开关（默认关闭；开启后仅对已绑定 totp 的账号生效）----
INSERT INTO `lp_platform_config` (`key`, `value`, `remark`)
SELECT 'admin_totp_enabled', '0', '后台谷歌验证码(2FA)总开关:1开 0关（默认关）'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `lp_platform_config` WHERE `key` = 'admin_totp_enabled');

-- ---- 4. ba_admin 增加 TOTP 字段（仅 admin 账号启用）----
-- MySQL 8 不支持 ADD COLUMN IF NOT EXISTS，用存储过程做幂等
DROP PROCEDURE IF EXISTS `_add_totp_cols`;
DELIMITER $$
CREATE PROCEDURE `_add_totp_cols`()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ba_admin' AND COLUMN_NAME = 'totp_secret') THEN
        ALTER TABLE `ba_admin` ADD COLUMN `totp_secret` VARCHAR(64) NOT NULL DEFAULT '' COMMENT 'TOTP密钥(base32)' AFTER `salt`;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ba_admin' AND COLUMN_NAME = 'totp_enabled') THEN
        ALTER TABLE `ba_admin` ADD COLUMN `totp_enabled` TINYINT NOT NULL DEFAULT 0 COMMENT '是否启用2FA:1是 0否' AFTER `totp_secret`;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ba_admin' AND COLUMN_NAME = 'totp_recovery') THEN
        ALTER TABLE `ba_admin` ADD COLUMN `totp_recovery` TEXT NULL COMMENT '一次性恢复码(哈希后JSON数组)' AFTER `totp_enabled`;
    END IF;
END$$
DELIMITER ;
CALL `_add_totp_cols`();
DROP PROCEDURE IF EXISTS `_add_totp_cols`;

-- ---- 5. 后台菜单：访问控制（IP白名单）----
-- 归属父级优先「数据安全管理」，其次「安全管理」→「直播运营」→ live 目录
SET @sec_pid = IFNULL((SELECT `id` FROM `ba_admin_rule` WHERE `title` = '数据安全管理' AND `type` = 'menu_dir' ORDER BY `id` DESC LIMIT 1),
              IFNULL((SELECT `id` FROM `ba_admin_rule` WHERE `title` = '安全管理' AND `type` = 'menu_dir' ORDER BY `id` DESC LIMIT 1),
              IFNULL((SELECT `id` FROM `ba_admin_rule` WHERE `title` = '直播运营' AND `type` = 'menu_dir' ORDER BY `id` DESC LIMIT 1),
              IFNULL((SELECT `id` FROM `ba_admin_rule` WHERE `name` = 'live' AND `type` = 'menu_dir' ORDER BY `id` DESC LIMIT 1), 0))));

-- 目标父级下现有子项的最大权重，两个菜单排在其后
SET @sec_maxw = IFNULL((SELECT MAX(`weigh`) FROM `ba_admin_rule` WHERE `pid` = @sec_pid), 0);

INSERT INTO `ba_admin_rule` (`pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`, `component`, `keepalive`, `extend`, `remark`, `weigh`, `status`)
SELECT @sec_pid, 'menu', '访问控制', 'live/accessSetting', 'live/accessSetting', 'fa fa-shield', 'iframe', '/admin/live.AccessSetting/index', '', 0, 'none', '后台IP白名单管理', @sec_maxw + 1, 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `ba_admin_rule` WHERE `name` = 'live/accessSetting');

-- ---- 6. 后台菜单：谷歌验证码绑定 ----
INSERT INTO `ba_admin_rule` (`pid`, `type`, `title`, `name`, `path`, `icon`, `menu_type`, `url`, `component`, `keepalive`, `extend`, `remark`, `weigh`, `status`)
SELECT @sec_pid, 'menu', '谷歌验证码', 'live/totpSetup', 'live/totpSetup', 'fa fa-key', 'iframe', '/admin/live.TotpSetup/index', '', 0, 'none', '管理员的2FA绑定/解绑', @sec_maxw + 2, 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `ba_admin_rule` WHERE `name` = 'live/totpSetup');

-- ---- 7. 归属修正：确保这两个菜单挂在「数据安全管理」下 ----
-- 早期版本会挂到「直播运营」下，本段幂等，已执行过的实例会被自动纠正
UPDATE `ba_admin_rule`
SET `pid` = @sec_pid,
    `weigh` = @sec_maxw + IF(`name` = 'live/accessSetting', 1, 2)
WHERE `name` IN ('live/accessSetting', 'live/totpSetup')
  AND @sec_pid IS NOT NULL AND @sec_pid > 0
  AND `pid` <> @sec_pid;

-- 完成
SELECT 'admin security upgrade done' AS result;
