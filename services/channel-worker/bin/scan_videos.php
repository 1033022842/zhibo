<?php
/**
 * 视频扫描入库脚本
 * 扫描 视频成品/ 下所有 mp4，自动匹配 20 大类关键词，写入 lp_media_asset
 * 用法: php scan_videos.php
 * 输出: 已入库清单 + 待人工标注清单
 */

$videoDir = str_replace('/', DIRECTORY_SEPARATOR, dirname(__DIR__, 3) . '/视频成品'); // 项目根目录/视频成品
$persona = '白毛女';
$scene_type = 'public';  // 新架构统一 public

// ============================================================
// 20 大类关键词匹配表（按 KEYWORD_CATEGORIES.md）
// 顺序: 大类 => [匹配用的子关键词]
// ============================================================
$categoryMap = [
    '比心'   => ['比心', '比心连击', '双手比关注', '比666', '比晚安', 'OK手势'],
    '飞吻'   => ['飞吻'],
    '摸头杀'  => ['摸头杀', '歪头杀', 'POV摸头杀'],
    '撩发'   => ['撩发至耳后', '撩拨发际', '整理头发', '整理衣领', '整理形象', '整理袖口', '擦汗', '单手扎马尾', '戴摘眼镜', '动作习惯', '撩发'],
    '害羞'   => ['遮挡偷看', '双手捂脸', '害羞躲闪', '掩耳盗铃', '频繁闪躲', '害羞'],
    '傲娇'   => ['傲娇', '鼓腮帮子', '假装生气', '假装委屈', '抱胸转头', '轻蔑斜视', '斜视偷瞄', '蔑视', '翻白眼', '吃醋', '鼓起脸颊', '跺脚', '嘟嘴'],
    '变脸'   => ['冷脸转笑', '笑转冷脸', '极度惊恐', '神经质颤抖', '死盯爆发', '惊喜瞬间', '捂嘴惊喜', '变脸', '惊恐', '惊喜', '情绪崩坏'],
    '开心'   => ['拍腿大笑', '捂嘴笑', '举手欢呼', '开怀大笑', '欢呼雀跃', '微笑摇摆', '调皮眨眼', '握拳加油', '双手点赞', '挑眉调侃', '对着镜头打气', '开心', '大笑', '欢呼'],
    '待机'   => ['标准呼吸', '平静呼吸', '轻快呼吸', '沉重呼吸', '屏息', '急促呼吸', '注视镜头', '待机', '呼吸'],
    '专注'   => ['玩手机', '看书', '托腮看弹幕', '手指屏幕念弹幕', '看手机后看镜头', '指点下方', '专注', '低头玩手机', '看弹幕'],
    '日常'   => ['伸懒腰', '打哈欠', '揉眼睛', '喝水', '睡眼惺忪', '深夜困倦', '晕倒', '倒床上', '日常', '犯困'],
    '投喂'   => ['投喂', '喂食', '物理投喂'],
    '拥抱'   => ['模拟拥抱', '霸道环抱', '拥抱'],
    '安慰'   => ['物理安慰', '手掌贴镜', '抹泪', '擦眼泪', '安慰'],
    '拉扯'   => ['物理拉扯', '拉扯衣领', '拉扯衣角', '遮挡抢夺', '物理压迫', '拉扯', '向下抓衣角', '双手合十'],
    '诱惑'   => ['手指滑锁骨', '勾内衣边', '手掌腿部移动', '指尖引诱', '勾手', '颈部锁骨', '身体前倾', '束发散发', '轻抿嘴唇', '坐姿重心切换', '由远及近', '极近贴镜', '诱惑'],
    '侧颜'   => ['侧颜45度', '背身回头', '头部360度扫描', '复杂光影走位', '俯身看向镜头', '侧颜'],
    '感谢'   => ['感谢', '鞠躬', '双手合十', '点头致谢', '比冲手势', '倒计时', '伸手 3', '321', '感谢'],
    '话术'   => ['情感告白', '引导关注', '引导打赏', '不准看', '今天不去加班', '盯着我', '陪你喝', '不是特意', '没见过', '笨蛋', '罐头打不开', '低级错误', '不嫌弃', '对不起', '除了我', '别乱动', '躲不掉', '别盯着', '话术', '台词'],
    '出入场'  => ['进场', '离场', '打招呼', '挥手', '勾手', '点头', '摇头', '嘘声', '禁忌暗示', '手指嘘声', '镜头雾化哈气', '社交反馈', '出入场'],
];

// ============================================================
// 数据库连接
// ============================================================
$dsn = 'mysql:host=127.0.0.1;port=3306;dbname=live_platform;charset=utf8mb4';
$pdo = new PDO($dsn, 'root', 'root', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

// 清空旧的同 persona 数据（可选，谨慎使用）
// $pdo->exec("DELETE FROM lp_media_asset WHERE persona = '{$persona}'");

// 获取当前最大 asset_code 序号（按大类）
$stmt = $pdo->query("SELECT asset_code FROM lp_media_asset WHERE persona = '{$persona}' ORDER BY id DESC");
$existingCodes = $stmt->fetchAll(PDO::FETCH_COLUMN);
$seqMap = []; // 大类 => 当前最大序号
foreach ($existingCodes as $code) {
    // 格式: 白毛女-比心-001
    if (preg_match('/^' . preg_quote($persona, '/') . '-(\S+)-(\d+)$/', $code, $m)) {
        $cat = $m[1];
        $num = (int)$m[2];
        if (!isset($seqMap[$cat]) || $num > $seqMap[$cat]) {
            $seqMap[$cat] = $num;
        }
    }
}

// ============================================================
// 扫描视频
// ============================================================
$files = glob($videoDir . '/*.mp4');
$totalFiles = count($files);

$insertStmt = $pdo->prepare(
    "INSERT INTO lp_media_asset (asset_code, asset_type, scene_type, title, file_url, duration_ms, keywords, persona, weight, status, created_at)
     VALUES (:code, 'video', :scene_type, :title, :file_url, 0, :keywords, :persona, 1, 1, NOW())"
);

$matched = [];   // 已入库
$unmatched = []; // 待人工标注

foreach ($files as $filePath) {
    $fileName = basename($filePath);
    // 去掉 .mp4 后缀作为标题
    $title = mb_substr($fileName, 0, -4);

    // 匹配关键词
    $category = matchCategory($title, $categoryMap);

    if ($category === null) {
        $unmatched[] = $fileName;
        continue;
    }

    // 生成 asset_code
    if (!isset($seqMap[$category])) {
        $seqMap[$category] = 0;
    }
    $seqMap[$category]++;
    $seq = str_pad((string)$seqMap[$category], 3, '0', STR_PAD_LEFT);
    $assetCode = "{$persona}-{$category}-{$seq}";

    // 相对路径存储（ChannelWorker config 拼前缀）
    $fileUrl = '视频成品/' . $fileName;

    try {
        $insertStmt->execute([
            'code'       => $assetCode,
            'scene_type' => $scene_type,
            'title'      => $title,
            'file_url'   => $fileUrl,
            'keywords'   => $category,
            'persona'    => $persona,
        ]);
        $matched[] = ['file' => $fileName, 'category' => $category, 'code' => $assetCode];
    } catch (\PDOException $e) {
        // asset_code 唯一键冲突 = 已存在
        if (str_contains($e->getMessage(), 'Duplicate entry')) {
            $matched[] = ['file' => $fileName, 'category' => $category, 'code' => $assetCode, 'note' => '已存在'];
        } else {
            echo "  ERROR: {$fileName} -> {$e->getMessage()}\n";
        }
    }
}

// ============================================================
// 输出结果
// ============================================================
echo "========================================\n";
echo "视频扫描入库完成\n";
echo "总文件数: {$totalFiles}\n";
echo "已入库: " . count($matched) . "\n";
echo "待标注: " . count($unmatched) . "\n";
echo "========================================\n";

if ($matched) {
    echo "\n--- 已入库清单 ---\n";
    foreach ($matched as $m) {
        $note = isset($m['note']) ? " ({$m['note']})" : '';
        echo "  [{$m['category']}] {$m['file']} -> {$m['code']}{$note}\n";
    }
}

if ($unmatched) {
    echo "\n--- 待人工标注清单 (" . count($unmatched) . " 个) ---\n";
    foreach ($unmatched as $f) {
        echo "  {$f}\n";
    }
}

// ============================================================
// 匹配函数：遍历 20 大类，找到文件名中包含的子关键词
// ============================================================
function matchCategory(string $title, array $categoryMap): ?string
{
    foreach ($categoryMap as $category => $keywords) {
        foreach ($keywords as $kw) {
            if (mb_stripos($title, $kw) !== false) {
                return $category;
            }
        }
    }
    return null;
}
