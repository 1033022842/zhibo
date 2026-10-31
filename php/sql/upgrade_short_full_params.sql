-- ============================================
-- lp_short_preset 升级：支持完整参数（DeepThroat 模板）
-- 执行：mysql zhibo < php/sql/upgrade_short_full_params.sql
--
-- 背景：
--   原来 lp_short_preset 只有 lora_chain + duration_sec，worker 里
--   task_type/sampler/scheduler/steps/unet 都是写死的。现在每类卡片
--   需要不同参数（如 DeepThroat 用 I2VA + beta57 + 单 LoRA），
--   必须让预设自己能声明这些值。
--
-- 新增字段：
--   task_type      I2VA | FL2VA | Ref2VA | Hybrid | T2VA
--   sampler        euler | res_multistep | ...
--   scheduler      beta57 | simple | beta | ...
--                  注意 beta57 = BetaSamplingScheduler(alpha=0.5, beta=0.7)
--   sampler_alpha  beta57 的 alpha（默认 0.5）
--   sampler_beta   beta57 的 beta（默认 0.7）
--   steps          采样步数
--   unet_name      基座模型
--   persona_thumb  人设缩略图（卡片左上角）
--   reference_image 固定参考图（可选，I2VA 单图模板可预置）
-- ============================================

SET @db := DATABASE();

-- task_type
SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `lp_short_preset` ADD COLUMN `task_type` VARCHAR(16) NOT NULL DEFAULT ''I2VA'' COMMENT ''I2VA|FL2VA|Ref2VA|Hybrid|T2VA'' AFTER `kind`',
  'SELECT 1')
  FROM information_schema.columns
  WHERE table_schema = @db AND table_name = 'lp_short_preset' AND column_name = 'task_type');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- sampler
SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `lp_short_preset` ADD COLUMN `sampler` VARCHAR(32) NOT NULL DEFAULT ''euler'' COMMENT ''采样器'' AFTER `task_type`',
  'SELECT 1')
  FROM information_schema.columns
  WHERE table_schema = @db AND table_name = 'lp_short_preset' AND column_name = 'sampler');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- scheduler
SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `lp_short_preset` ADD COLUMN `scheduler` VARCHAR(32) NOT NULL DEFAULT ''beta57'' COMMENT ''调度器；beta57=BetaSamplingScheduler(0.5/0.7)'' AFTER `sampler`',
  'SELECT 1')
  FROM information_schema.columns
  WHERE table_schema = @db AND table_name = 'lp_short_preset' AND column_name = 'scheduler');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- sampler_alpha
SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `lp_short_preset` ADD COLUMN `sampler_alpha` DECIMAL(4,2) NOT NULL DEFAULT 0.50 COMMENT ''beta 曲线 alpha（beta57=0.5）'' AFTER `scheduler`',
  'SELECT 1')
  FROM information_schema.columns
  WHERE table_schema = @db AND table_name = 'lp_short_preset' AND column_name = 'sampler_alpha');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- sampler_beta
SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `lp_short_preset` ADD COLUMN `sampler_beta` DECIMAL(4,2) NOT NULL DEFAULT 0.70 COMMENT ''beta 曲线 beta（beta57=0.7）'' AFTER `sampler_alpha`',
  'SELECT 1')
  FROM information_schema.columns
  WHERE table_schema = @db AND table_name = 'lp_short_preset' AND column_name = 'sampler_beta');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- steps
SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `lp_short_preset` ADD COLUMN `steps` TINYINT UNSIGNED NOT NULL DEFAULT 8 COMMENT ''采样步数'' AFTER `sampler_beta`',
  'SELECT 1')
  FROM information_schema.columns
  WHERE table_schema = @db AND table_name = 'lp_short_preset' AND column_name = 'steps');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- unet_name
SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `lp_short_preset` ADD COLUMN `unet_name` VARCHAR(128) NOT NULL DEFAULT '''' COMMENT ''基座模型；空=用 worker 默认'' AFTER `steps`',
  'SELECT 1')
  FROM information_schema.columns
  WHERE table_schema = @db AND table_name = 'lp_short_preset' AND column_name = 'unet_name');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- persona_thumb
SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `lp_short_preset` ADD COLUMN `persona_thumb` VARCHAR(500) NOT NULL DEFAULT '''' COMMENT ''人设缩略图（卡片左上角）'' AFTER `cover_url`',
  'SELECT 1')
  FROM information_schema.columns
  WHERE table_schema = @db AND table_name = 'lp_short_preset' AND column_name = 'persona_thumb');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- reference_image
SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `lp_short_preset` ADD COLUMN `reference_image` VARCHAR(500) NOT NULL DEFAULT '''' COMMENT ''固定参考图；I2VA 单图模板可预置，空=用户上传'' AFTER `persona_thumb`',
  'SELECT 1')
  FROM information_schema.columns
  WHERE table_schema = @db AND table_name = 'lp_short_preset' AND column_name = 'reference_image');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 存量 50 条（gs-01..gs-50）改按标准配置：euler + beta57 + 8 步
-- 它们原本是 Ref2VA 双图风格提示词，task_type 保持 Ref2VA
UPDATE `lp_short_preset`
SET `task_type` = 'Ref2VA',
    `sampler` = 'euler',
    `scheduler` = 'beta57',
    `sampler_alpha` = 0.50,
    `sampler_beta` = 0.70,
    `steps` = 8,
    `updated_at` = NOW()
WHERE `code` LIKE 'gs-%';

-- ============================================
-- DeepThroat 卡片（I2VA 单图 + 专用 LoRA）
-- 封面 dt_s1.mp4 的首帧，人设图 persona_001
-- ============================================
INSERT INTO `lp_short_preset`
  (`code`, `title`, `prompt`, `category`, `kind`, `task_type`,
   `sampler`, `scheduler`, `sampler_alpha`, `sampler_beta`, `steps`,
   `unet_name`, `lora_chain`, `duration_sec`,
   `cover_url`, `persona_thumb`, `reference_image`, `preview_url`,
   `weigh`, `status`, `created_at`, `updated_at`)
VALUES (
  'card-dt-01',
  'Deep Throat',
  'For the target video, at 0.00 seconds into the target video, <Picture 1> (from [Shot 1]) is fully referenced.\n\nintegrated_multimodal_description: [Shot 1] Live-action, cinematic, a medium close-up frames the young woman kneeling before the man, who stands on the bed with his back mostly to the camera, showing only the lower part of his body, as he deeply thrusts his penis into her mouth, filling her throat. The camera remains static, capturing the rich, deeply saturated film-grade color and crisp fine detail of the bedroom setting. Initially, the man firmly holds the woman''s head, rhythmically and deeply inserting his penis into her mouth. A moment later, during a deep thrust, he pauses the movement and, instead of withdrawing, pushes his penis even deeper into her throat as he begins to climax and moan from pleasure. Simultaneously, the woman begins to choke on the semen, and the man slightly withdraws his penis, causing the woman to cough, and a large amount of semen flows out of her mouth onto her stomach. Next, while his penis remains partially inserted in her mouth and he continues to hold her head, the man takes a few steps back, lowering one foot from the bed, which forces the woman to arch and lean toward him. She coughs again, and the semen flowing from her mouth continues to run down her chin, neck, and further down. The semen that had previously landed on her stomach also runs down her panties, and these two streams quickly form a noticeable puddle on the sheet beneath the woman.\n\noverall_soundscape: Deep, rhythmic thrusting sounds are audible, interspersed with the woman''s muffled choking sounds and the man''s audible moans of pleasure. Soft fabric rustling accompanies the movements on the bed.\n\nnon_diegetic_music: N/A',
  'blowjob',
  'video',
  'I2VA',
  'euler',
  'beta57',
  0.50,
  0.70,
  8,
  'h3ErosMax_beta7_int8.safetensors',
  '[["MMH3_DeepThroat_v0.1.safetensors", 0.85]]',
  10,
  '/ai/media/cards/dt_s1.jpg',
  '/ai/media/cards/dt_persona.jpg',
  '/ai/media/cards/dt_ref.jpg',
  '/ai/media/cards/dt_s1.mp4',
  100,
  1,
  NOW(),
  NOW()
)
ON DUPLICATE KEY UPDATE
  `title` = VALUES(`title`),
  `prompt` = VALUES(`prompt`),
  `task_type` = VALUES(`task_type`),
  `sampler` = VALUES(`sampler`),
  `scheduler` = VALUES(`scheduler`),
  `steps` = VALUES(`steps`),
  `unet_name` = VALUES(`unet_name`),
  `lora_chain` = VALUES(`lora_chain`),
  `cover_url` = VALUES(`cover_url`),
  `persona_thumb` = VALUES(`persona_thumb`),
  `updated_at` = NOW();
