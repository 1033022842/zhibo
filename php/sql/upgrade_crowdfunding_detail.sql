-- ============================================================================
-- 迁移：众筹项目表增加更详细的角色信息字段
-- 目的：发起众筹时补充标签、风格、18+、语种、语音风格、交付内容等信息
-- 执行：mysql -u root -p live_platform < php/sql/upgrade_crowdfunding_detail.sql
-- ============================================================================

ALTER TABLE `lp_crowdfunding_project`
  ADD COLUMN `tags`          VARCHAR(255) NOT NULL DEFAULT ''  COMMENT '标签，逗号分隔，如：御姐,甜美,高冷' AFTER `description`,
  ADD COLUMN `style`         VARCHAR(32)  NOT NULL DEFAULT ''  COMMENT '风格：realistic写实/anime二次元/3d/cyberpunk赛博朋克/chinese古风/korean韩系/western欧美' AFTER `tags`,
  ADD COLUMN `gender`        VARCHAR(16)  NOT NULL DEFAULT ''  COMMENT '角色性别：female/male/other' AFTER `style`,
  ADD COLUMN `age_range`     VARCHAR(16)  NOT NULL DEFAULT ''  COMMENT '年龄段：18-22/23-27/28-35/36-45/45+' AFTER `gender`,
  ADD COLUMN `language`      VARCHAR(32)  NOT NULL DEFAULT ''  COMMENT '语种：zh-CN/en-US/ja-JP/ms-MY/multi' AFTER `age_range`,
  ADD COLUMN `personality`   VARCHAR(255) NOT NULL DEFAULT ''  COMMENT '性格特点，逗号分隔，如：温柔,幽默,粘人' AFTER `language`,
  ADD COLUMN `voice_style`   VARCHAR(64)  NOT NULL DEFAULT ''  COMMENT '语音风格：sweet甜美/mature御姐/magnetic磁性/loli萝莉/cold冷艳/gentle温柔/none不涉及语音' AFTER `personality`,
  ADD COLUMN `deliverables`  VARCHAR(255) NOT NULL DEFAULT ''  COMMENT '交付内容，逗号分隔：portrait立绘/voice语音/video短视频/live直播/chat聊天' AFTER `voice_style`,
  ADD COLUMN `is_adult`      TINYINT(1)   NOT NULL DEFAULT 0   COMMENT '是否18+内容：0否 1是' AFTER `deliverables`,
  ADD COLUMN `highlights`    TEXT         NULL                 COMMENT '项目亮点/卖点' AFTER `is_adult`,
  ADD COLUMN `reference_url` VARCHAR(512) NOT NULL DEFAULT ''  COMMENT '参考链接' AFTER `highlights`;

-- 索引：按风格 / 18+ 筛选
ALTER TABLE `lp_crowdfunding_project`
  ADD INDEX `idx_style` (`style`),
  ADD INDEX `idx_is_adult` (`is_adult`);
