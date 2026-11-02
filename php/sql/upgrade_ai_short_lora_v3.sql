-- ============================================
-- 50 条预设：LoRA 链精简到 2 层（基座 + 1 个主 LoRA）
-- 执行：source php/sql/upgrade_ai_short_lora_v3.sql
--
-- 依据 2026-10-27 的 LoRA 隔离实验（同图/同提示词/同 seed，只改 LoRA）：
--   L1 仅基座              detail=6.08  ← 画质最佳
--   L2 +MysticXXX 0.7      detail=4.54
--   L4 +fingering 0.8      detail=5.48
--   L5 完整 4 层（旧现状） detail=4.67  ← 最差
-- 每多叠一层 LoRA，细节量继续下降。补位层无语义贡献，只贡献噪声。
--
-- 新结构：motion_enhancer(1.0) + 主 LoRA(0.55~0.80，按分类定)
-- ============================================


UPDATE `lp_short_preset` SET `lora_chain`='[["minimax-h3_fl2v_8Step_motion_enhancer.safetensors", 1.0], ["MysticXXX_MMH3-V4.safetensors", 0.65]]', `updated_at`=NOW() WHERE `code`='gs-01';

UPDATE `lp_short_preset` SET `lora_chain`='[["minimax-h3_fl2v_8Step_motion_enhancer.safetensors", 1.0], ["H3_slop_bounce.safetensors", 0.7]]', `updated_at`=NOW() WHERE `code`='gs-02';

UPDATE `lp_short_preset` SET `lora_chain`='[["minimax-h3_fl2v_8Step_motion_enhancer.safetensors", 1.0], ["MMH3_Perfect_fingering_v1_000002000.safetensors", 0.7]]', `updated_at`=NOW() WHERE `code`='gs-03';

UPDATE `lp_short_preset` SET `lora_chain`='[["minimax-h3_fl2v_8Step_motion_enhancer.safetensors", 1.0], ["ass_jiggle_H3_i2v_v1.0.safetensors", 0.7]]', `updated_at`=NOW() WHERE `code`='gs-04';

UPDATE `lp_short_preset` SET `lora_chain`='[["minimax-h3_fl2v_8Step_motion_enhancer.safetensors", 1.0], ["MM-H3 - Pussy Lora.safetensors", 0.65]]', `updated_at`=NOW() WHERE `code`='gs-05';

UPDATE `lp_short_preset` SET `lora_chain`='[["minimax-h3_fl2v_8Step_motion_enhancer.safetensors", 1.0], ["ass_jiggle_H3_i2v_v1.0.safetensors", 0.65]]', `updated_at`=NOW() WHERE `code`='gs-06';

UPDATE `lp_short_preset` SET `lora_chain`='[["minimax-h3_fl2v_8Step_motion_enhancer.safetensors", 1.0], ["MMH3_Perfect_fingering_v1_000002000.safetensors", 0.65]]', `updated_at`=NOW() WHERE `code`='gs-07';

UPDATE `lp_short_preset` SET `lora_chain`='[["minimax-h3_fl2v_8Step_motion_enhancer.safetensors", 1.0], ["MMH3_Perfect_fingering_v1_000002000.safetensors", 0.6]]', `updated_at`=NOW() WHERE `code`='gs-08';

UPDATE `lp_short_preset` SET `lora_chain`='[["minimax-h3_fl2v_8Step_motion_enhancer.safetensors", 1.0], ["MMH3_Perfect_fingering_v1_000002000.safetensors", 0.6]]', `updated_at`=NOW() WHERE `code`='gs-09';

UPDATE `lp_short_preset` SET `lora_chain`='[["minimax-h3_fl2v_8Step_motion_enhancer.safetensors", 1.0], ["H3_slop_bounce.safetensors", 0.75]]', `updated_at`=NOW() WHERE `code`='gs-10';

UPDATE `lp_short_preset` SET `lora_chain`='[["minimax-h3_fl2v_8Step_motion_enhancer.safetensors", 1.0], ["MysticXXX_MMH3-V4.safetensors", 0.65]]', `updated_at`=NOW() WHERE `code`='gs-11';

UPDATE `lp_short_preset` SET `lora_chain`='[["minimax-h3_fl2v_8Step_motion_enhancer.safetensors", 1.0], ["ass_jiggle_H3_i2v_v1.0.safetensors", 0.75]]', `updated_at`=NOW() WHERE `code`='gs-12';

UPDATE `lp_short_preset` SET `lora_chain`='[["minimax-h3_fl2v_8Step_motion_enhancer.safetensors", 1.0], ["MM-H3 - Pussy Lora.safetensors", 0.7]]', `updated_at`=NOW() WHERE `code`='gs-13';

UPDATE `lp_short_preset` SET `lora_chain`='[["minimax-h3_fl2v_8Step_motion_enhancer.safetensors", 1.0], ["MMH3_Perfect_fingering_v1_000002000.safetensors", 0.65]]', `updated_at`=NOW() WHERE `code`='gs-14';

UPDATE `lp_short_preset` SET `lora_chain`='[["minimax-h3_fl2v_8Step_motion_enhancer.safetensors", 1.0], ["ass_jiggle_H3_i2v_v1.0.safetensors", 0.7]]', `updated_at`=NOW() WHERE `code`='gs-15';

UPDATE `lp_short_preset` SET `lora_chain`='[["minimax-h3_fl2v_8Step_motion_enhancer.safetensors", 1.0], ["MMH3_Perfect_fingering_v1_000002000.safetensors", 0.6]]', `updated_at`=NOW() WHERE `code`='gs-16';

UPDATE `lp_short_preset` SET `lora_chain`='[["minimax-h3_fl2v_8Step_motion_enhancer.safetensors", 1.0], ["MMH3_Perfect_fingering_v1_000002000.safetensors", 0.65]]', `updated_at`=NOW() WHERE `code`='gs-17';

UPDATE `lp_short_preset` SET `lora_chain`='[["minimax-h3_fl2v_8Step_motion_enhancer.safetensors", 1.0], ["MysticXXX_MMH3-V4.safetensors", 0.55]]', `updated_at`=NOW() WHERE `code`='gs-18';

UPDATE `lp_short_preset` SET `lora_chain`='[["minimax-h3_fl2v_8Step_motion_enhancer.safetensors", 1.0], ["MM-H3 - Pussy Lora.safetensors", 0.6]]', `updated_at`=NOW() WHERE `code`='gs-19';

UPDATE `lp_short_preset` SET `lora_chain`='[["minimax-h3_fl2v_8Step_motion_enhancer.safetensors", 1.0], ["MMH3_Perfect_fingering_v1_000002000.safetensors", 0.65]]', `updated_at`=NOW() WHERE `code`='gs-20';

UPDATE `lp_short_preset` SET `lora_chain`='[["minimax-h3_fl2v_8Step_motion_enhancer.safetensors", 1.0], ["MMH3_Perfect_fingering_v1_000002000.safetensors", 0.65]]', `updated_at`=NOW() WHERE `code`='gs-21';

UPDATE `lp_short_preset` SET `lora_chain`='[["minimax-h3_fl2v_8Step_motion_enhancer.safetensors", 1.0], ["MM-H3 - Pussy Lora.safetensors", 0.6]]', `updated_at`=NOW() WHERE `code`='gs-22';

UPDATE `lp_short_preset` SET `lora_chain`='[["minimax-h3_fl2v_8Step_motion_enhancer.safetensors", 1.0], ["MM-H3 - Pussy Lora.safetensors", 0.6]]', `updated_at`=NOW() WHERE `code`='gs-23';

UPDATE `lp_short_preset` SET `lora_chain`='[["minimax-h3_fl2v_8Step_motion_enhancer.safetensors", 1.0], ["MM-H3 - Pussy Lora.safetensors", 0.6]]', `updated_at`=NOW() WHERE `code`='gs-24';

UPDATE `lp_short_preset` SET `lora_chain`='[["minimax-h3_fl2v_8Step_motion_enhancer.safetensors", 1.0], ["MysticXXX_MMH3-V4.safetensors", 0.65]]', `updated_at`=NOW() WHERE `code`='gs-25';

UPDATE `lp_short_preset` SET `lora_chain`='[["minimax-h3_fl2v_8Step_motion_enhancer.safetensors", 1.0], ["MM-H3 - Pussy Lora.safetensors", 0.65]]', `updated_at`=NOW() WHERE `code`='gs-26';

UPDATE `lp_short_preset` SET `lora_chain`='[["minimax-h3_fl2v_8Step_motion_enhancer.safetensors", 1.0], ["MysticXXX_MMH3-V4.safetensors", 0.7]]', `updated_at`=NOW() WHERE `code`='gs-27';

UPDATE `lp_short_preset` SET `lora_chain`='[["minimax-h3_fl2v_8Step_motion_enhancer.safetensors", 1.0], ["ass_jiggle_H3_i2v_v1.0.safetensors", 0.7]]', `updated_at`=NOW() WHERE `code`='gs-28';

UPDATE `lp_short_preset` SET `lora_chain`='[["minimax-h3_fl2v_8Step_motion_enhancer.safetensors", 1.0], ["H3_slop_bounce.safetensors", 0.75]]', `updated_at`=NOW() WHERE `code`='gs-29';

UPDATE `lp_short_preset` SET `lora_chain`='[["minimax-h3_fl2v_8Step_motion_enhancer.safetensors", 1.0], ["MM-H3 - Pussy Lora.safetensors", 0.6]]', `updated_at`=NOW() WHERE `code`='gs-30';

UPDATE `lp_short_preset` SET `lora_chain`='[["minimax-h3_fl2v_8Step_motion_enhancer.safetensors", 1.0], ["MM-H3 - Pussy Lora.safetensors", 0.6]]', `updated_at`=NOW() WHERE `code`='gs-31';

UPDATE `lp_short_preset` SET `lora_chain`='[["minimax-h3_fl2v_8Step_motion_enhancer.safetensors", 1.0], ["ass_jiggle_H3_i2v_v1.0.safetensors", 0.75]]', `updated_at`=NOW() WHERE `code`='gs-32';

UPDATE `lp_short_preset` SET `lora_chain`='[["minimax-h3_fl2v_8Step_motion_enhancer.safetensors", 1.0], ["MysticXXX_MMH3-V4.safetensors", 0.7]]', `updated_at`=NOW() WHERE `code`='gs-33';

UPDATE `lp_short_preset` SET `lora_chain`='[["minimax-h3_fl2v_8Step_motion_enhancer.safetensors", 1.0], ["H3_slop_bounce.safetensors", 0.7]]', `updated_at`=NOW() WHERE `code`='gs-34';

UPDATE `lp_short_preset` SET `lora_chain`='[["minimax-h3_fl2v_8Step_motion_enhancer.safetensors", 1.0], ["MMH3_Perfect_fingering_v1_000002000.safetensors", 0.6]]', `updated_at`=NOW() WHERE `code`='gs-35';

UPDATE `lp_short_preset` SET `lora_chain`='[["minimax-h3_fl2v_8Step_motion_enhancer.safetensors", 1.0], ["MysticXXX_MMH3-V4.safetensors", 0.65]]', `updated_at`=NOW() WHERE `code`='gs-36';

UPDATE `lp_short_preset` SET `lora_chain`='[["minimax-h3_fl2v_8Step_motion_enhancer.safetensors", 1.0], ["H3_slop_bounce.safetensors", 0.75]]', `updated_at`=NOW() WHERE `code`='gs-37';

UPDATE `lp_short_preset` SET `lora_chain`='[["minimax-h3_fl2v_8Step_motion_enhancer.safetensors", 1.0], ["MM-H3 - Pussy Lora.safetensors", 0.7]]', `updated_at`=NOW() WHERE `code`='gs-38';

UPDATE `lp_short_preset` SET `lora_chain`='[["minimax-h3_fl2v_8Step_motion_enhancer.safetensors", 1.0], ["MysticXXX_MMH3-V4.safetensors", 0.7]]', `updated_at`=NOW() WHERE `code`='gs-39';

UPDATE `lp_short_preset` SET `lora_chain`='[["minimax-h3_fl2v_8Step_motion_enhancer.safetensors", 1.0], ["MysticXXX_MMH3-V4.safetensors", 0.65]]', `updated_at`=NOW() WHERE `code`='gs-40';

UPDATE `lp_short_preset` SET `lora_chain`='[["minimax-h3_fl2v_8Step_motion_enhancer.safetensors", 1.0], ["MysticXXX_MMH3-V4.safetensors", 0.75]]', `updated_at`=NOW() WHERE `code`='gs-41';

UPDATE `lp_short_preset` SET `lora_chain`='[["minimax-h3_fl2v_8Step_motion_enhancer.safetensors", 1.0], ["MM-H3 - Pussy Lora.safetensors", 0.75]]', `updated_at`=NOW() WHERE `code`='gs-42';

UPDATE `lp_short_preset` SET `lora_chain`='[["minimax-h3_fl2v_8Step_motion_enhancer.safetensors", 1.0], ["MMH3_Perfect_fingering_v1_000002000.safetensors", 0.55]]', `updated_at`=NOW() WHERE `code`='gs-43';

UPDATE `lp_short_preset` SET `lora_chain`='[["minimax-h3_fl2v_8Step_motion_enhancer.safetensors", 1.0], ["H3_slop_bounce.safetensors", 0.8]]', `updated_at`=NOW() WHERE `code`='gs-44';

UPDATE `lp_short_preset` SET `lora_chain`='[["minimax-h3_fl2v_8Step_motion_enhancer.safetensors", 1.0], ["MM-H3 - Pussy Lora.safetensors", 0.7]]', `updated_at`=NOW() WHERE `code`='gs-45';

UPDATE `lp_short_preset` SET `lora_chain`='[["minimax-h3_fl2v_8Step_motion_enhancer.safetensors", 1.0], ["MM-H3 - Pussy Lora.safetensors", 0.6]]', `updated_at`=NOW() WHERE `code`='gs-46';

UPDATE `lp_short_preset` SET `lora_chain`='[["minimax-h3_fl2v_8Step_motion_enhancer.safetensors", 1.0], ["H3_slop_bounce.safetensors", 0.65]]', `updated_at`=NOW() WHERE `code`='gs-47';

UPDATE `lp_short_preset` SET `lora_chain`='[["minimax-h3_fl2v_8Step_motion_enhancer.safetensors", 1.0], ["ass_jiggle_H3_i2v_v1.0.safetensors", 0.65]]', `updated_at`=NOW() WHERE `code`='gs-48';

UPDATE `lp_short_preset` SET `lora_chain`='[["minimax-h3_fl2v_8Step_motion_enhancer.safetensors", 1.0], ["ass_jiggle_H3_i2v_v1.0.safetensors", 0.8]]', `updated_at`=NOW() WHERE `code`='gs-49';

UPDATE `lp_short_preset` SET `lora_chain`='[["minimax-h3_fl2v_8Step_motion_enhancer.safetensors", 1.0], ["H3_slop_bounce.safetensors", 0.8]]', `updated_at`=NOW() WHERE `code`='gs-50';


-- 自检：应全部是 2 层，且第一个必为 motion_enhancer
SELECT COUNT(*) AS total,
       SUM(JSON_LENGTH(`lora_chain`) = 2) AS two_layer,
       SUM(JSON_UNQUOTE(JSON_EXTRACT(`lora_chain`, '$[0][0]')) LIKE '%motion_enhancer%') AS base_ok
FROM `lp_short_preset` WHERE `status`=1;
