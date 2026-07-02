ALTER TABLE `lp_persona`
  ADD COLUMN `user_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '所属用户ID(关联lp_user.id)' AFTER `id`,
  ADD COLUMN `source_fields` JSON COMMENT 'AI角色原始属性(type/age/eye/hair/body/breast/hip/personality/profession/hobby/relation/clothing)' AFTER `tags`,
  ADD INDEX `idx_user_id` (`user_id`);
