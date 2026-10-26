-- ============================================================
-- AI 站点注册：强制邮箱验证码开关
-- 2026-10-17
--
-- 背景：/api/live/registerFromAi 原本不校验邮箱，任意邮箱都能注册；
--       后台伪删除后，换个新邮箱即可重新注册。
-- 开启后：注册必须先调用 /api/live/sendEmailCode 收取 6 位验证码，
--         注册接口校验通过才会创建账号。
--
-- 前置条件：后台「系统配置 → 邮件配置」需先填好 smtp_user / smtp_pass /
--          smtp_sender_mail，否则验证码发不出去。
--
-- 默认 0（关闭）。后台「会员管理」列表顶部可切换「注册邮箱验证」。
-- 开启前需完成 SMTP 配置；关闭不影响找回密码与邮箱免密登录的验证。
--
-- 幂等：可重复执行。
-- ============================================================

INSERT INTO `lp_platform_config` (`key`, `value`, `remark`, `updated_at`)
SELECT 'ai_register_email_verify', '0',
       '注册邮箱验证：1开启，0关闭（默认关闭，后台会员管理可切换）', NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `lp_platform_config` WHERE `key` = 'ai_register_email_verify');
