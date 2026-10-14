-- 聊天场景媒体（Show me the scene）：消息表 media_kind 原 varchar(10) 装不下 chat_scene_image/video
ALTER TABLE lp_ai_chat_message MODIFY media_kind VARCHAR(32) NOT NULL DEFAULT "";
