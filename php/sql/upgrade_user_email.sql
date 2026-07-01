-- ============================================
-- 1. lp_user 表新增 email 字段
-- ============================================
ALTER TABLE `lp_user` ADD COLUMN `email` varchar(128) NOT NULL DEFAULT '' COMMENT '邮箱' AFTER `nickname`;
ALTER TABLE `lp_user` ADD INDEX `idx_email` (`email`);

-- ============================================
-- 2. 更新 config_group 添加 sms 分组
-- ============================================
UPDATE `ba_config` SET `value` = '[{"key":"basics","value":"Basics"},{"key":"mail","value":"Mail"},{"key":"sms","value":"SMS"},{"key":"config_quick_entrance","value":"Config Quick entrance"}]' WHERE `name` = 'config_group';

-- 短信配置项
INSERT IGNORE INTO `ba_config` (`name`, `group`, `title`, `tip`, `type`, `value`, `content`, `rule`, `extend`, `weigh`) VALUES
('sms_api_url', 'sms', 'API接口地址', '短信服务商的API请求地址', 'string', '', '', '', '', 10),
('sms_api_key', 'sms', 'API密钥', '短信服务商提供的API Key（需加密存储）', 'string', '', '', '', '', 9),
('sms_sign_id', 'sms', '签名ID', '短信签名标识', 'string', '', '', '', '', 8),
('sms_template_id', 'sms', '模板ID', '短信模板编码', 'string', '', '', '', '', 7),
('sms_active_provider', 'sms', '激活渠道', '当前使用的短信渠道标识，留空使用默认', 'string', 'default', '', '', '', 6),
('sms_grayscale_providers', 'sms', '灰度切换配置', 'JSON格式，如{"provider_a":30,"provider_b":70}表示按百分比流量分配', 'textarea', '', '', '', '', 5);

-- ============================================
-- 3. 短信服务配置 菜单
-- ============================================
SET @routine_pid = (SELECT `id` FROM `ba_admin_rule` WHERE `name` = 'routine' AND `type` = 'menu_dir' LIMIT 1);

INSERT IGNORE INTO `ba_admin_rule` (`pid`, `type`, `name`, `title`, `icon`, `path`, `component`, `menu_type`, `weigh`, `status`) VALUES
(@routine_pid, 'menu', 'routine/smsConfig', '短信服务配置', 'fa fa-message', '/admin/routine/smsConfig', 'routine/smsConfig/index', 0, 4, 1);

-- ============================================
-- 4. 直播平台注册用户管理 菜单
-- ============================================
SET @user_pid = (SELECT `id` FROM `ba_admin_rule` WHERE `name` = 'user' AND `type` = 'menu_dir' LIMIT 1);

INSERT IGNORE INTO `ba_admin_rule` (`pid`, `type`, `name`, `title`, `icon`, `path`, `component`, `menu_type`, `weigh`, `status`) VALUES
(@user_pid, 'menu', 'user/liveUser', '直播平台用户', 'fa fa-users', '/admin/user/liveUser', 'user/liveUser/index', 0, 5, 1);
