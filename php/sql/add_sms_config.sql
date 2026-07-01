-- 添加短信服务配置分组
-- 更新 config_group，加入 sms 分组
UPDATE `ba_config` SET `value` = '[{"key":"basics","value":"Basics"},{"key":"mail","value":"Mail"},{"key":"sms","value":"SMS"},{"key":"config_quick_entrance","value":"Config Quick entrance"}]' WHERE `name` = 'config_group';

-- 短信配置项
INSERT IGNORE INTO `ba_config` (`name`, `group`, `title`, `tip`, `type`, `value`, `content`, `rule`, `extend`, `weigh`) VALUES
('sms_api_url', 'sms', 'API接口地址', '短信服务商的API请求地址', 'string', '', '', '', '', 10),
('sms_api_key', 'sms', 'API密钥', '短信服务商提供的API Key（需加密存储）', 'string', '', '', '', '', 9),
('sms_sign_id', 'sms', '签名ID', '短信签名标识', 'string', '', '', '', '', 8),
('sms_template_id', 'sms', '模板ID', '短信模板编码', 'string', '', '', '', '', 7),
('sms_active_provider', 'sms', '激活渠道', '当前使用的短信渠道标识，留空使用默认', 'string', 'default', '', '', '', 6),
('sms_grayscale_providers', 'sms', '灰度切换配置', 'JSON格式，如{"provider_a":30,"provider_b":70}表示按百分比流量分配', 'textarea', '', '', '', '', 5);
