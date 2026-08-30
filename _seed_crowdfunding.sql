-- 众筹测试数据：4 个已成功（有收益）、2 个进行中、1 个未达标
-- 幂等：先清理旧的测试行（保留 id=1 真实测试项目）
DELETE FROM lp_crowdfunding_project WHERE id > 1;

-- ===== 已成功（status=1，已达标结算，有收益） =====
INSERT INTO lp_crowdfunding_project
(id, user_id, title, persona_name, description, cover_url, target_amount, raised_amount, supporter_count, deadline, status, persona_id, created_at, updated_at)
VALUES
(2, 2, 'Malaysia Sweetheart Aina', 'Aina',
 'Warm Malaysian girl who speaks English, Malay and a little Cantonese. Loves street food and late-night chats.',
 './Girls_files/image1-510c9159fd51d7802ef2de3bc1e6edcbd5c5dc0a87ef225a5871143c46eaba02.webp',
 5000.00, 6520.00, 38, '2026-08-05 23:59:00', 1, NULL, '2026-07-18 10:00:00', '2026-08-05 23:59:00'),

(3, 2, 'Cyber Idol Nova', 'Nova',
 'A neon-city cyber idol with futuristic charm. Your story in the night city starts with her.',
 './Anime_files/anime-9b5de57569565ea21979d08aed32c1d6ee5c1132f29a37ffe6bb2de6750b5fee.webp',
 8000.00, 9450.00, 52, '2026-07-30 23:59:00', 1, NULL, '2026-07-10 09:00:00', '2026-07-30 23:59:00'),

(4, 2, 'Girl Next Door Mei Ling', 'Mei Ling',
 'Sweet KL girl next door. Coffee, rain and cozy conversations.',
 './Girls_files/image5-bd5309d84f7f2bbd60a70e38d705f827c63611034bb3cce450b24d5ada8e4b4d.webp',
 3000.00, 3120.00, 21, '2026-08-08 23:59:00', 1, NULL, '2026-07-22 14:00:00', '2026-08-08 23:59:00'),

(5, 2, 'Anime Waifu Sakura', 'Sakura',
 'Classic anime-style waifu with pink hair and endless affection.',
 './Anime_files/center-29b7ed90bdd63e656b7d00286202b2a92809b3ed3d093bc550390a12ec8c308a.webp',
 12000.00, 15600.00, 74, '2026-07-25 23:59:00', 1, NULL, '2026-07-05 20:00:00', '2026-07-25 23:59:00'),

-- ===== 进行中（status=0） =====
(6, 2, 'Island Babe Lina', 'Lina',
 'Sun-kissed island girl, always ready for the next beach adventure.',
 './Anime_files/female-d92a38dacdff8a7c32518c2dc3c3c9a4372fdbf8b47dd2d50f1d87d55137602b.webp',
 10000.00, 4200.00, 18, '2026-08-25 23:59:00', 0, NULL, '2026-08-10 10:00:00', '2026-08-14 12:00:00'),

(7, 2, 'Office Lady Yuki', 'Yuki',
 'Elegant office lady by day, gentle companion by night.',
 './Anime_files/image2-22de8af2c140eb94f353877688d55dc44a7d62accc8f9667213888e17a511d85.webp',
 6000.00, 900.00, 5, '2026-08-20 23:59:00', 0, NULL, '2026-08-12 09:00:00', '2026-08-14 12:00:00'),

-- ===== 未达标（status=2，已全额退款） =====
(8, 2, 'Rock Chick Vivi', 'Vivi',
 'Wild rocker girl with tattoos and a golden heart. Did not reach her goal - all backers refunded.',
 './Guys_files/image5-54c6e2430dd47e382f4c0992bfe35c6f8b5d7337f4d233a6300f1949d519eaf9.webp',
 5000.00, 800.00, 3, '2026-07-28 23:59:00', 2, NULL, '2026-07-08 11:00:00', '2026-07-28 23:59:00');
