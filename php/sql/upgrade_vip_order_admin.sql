-- ============================================
-- 后台新增「会员订阅记录」页面（数据源 lp_vip_order）
--
-- 背景：会员订阅数据一直有落库（lp_vip_order：代币购买 / 网关·链上付款 / 后台手动开通），
--       但后台只有在「充值审核」里能看到「网关付款」那一类，代币购买与手动开通的记录无处可查。
-- 本次挂到「财务与审核」菜单下、充值审核之后，并在会员详情页增加订阅记录区块。
--
-- 幂等，可重复执行
-- 2026-10-14
-- ============================================

SET @parent := (SELECT `id` FROM `ba_admin_rule` WHERE `name` = 'finance' AND `type` = 'menu_dir' ORDER BY `id` LIMIT 1);
SET @parent := IFNULL(@parent, 297);
SET @has := (SELECT COUNT(*) FROM `ba_admin_rule` WHERE `name` = 'live/vipOrder');
SET @sql := IF(@has = 0,
  CONCAT('INSERT INTO `ba_admin_rule` (`pid`,`type`,`title`,`name`,`path`,`icon`,`menu_type`,`url`,`component`,`keepalive`,`extend`,`remark`,`weigh`,`status`,`create_time`,`update_time`) VALUES (',
         @parent,
         ',''menu'',''会员订阅记录'',''live/vipOrder'',''live/vipOrder'','''',''iframe'',''/admin/live.VipOrder/index'','''',0,''none'','''',101,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP())'),
  'SELECT ''live/vipOrder 已存在，跳过'' AS msg');
PREPARE st FROM @sql;
EXECUTE st;
DEALLOCATE PREPARE st;

SELECT '--- 校验 ---' AS section;
SELECT `id`,`pid`,`type`,`title`,`name`,`path`,`menu_type`,`url`,`weigh`,`status`
  FROM `ba_admin_rule` WHERE `name` = 'live/vipOrder';
