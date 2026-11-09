-- ============================================================================
-- kiss 建库脚本：以 sugus 数据为基线，保留角色/素材/配置，清空全部用户数据
-- 生成于 2026-11-09，配合 docs/kiss重建计划.md 阶段 3 使用
--
-- 用法（新机上，导入 sugus 全量后执行）：
--   mysql -uroot -p live_platform < kiss_建库清空用户.sql
--
-- ⚠️ 执行前：
--   1. 已把 sugus 的 live_platform 全量导入本库（结构和内容都在）
--   2. 确认这是 kiss 库不是 sugus 库（第 0 节有校验）
-- ============================================================================

-- ── 0. 安全校验 ────────────────────────────────────────────────────────
SELECT CONCAT('当前库：', DATABASE()) AS `=== CHECK ===`;
SELECT IF(DATABASE()='live_platform','OK 继续','!! 中止：库名不对') AS `=== SAFETY ===`;

-- ── 1. 清空用户数据（TRUNCATE 重置自增，新用户 ID 从 1 开始）────────────
SET FOREIGN_KEY_CHECKS = 0;

-- 账户与身份
TRUNCATE TABLE `lp_user`;
TRUNCATE TABLE `lp_user_auth`;
TRUNCATE TABLE `lp_user_profile`;
TRUNCATE TABLE `lp_user_setting`;
TRUNCATE TABLE `lp_user_device`;
TRUNCATE TABLE `lp_user_persona`;

-- 钱包与资产
TRUNCATE TABLE `lp_wallet_account`;
TRUNCATE TABLE `lp_wallet_ledger`;
TRUNCATE TABLE `lp_user_item`;
TRUNCATE TABLE `lp_user_gen_credit`;
TRUNCATE TABLE `lp_user_quota`;

-- 订单与支付
TRUNCATE TABLE `lp_recharge_order`;
TRUNCATE TABLE `lp_vip_order`;
TRUNCATE TABLE `lp_gift_order`;
TRUNCATE TABLE `lp_withdrawal`;
TRUNCATE TABLE `lp_payment_callback_log`;

-- AI 会话与互动（kiss 代码实际引用的）
TRUNCATE TABLE `lp_ai_chat_message`;
TRUNCATE TABLE `lp_ai_affection`;
TRUNCATE TABLE `lp_ai_collect`;
TRUNCATE TABLE `lp_ai_media_unlock`;
TRUNCATE TABLE `lp_ai_task`;
TRUNCATE TABLE `lp_ai_task_log`;
TRUNCATE TABLE `lp_chat_message`;
TRUNCATE TABLE `lp_post_like`;
TRUNCATE TABLE `lp_short_like`;
TRUNCATE TABLE `lp_user_watch_history`;

-- 邀请（用户侧记录）
TRUNCATE TABLE `lp_invite_record`;

-- 直播运行时（观众侧）
TRUNCATE TABLE `lp_room_event_log`;
TRUNCATE TABLE `lp_room_online_minute`;
TRUNCATE TABLE `lp_room_state_snapshot`;

-- 创作者 / 众筹 / 提现（用户侧业务）
TRUNCATE TABLE `lp_creator_submission`;
TRUNCATE TABLE `lp_crowdfunding_pledge`;
TRUNCATE TABLE `lp_merchant_certification`;
TRUNCATE TABLE `lp_payout_account`;

-- 后台登录日志与令牌（不含操作日志之外的配置）
TRUNCATE TABLE `ba_admin_log`;
TRUNCATE TABLE `ba_token`;

SET FOREIGN_KEY_CHECKS = 1;

-- ── 2. kiss 自己的配置（必须改，不能留 sugus 的）───────────────────────

-- 2.1 站点名字/域名类配置
UPDATE `lp_platform_config` SET `value`='' WHERE `key`='site_home_page';

-- 2.2 推广像素：sugus 的 FB/TikTok 像素 ID，留着会把 kiss 的访问数据发往 sugus 的广告账户
TRUNCATE TABLE `lp_promo_pixel`;

-- 2.3 推广链接：清掉 sugus 的
TRUNCATE TABLE `lp_promo_link`;

-- 2.4 法币通道：清空后重建 kiss 自己的（HissPay / MKPay 巴西）
--     注意：先看仓库里 kiss 需要哪几个网关，不要盲删
-- DELETE FROM `lp_fiat_channel` WHERE gateway NOT IN ('hisspay','mkpay');
-- UPDATE `lp_fiat_channel` SET status=0 WHERE gateway NOT IN ('hisspay','mkpay');

-- 2.5 链上收款通道：sugus 的 USDT 地址必须换成 kiss 的
--     否则用户打款到 sugus 的钱包，钱进了别人账户
-- TRUNCATE TABLE `lp_recharge_channel`;
-- （重建时按 kiss 的钱包地址重新录入）

-- 2.6 客服渠道 / 代理：sugus 的，必须改
-- UPDATE `lp_cs_channel` SET ... ;   -- 改成 kiss 的 TG/邮箱
-- UPDATE `lp_agent` SET domain=... ; -- 改成 kisss.ai

-- ── 3. 后台管理员：重建为 kiss 自己的密码 ───────────────────────────────
-- ⚠️ 不要在 mysql -e 里写 bcrypt（bash 会吃掉 $2y$10$ 里的 $）
--    正确：php -r 'echo password_hash("新密码", PASSWORD_DEFAULT), "\n";'
--    写进 .sql 文件再导入
--
-- UPDATE `ba_admin` SET `username`='admin', `password`='<bcrypt>',
--   `nickname`='kiss管理员' WHERE `id`=1;

-- ── 4. 保留 sugus 的「AI 工作室」相关表（kiss 代码不引用，留着无害）─────
--   lp_short_preset / lp_short_preset_fav / lp_ai_content 等保持原样，
--   若将来客户要求部署 AI 工作室可直接用。

-- ── 5. 校验 ────────────────────────────────────────────────────────────
SELECT '=== 清空后应为 0 ===' AS `校验`;
SELECT 'lp_user'             AS tbl, COUNT(*) AS cnt FROM `lp_user`
UNION ALL SELECT 'lp_user_profile',    COUNT(*) FROM `lp_user_profile`
UNION ALL SELECT 'lp_wallet_account',  COUNT(*) FROM `lp_wallet_account`
UNION ALL SELECT 'lp_recharge_order',  COUNT(*) FROM `lp_recharge_order`
UNION ALL SELECT 'lp_ai_chat_message', COUNT(*) FROM `lp_ai_chat_message`
UNION ALL SELECT 'lp_user_item',       COUNT(*) FROM `lp_user_item`
UNION ALL SELECT 'lp_promo_pixel',     COUNT(*) FROM `lp_promo_pixel`;

SELECT '=== 保留项应 >0 ===' AS `校验`;
SELECT 'lp_persona'                AS tbl, COUNT(*) AS cnt FROM `lp_persona`
UNION ALL SELECT 'lp_media_asset',           COUNT(*) FROM `lp_media_asset`
UNION ALL SELECT 'lp_room',                  COUNT(*) FROM `lp_room`
UNION ALL SELECT 'lp_room_binding',          COUNT(*) FROM `lp_room_binding`
UNION ALL SELECT 'lp_playlist_template',     COUNT(*) FROM `lp_playlist_template`
UNION ALL SELECT 'lp_playlist_template_item',COUNT(*) FROM `lp_playlist_template_item`
UNION ALL SELECT 'lp_short_item',            COUNT(*) FROM `lp_short_item`
UNION ALL SELECT 'lp_short_episode',         COUNT(*) FROM `lp_short_episode`
UNION ALL SELECT 'lp_gift',                  COUNT(*) FROM `lp_gift`
UNION ALL SELECT 'lp_private_item',          COUNT(*) FROM `lp_private_item`
UNION ALL SELECT 'lp_home_character',        COUNT(*) FROM `lp_home_character`
UNION ALL SELECT 'lp_vip_plan',              COUNT(*) FROM `lp_vip_plan`
UNION ALL SELECT 'lp_recharge_package',      COUNT(*) FROM `lp_recharge_package`
UNION ALL SELECT 'lp_platform_config',       COUNT(*) FROM `lp_platform_config`;

-- ============================================================================
-- 附录：三分类明细（sugus 实测，2026-11-09）
-- ============================================================================
--
-- 【保留·内容】角色 / 素材 / 房间 / 播单 / 短剧 / 礼物 / 首页推荐
--   lp_persona 191        lp_media_asset 1264      lp_room 13
--   lp_room_binding 12    lp_playlist_template 29  lp_playlist_template_item 381
--   lp_short_item 33      lp_short_episode 165     lp_private_item 58
--   lp_post_item 9        lp_gift 9                lp_gift_keyword 21
--   lp_chat_gift 11       lp_chat_gift_role 25     lp_home_banner 9
--   lp_home_character 74  lp_character_image_pool 132
--   lp_ai_content 125     lp_ai_media 311          lp_room_tag 4
--
-- 【保留·商业配置】改值但不清空
--   lp_platform_config 60   lp_vip_plan 24   lp_recharge_package 6
--   lp_recharge_channel 7 ← 地址必须换成 kiss 的
--   lp_fiat_channel 5       ← 网关必须换成 kiss 的
--   lp_shop_item 10         lp_cs_channel 1 ← 改成 kiss 的
--   lp_agent 1              ← 改成 kiss 的
--   lp_promo_pixel 30       ← 已清空，需重配 kiss 的
--
-- 【清空·用户数据】见第 1 节，合计约 24 万行
--   其中最大的两张：lp_room_online_minute 154636、lp_room_event_log 59065
--
-- 【保留但 kiss 不引用】AI 工作室
--   lp_short_preset 87   lp_short_preset_fav 6   lp_ai_task* 已清（用户侧）
-- ============================================================================
