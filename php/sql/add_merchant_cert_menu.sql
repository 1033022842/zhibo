-- 商家认证审核菜单（link 模式，直接打开PHP渲染页面）
INSERT INTO `ba_admin_rule` (`pid`, `type`, `name`, `title`, `icon`, `path`, `component`, `menu_type`, `weigh`, `status`) VALUES
(0, 'menu', 'live/merchantCertification', '商家认证审核', 'fa fa-certificate', '/admin/live.MerchantCertification/index', '', 'link', 50, 1)
ON DUPLICATE KEY UPDATE `title`='商家认证审核', `path`='/admin/live.MerchantCertification/index', `menu_type`='link', `status`=1;
