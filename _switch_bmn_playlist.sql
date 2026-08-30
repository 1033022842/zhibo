-- 1. 旧素材下架（软删除，可恢复：status 改回 1）
UPDATE lp_media_asset SET status=0 WHERE source='machine' AND machine_id='room1' AND status=1;

-- 2. 插入新分类素材（52条）
INSERT INTO lp_media_asset (asset_code,asset_type,scene_type,keywords,persona,weight,title,file_url,duration_ms,checksum,status,source,machine_id,remote_path,created_at) VALUES
('m_room1_bmn001','video','public','出入场','白毛女',1,'出入场循环1','出入场/出入场循环1.mp4',0,'','1','machine','room1','出入场/出入场循环1.mp4',NOW()),
('m_room1_bmn002','video','public','出入场','白毛女',1,'出入场循环2','出入场/出入场循环2.mp4',0,'','1','machine','room1','出入场/出入场循环2.mp4',NOW()),
('m_room1_bmn003','video','public','出入场','白毛女',1,'出入场循环3','出入场/出入场循环3.mp4',0,'','1','machine','room1','出入场/出入场循环3.mp4',NOW()),
('m_room1_bmn004','video','public','出入场','白毛女',1,'出入场循环4','出入场/出入场循环4.mp4',0,'','1','machine','room1','出入场/出入场循环4.mp4',NOW()),
('m_room1_bmn005','video','public','出入场','白毛女',1,'出入场循环5','出入场/出入场循环5.mp4',0,'','1','machine','room1','出入场/出入场循环5.mp4',NOW()),
('m_room1_bmn006','video','public','出入场','白毛女',1,'出入场循环6','出入场/出入场循环6.mp4',0,'','1','machine','room1','出入场/出入场循环6.mp4',NOW()),
('m_room1_bmn007','video','public','待机','白毛女',1,'待机循环1','待机/待机循环1.mp4',0,'','1','machine','room1','待机/待机循环1.mp4',NOW()),
('m_room1_bmn008','video','public','待机','白毛女',1,'待机循环2','待机/待机循环2.mp4',0,'','1','machine','room1','待机/待机循环2.mp4',NOW()),
('m_room1_bmn009','video','public','待机','白毛女',1,'待机循环3','待机/待机循环3.mp4',0,'','1','machine','room1','待机/待机循环3.mp4',NOW()),
('m_room1_bmn010','video','public','待机','白毛女',1,'待机循环4','待机/待机循环4.mp4',0,'','1','machine','room1','待机/待机循环4.mp4',NOW()),
('m_room1_bmn011','video','public','待机','白毛女',1,'待机循环5','待机/待机循环5.mp4',0,'','1','machine','room1','待机/待机循环5.mp4',NOW()),
('m_room1_bmn012','video','public','待机','白毛女',1,'待机循环6','待机/待机循环6.mp4',0,'','1','machine','room1','待机/待机循环6.mp4',NOW()),
('m_room1_bmn013','video','public','待机','白毛女',1,'待机循环7','待机/待机循环7.mp4',0,'','1','machine','room1','待机/待机循环7.mp4',NOW()),
('m_room1_bmn014','video','public','撩发','白毛女',1,'撩发循环1','撩发/撩发循环1.mp4',0,'','1','machine','room1','撩发/撩发循环1.mp4',NOW()),
('m_room1_bmn015','video','public','撩发','白毛女',1,'撩发循环2','撩发/撩发循环2.mp4',0,'','1','machine','room1','撩发/撩发循环2.mp4',NOW()),
('m_room1_bmn016','video','public','撩发','白毛女',1,'撩发循环3','撩发/撩发循环3.mp4',0,'','1','machine','room1','撩发/撩发循环3.mp4',NOW()),
('m_room1_bmn017','video','public','撩发','白毛女',1,'撩发循环4','撩发/撩发循环4.mp4',0,'','1','machine','room1','撩发/撩发循环4.mp4',NOW()),
('m_room1_bmn018','video','public','撩发','白毛女',1,'撩发循环5','撩发/撩发循环5.mp4',0,'','1','machine','room1','撩发/撩发循环5.mp4',NOW()),
('m_room1_bmn019','video','public','撩发','白毛女',1,'撩发循环6','撩发/撩发循环6.mp4',0,'','1','machine','room1','撩发/撩发循环6.mp4',NOW()),
('m_room1_bmn020','video','public','撩发','白毛女',1,'撩发循环7','撩发/撩发循环7.mp4',0,'','1','machine','room1','撩发/撩发循环7.mp4',NOW()),
('m_room1_bmn021','video','public','摸头杀','白毛女',1,'玫瑰循环1','玫瑰/玫瑰循环1.mp4',0,'','1','machine','room1','玫瑰/玫瑰循环1.mp4',NOW()),
('m_room1_bmn022','video','public','摸头杀','白毛女',1,'玫瑰循环2','玫瑰/玫瑰循环2.mp4',0,'','1','machine','room1','玫瑰/玫瑰循环2.mp4',NOW()),
('m_room1_bmn023','video','public','摸头杀','白毛女',1,'玫瑰循环3','玫瑰/玫瑰循环3.mp4',0,'','1','machine','room1','玫瑰/玫瑰循环3.mp4',NOW()),
('m_room1_bmn024','video','public','摸头杀','白毛女',1,'玫瑰循环4','玫瑰/玫瑰循环4.mp4',0,'','1','machine','room1','玫瑰/玫瑰循环4.mp4',NOW()),
('m_room1_bmn025','video','public','摸头杀','白毛女',1,'玫瑰循环5','玫瑰/玫瑰循环5.mp4',0,'','1','machine','room1','玫瑰/玫瑰循环5.mp4',NOW()),
('m_room1_bmn026','video','public','摸头杀','白毛女',1,'玫瑰循环6','玫瑰/玫瑰循环6.mp4',0,'','1','machine','room1','玫瑰/玫瑰循环6.mp4',NOW()),
('m_room1_bmn027','video','public','摸头杀','白毛女',1,'玫瑰循环7','玫瑰/玫瑰循环7.mp4',0,'','1','machine','room1','玫瑰/玫瑰循环7.mp4',NOW()),
('m_room1_bmn028','video','public','飞吻','白毛女',1,'飞吻循环1','飞吻/飞吻循环1.mp4',0,'','1','machine','room1','飞吻/飞吻循环1.mp4',NOW()),
('m_room1_bmn029','video','public','飞吻','白毛女',1,'飞吻循环2','飞吻/飞吻循环2.mp4',0,'','1','machine','room1','飞吻/飞吻循环2.mp4',NOW()),
('m_room1_bmn030','video','public','飞吻','白毛女',1,'飞吻循环3','飞吻/飞吻循环3.mp4',0,'','1','machine','room1','飞吻/飞吻循环3.mp4',NOW()),
('m_room1_bmn031','video','public','飞吻','白毛女',1,'飞吻循环4','飞吻/飞吻循环4.mp4',0,'','1','machine','room1','飞吻/飞吻循环4.mp4',NOW()),
('m_room1_bmn032','video','public','飞吻','白毛女',1,'飞吻循环5','飞吻/飞吻循环5.mp4',0,'','1','machine','room1','飞吻/飞吻循环5.mp4',NOW()),
('m_room1_bmn033','video','public','飞吻','白毛女',1,'飞吻循环6','飞吻/飞吻循环6.mp4',0,'','1','machine','room1','飞吻/飞吻循环6.mp4',NOW()),
('m_room1_bmn034','video','public','飞吻','白毛女',1,'飞吻循环7','飞吻/飞吻循环7.mp4',0,'','1','machine','room1','飞吻/飞吻循环7.mp4',NOW()),
('m_room1_bmn035','video','public','','白毛女',1,'默认视频1','默认视频/默认视频1.mp4',0,'','1','machine','room1','默认视频/默认视频1.mp4',NOW()),
('m_room1_bmn036','video','public','','白毛女',1,'默认视频2','默认视频/默认视频2.mp4',0,'','1','machine','room1','默认视频/默认视频2.mp4',NOW()),
('m_room1_bmn037','video','public','','白毛女',1,'默认视频3','默认视频/默认视频3.mp4',0,'','1','machine','room1','默认视频/默认视频3.mp4',NOW()),
('m_room1_bmn038','video','public','','白毛女',1,'默认视频4','默认视频/默认视频4.mp4',0,'','1','machine','room1','默认视频/默认视频4.mp4',NOW()),
('m_room1_bmn039','video','public','','白毛女',1,'默认视频5','默认视频/默认视频5.mp4',0,'','1','machine','room1','默认视频/默认视频5.mp4',NOW()),
('m_room1_bmn040','video','public','','白毛女',1,'默认视频6','默认视频/默认视频6.mp4',0,'','1','machine','room1','默认视频/默认视频6.mp4',NOW()),
('m_room1_bmn041','video','public','','白毛女',1,'默认视频7','默认视频/默认视频7.mp4',0,'','1','machine','room1','默认视频/默认视频7.mp4',NOW()),
('m_room1_bmn042','video','public','','白毛女',1,'默认视频8','默认视频/默认视频8.mp4',0,'','1','machine','room1','默认视频/默认视频8.mp4',NOW()),
('m_room1_bmn043','video','public','','白毛女',1,'默认视频9','默认视频/默认视频9.mp4',0,'','1','machine','room1','默认视频/默认视频9.mp4',NOW()),
('m_room1_bmn044','video','public','','白毛女',1,'默认视频10','默认视频/默认视频10.mp4',0,'','1','machine','room1','默认视频/默认视频10.mp4',NOW()),
('m_room1_bmn045','video','public','','白毛女',1,'默认视频11','默认视频/默认视频11.mp4',0,'','1','machine','room1','默认视频/默认视频11.mp4',NOW()),
('m_room1_bmn046','video','public','','白毛女',1,'默认视频12','默认视频/默认视频12.mp4',0,'','1','machine','room1','默认视频/默认视频12.mp4',NOW()),
('m_room1_bmn047','video','public','','白毛女',1,'默认视频13','默认视频/默认视频13.mp4',0,'','1','machine','room1','默认视频/默认视频13.mp4',NOW()),
('m_room1_bmn048','video','public','','白毛女',1,'默认视频14','默认视频/默认视频14.mp4',0,'','1','machine','room1','默认视频/默认视频14.mp4',NOW()),
('m_room1_bmn049','video','public','','白毛女',1,'默认视频15','默认视频/默认视频15.mp4',0,'','1','machine','room1','默认视频/默认视频15.mp4',NOW()),
('m_room1_bmn050','video','public','','白毛女',1,'默认视频16','默认视频/默认视频16.mp4',0,'','1','machine','room1','默认视频/默认视频16.mp4',NOW()),
('m_room1_bmn051','video','public','','白毛女',1,'默认视频17','默认视频/默认视频17.mp4',0,'','1','machine','room1','默认视频/默认视频17.mp4',NOW()),
('m_room1_bmn052','video','public','','白毛女',1,'默认视频18','默认视频/默认视频18.mp4',0,'','1','machine','room1','默认视频/默认视频18.mp4',NOW());

-- 3. 新建默认播单模板
INSERT INTO lp_playlist_template (template_code,name,mode,status,created_at) VALUES ('room_playlist_1_bmn','白毛女新默认播单','public',1,NOW());
SET @tpl = LAST_INSERT_ID();

-- 4. 播单项：默认视频1-18 按数字顺序
INSERT INTO lp_playlist_template_item (template_id,asset_id,seq,loop_count,weight,start_offset_ms) VALUES
(@tpl,(SELECT id FROM lp_media_asset WHERE asset_code='m_room1_bmn035'),1,1,1,0),
(@tpl,(SELECT id FROM lp_media_asset WHERE asset_code='m_room1_bmn036'),2,1,1,0),
(@tpl,(SELECT id FROM lp_media_asset WHERE asset_code='m_room1_bmn037'),3,1,1,0),
(@tpl,(SELECT id FROM lp_media_asset WHERE asset_code='m_room1_bmn038'),4,1,1,0),
(@tpl,(SELECT id FROM lp_media_asset WHERE asset_code='m_room1_bmn039'),5,1,1,0),
(@tpl,(SELECT id FROM lp_media_asset WHERE asset_code='m_room1_bmn040'),6,1,1,0),
(@tpl,(SELECT id FROM lp_media_asset WHERE asset_code='m_room1_bmn041'),7,1,1,0),
(@tpl,(SELECT id FROM lp_media_asset WHERE asset_code='m_room1_bmn042'),8,1,1,0),
(@tpl,(SELECT id FROM lp_media_asset WHERE asset_code='m_room1_bmn043'),9,1,1,0),
(@tpl,(SELECT id FROM lp_media_asset WHERE asset_code='m_room1_bmn044'),10,1,1,0),
(@tpl,(SELECT id FROM lp_media_asset WHERE asset_code='m_room1_bmn045'),11,1,1,0),
(@tpl,(SELECT id FROM lp_media_asset WHERE asset_code='m_room1_bmn046'),12,1,1,0),
(@tpl,(SELECT id FROM lp_media_asset WHERE asset_code='m_room1_bmn047'),13,1,1,0),
(@tpl,(SELECT id FROM lp_media_asset WHERE asset_code='m_room1_bmn048'),14,1,1,0),
(@tpl,(SELECT id FROM lp_media_asset WHERE asset_code='m_room1_bmn049'),15,1,1,0),
(@tpl,(SELECT id FROM lp_media_asset WHERE asset_code='m_room1_bmn050'),16,1,1,0),
(@tpl,(SELECT id FROM lp_media_asset WHERE asset_code='m_room1_bmn051'),17,1,1,0),
(@tpl,(SELECT id FROM lp_media_asset WHERE asset_code='m_room1_bmn052'),18,1,1,0);

-- 5. 房间1 绑定新播单
UPDATE lp_room_binding SET playlist_template_id=@tpl WHERE room_id=1;

-- 6. 清空积压的旧关键词队列
-- DEL 由 redis-cli 单独执行