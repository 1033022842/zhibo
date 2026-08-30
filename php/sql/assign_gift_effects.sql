-- ========================================
-- 礼物特效分配：8 个礼物改名对应特效并挂载 effect_code
-- 说明：只改 name / effect_code，lp_gift_keyword 的触发大类保持不变
--       （送礼仍切换对应大类的视频，同时全屏播放特效动画）
-- 价格由管理后台自行调整
-- ========================================

UPDATE `lp_gift` SET `name` = '玫瑰',     `effect_code` = 'rose_effect'            WHERE `gift_code` = 'rose';
UPDATE `lp_gift` SET `name` = '嘉年华',   `effect_code` = 'effect_carnival'        WHERE `gift_code` = 'kaixin';
UPDATE `lp_gift` SET `name` = '爱心',     `effect_code` = 'effect_heart'           WHERE `gift_code` = 'yongbao';
UPDATE `lp_gift` SET `name` = '宇宙之心', `effect_code` = 'effect_heart_universe'  WHERE `gift_code` = 'youhuo';
UPDATE `lp_gift` SET `name` = '海上生明月', `effect_code` = 'effect_moon_sea'      WHERE `gift_code` = 'ceyan';
UPDATE `lp_gift` SET `name` = '浪漫马车', `effect_code` = 'effect_romantic_carriage' WHERE `gift_code` = 'churuchang';
UPDATE `lp_gift` SET `name` = '火箭',     `effect_code` = 'effect_rocket'          WHERE `gift_code` = 'bianlian';
UPDATE `lp_gift` SET `name` = '超级跑车', `effect_code` = 'effect_supercar'        WHERE `gift_code` = 'lache';
