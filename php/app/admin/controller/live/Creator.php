<?php
/**
 * 创作审核 - 独立页面（无需前端编译）
 * 访问: /admin/live.Creator/index
 */

declare(strict_types=1);

namespace app\admin\controller\live;

use app\common\controller\Backend;
use app\admin\model\live\CreatorSubmission as CreatorModel;
use app\live\service\CreatorService;

final class Creator extends Backend
{
    protected object $model;

    protected array $noNeedLogin = ['index', 'approve', 'reject', 'publish', 'detailJson', 'download', 'removeRecord', 'linkPersona'];

    // 飘页访问守卫：URL ?_key= 首次校验后走 Cookie（密钥 .env ADMIN_FLOAT_KEY）
    protected array $middleware = [
        \app\admin\middleware\FloatAuth::class,
    ];

    private const STATUS_MAP = [0 => '待审核', 1 => '已上线', 2 => '已驳回'];

    private const PUBLISH_MAP = ['live' => '直播角色', 'chat' => '普通对话角色', 'drama' => '短剧'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new CreatorModel();
    }

    /**
     * 创作提交列表页
     */
    public function index(): void
    {
        $keyword = $this->request->param('search', '');
        $status  = $this->request->param('status', '');

        $query = $this->model->alias('c')
            ->join('lp_user u', 'u.id = c.user_id', 'LEFT')
            ->field('c.*, u.nickname, u.user_no');

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('c.persona_name', 'like', "%{$keyword}%")
                  ->whereOr('u.nickname', 'like', "%{$keyword}%");
            });
        }
        if ($status !== '') {
            $query->where('c.status', (int)$status);
        }

        $list = $query->order('c.id', 'desc')->paginate(15);

        $stats = [
            'total'    => (int)$this->model->count(),
            'pending'  => (int)$this->model->where('status', 0)->count(),
            'online'   => (int)$this->model->where('status', 1)->count(),
            'rejected' => (int)$this->model->where('status', 2)->count(),
        ];

        // 收入：角色作品按角色礼物收入统计；短剧作品按解锁收入统计；再统计提交用户累计到手收益
        $items       = $list->items();
        $personaIds  = [];
        $userIds     = [];
        $shortIds    = [];
        $contentIds  = [];
        foreach ($items as $row) {
            $pid = (int)($row['persona_id'] ?? 0);
            if ($pid > 0 && (string)($row['publish_type'] ?? '') !== 'drama') {
                $personaIds[] = $pid;
            }
            $sid = (int)($row['short_id'] ?? 0);
            if ($sid > 0) {
                $shortIds[] = $sid;
            }
            $cid = (int)($row['ai_content_id'] ?? 0);
            if ($cid > 0) {
                $contentIds[] = $cid;
            }
            $uid = (int)($row['user_id'] ?? 0);
            if ($uid > 0) {
                $userIds[] = $uid;
            }
        }
        $rate          = $this->commissionRate();
        $personaIncome = $this->personaIncomeMap($personaIds, $rate);
        $shortIncome   = $this->shortIncomeMap($shortIds);
        $contentIncome = $this->contentIncomeMap($contentIds);
        $userIncome    = $this->userIncomeMap($userIds);

        $html = $this->renderPage($list, $stats, $personaIncome, $userIncome, $rate, $shortIncome, $contentIncome);
        response($html)->send();
        exit;
    }

    /**
     * 审核通过 (AJAX)：通过后按类型自动上架到对应专区（直播→直播间、聊天→聊天内容、短剧→短剧）
     */
    public function approve(): void
    {
        $id = $this->request->param('id/d', 0);
        $item = $this->model->find($id);
        if (!$item) { $this->error('记录不存在'); return; }
        if ((int)$item->status !== 0) { $this->error('不在待审核状态'); return; }

        $item->status      = 1;
        $item->reviewed_at = date('Y-m-d H:i:s');
        $item->updated_at  = date('Y-m-d H:i:s');
        $item->save();

        try {
            $r = (new CreatorService())->publish($id);
            $this->success('审核通过，已上架站内（' . $this->publishResultMsg($r) . '）');
        } catch (\Throwable $e) {
            $this->success('审核通过，但上架站内失败：' . $e->getMessage());
        }
    }

    /**
     * 上架站内 (AJAX)：给已上线但还没上架的作品补上（按类型落到对应专区，幂等）
     */
    public function publish(): void
    {
        $id = $this->request->param('id/d', 0);
        $item = $this->model->find($id);
        if (!$item) { $this->error('记录不存在'); return; }

        try {
            $r = (new CreatorService())->publish($id);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return;
        }
        $this->success('已上架站内（' . $this->publishResultMsg($r) . '）');
    }

    /**
     * 上架结果文案：按类型只显示落到自己专区的成果
     */
    private function publishResultMsg(array $r): string
    {
        $parts = [];
        if (!empty($r['persona_id'])) {
            $parts[] = '角色 #' . (int) $r['persona_id'];
        }
        if (!empty($r['room_id'])) {
            $parts[] = '直播间 #' . (int) $r['room_id'];
        }
        if (!empty($r['short_id'])) {
            $parts[] = '短剧 #' . (int) $r['short_id'];
        }
        if (!empty($r['content_id'])) {
            $parts[] = '聊天内容 #' . (int) $r['content_id'];
        }
        return $parts ? implode('，', $parts) : '无有效内容，未上架';
    }

    /**
     * 审核驳回 (AJAX)
     */
    public function reject(): void
    {
        $id = $this->request->param('id/d', 0);
        $reason = trim((string)$this->request->param('reason', ''));
        if ($reason === '') { $this->error('请填写驳回原因'); return; }

        $item = $this->model->find($id);
        if (!$item) { $this->error('记录不存在'); return; }
        if ((int)$item->status !== 0) { $this->error('不在待审核状态'); return; }

        $item->status        = 2;
        $item->reject_reason = $reason;
        $item->reviewed_at   = date('Y-m-d H:i:s');
        $item->updated_at    = date('Y-m-d H:i:s');
        $item->save();
        $this->success('已驳回');
    }

    /**
     * 创作详情 JSON（给弹窗用）
     */
    public function detailJson(): void
    {
        $id = $this->request->param('id/d', 0);
        $item = $this->model->find($id);
        if (!$item) {
            $this->error('记录不存在');
            return;
        }

        $user = \think\facade\Db::connect('live_mysql')
            ->table('lp_user')
            ->where('id', (int)$item->user_id)
            ->field('nickname, user_no')
            ->find();

        $materials = json_decode((string)$item->materials, true);
        if (!is_array($materials)) {
            $materials = [];
        }

        $episodes = json_decode((string)($item->episodes ?? ''), true);
        if (!is_array($episodes)) {
            $episodes = [];
        }

        // 收入信息：短剧按解锁收入、聊天作品按好感度/视频解锁收入、直播作品按角色礼物收入；外加提交用户累计到手收益
        $rate         = $this->commissionRate();
        $personaId    = (int)($item->persona_id ?? 0);
        $shortId      = (int)($item->short_id ?? 0);
        $contentId    = (int)($item->ai_content_id ?? 0);
        $personaInc   = $personaId > 0 ? ($this->personaIncomeMap([$personaId], $rate)[$personaId] ?? null) : null;
        $shortInc     = $shortId > 0 ? ($this->shortIncomeMap([$shortId])[$shortId] ?? null) : null;
        $contentInc   = $contentId > 0 ? ($this->contentIncomeMap([$contentId])[$contentId] ?? null) : null;
        $authorIncome = $this->userIncomeMap([(int)$item->user_id])[(int)$item->user_id] ?? 0.0;

        // 可关联的角色列表（供后台手动关联）
        $personas = \think\facade\Db::connect('live_mysql')
            ->table('lp_persona p')
            ->leftJoin('lp_user u', 'u.id = p.user_id')
            ->field('p.id, p.name, p.user_id, u.nickname')
            ->order('p.id', 'desc')
            ->select()
            ->toArray();
        $personaOptions = array_map(static function (array $p): array {
            return [
                'id'         => (int) $p['id'],
                'name'       => (string) $p['name'],
                'user_id'    => (int) $p['user_id'],
                'nickname'   => (string) ($p['nickname'] ?? ''),
            ];
        }, $personas);

        $this->success('', [
            'id'             => (int)$item->id,
            'user_id'        => (int)$item->user_id,
            'nickname'       => $user['nickname'] ?? '',
            'user_no'        => $user['user_no'] ?? '',
            'persona_name'   => (string)$item->persona_name,
            'description'    => (string)$item->description,
            'tags'           => self::splitList((string)$item->tags),
            'style'          => (string)$item->style,
            'gender'         => (string)$item->gender,
            'age_range'      => (string)$item->age_range,
            'language'       => (string)$item->language,
            'personality'    => self::splitList((string)$item->personality),
            'voice_style'    => (string)$item->voice_style,
            'publish_type'   => (string)$item->publish_type,
            'publish_text'   => self::PUBLISH_MAP[$item->publish_type] ?? (string)$item->publish_type,
            'is_adult'       => (int)$item->is_adult,
            'highlights'     => (string)$item->highlights,
            'reference_url'  => (string)$item->reference_url,
            'cover_url'      => (string)$item->cover_url,
            'materials'      => $materials,
            'episodes'       => $episodes,
            'free_episodes'  => (int)($item->free_episodes ?? 0),
            'status'         => (int)$item->status,
            'status_text'    => self::STATUS_MAP[$item->status] ?? '未知',
            'reject_reason'  => (string)$item->reject_reason,
            'created_at'     => (string)$item->created_at,
            'reviewed_at'    => (string)$item->reviewed_at,
            'persona_id'     => $personaId,
            'short_id'       => $shortId,
            'content_id'     => $contentId,
            'income'         => [
                'rate'         => $rate,
                'persona'      => $personaInc,
                'short'        => $shortInc,
                'content'      => $contentInc,
                'author_total' => $authorIncome,
            ],
            'persona_options' => $personaOptions,
        ]);
    }

    /**
     * 删除创作提交 (AJAX)
     */
    public function removeRecord(): void
    {
        $id = $this->request->param('id/d', 0);
        $item = $this->model->find($id);
        if (!$item) { $this->error('记录不存在'); return; }

        $item->delete();
        $this->success('已删除');
    }

    /**
     * 关联/解除关联角色：作品上线后在后台把提交与角色(lp_persona)绑定，用于统计作品收入
     */
    public function linkPersona(): void
    {
        $id        = $this->request->param('id/d', 0);
        $personaId = $this->request->param('persona_id/d', 0);

        $item = $this->model->find($id);
        if (!$item) { $this->error('记录不存在'); return; }

        if ($personaId > 0) {
            $exists = \think\facade\Db::connect('live_mysql')
                ->table('lp_persona')->where('id', $personaId)->value('id');
            if (!$exists) { $this->error('角色不存在'); return; }
        }

        $item->persona_id = $personaId;
        $item->updated_at = date('Y-m-d H:i:s');
        $item->save();
        $this->success($personaId > 0 ? '已关联角色' : '已解除关联');
    }

    /**
     * 下载创作资料：打包 ZIP（info.json + 全部素材文件）
     */
    public function download(): void
    {
        $id = $this->request->param('id/d', 0);
        $item = $this->model->find($id);
        if (!$item) { http_response_code(404); echo '记录不存在'; exit; }

        $materials = json_decode((string) $item->materials, true);
        if (!is_array($materials)) {
            $materials = [];
        }
        $episodes = json_decode((string) ($item->episodes ?? ''), true);
        if (!is_array($episodes)) {
            $episodes = [];
        }

        $user = \think\facade\Db::connect('live_mysql')
            ->table('lp_user')
            ->where('id', (int) $item->user_id)
            ->field('nickname, user_no')
            ->find();

        $info = [
            'id'            => (int) $item->id,
            'user_id'       => (int) $item->user_id,
            'nickname'      => $user['nickname'] ?? '',
            'user_no'       => $user['user_no'] ?? '',
            'persona_name'  => (string) $item->persona_name,
            'description'   => (string) $item->description,
            'tags'          => (string) $item->tags,
            'style'         => (string) $item->style,
            'gender'        => (string) $item->gender,
            'age_range'     => (string) $item->age_range,
            'language'      => (string) $item->language,
            'personality'   => (string) $item->personality,
            'voice_style'   => (string) $item->voice_style,
            'publish_type'  => (string) $item->publish_type,
            'is_adult'      => (int) $item->is_adult,
            'highlights'    => (string) $item->highlights,
            'reference_url' => (string) $item->reference_url,
            'cover_url'     => (string) $item->cover_url,
            'status'        => (int) $item->status,
            'reject_reason' => (string) $item->reject_reason,
            'created_at'    => (string) $item->created_at,
            'reviewed_at'   => (string) $item->reviewed_at,
            'materials'     => $materials,
            'episodes'      => $episodes,
            'free_episodes' => (int) ($item->free_episodes ?? 0),
        ];

        if (!class_exists('\ZipArchive')) {
            http_response_code(500);
            echo '服务器未启用 zip 扩展，无法打包下载';
            exit;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'creator_');
        $zip = new \ZipArchive();
        if ($zip->open($tmp, \ZipArchive::OVERWRITE) !== true) {
            http_response_code(500);
            echo '创建压缩包失败';
            exit;
        }

        $zip->addFromString('info.json', (string) json_encode($info, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

        $used = [];
        foreach ($materials as $i => $m) {
            $url  = (string) ($m['url'] ?? '');
            $name = basename(str_replace('\\', '/', trim((string) ($m['name'] ?? ''))));
            if ($name === '') {
                $name = self::fileNameFromUrl($url, 'asset-' . ($i + 1));
            }
            $this->zipAddFromUrl($zip, $used, 'assets/' . $name, $url);
        }

        // 短剧剧集：每集视频 + 每集封面
        foreach ($episodes as $ep) {
            $no = (int) ($ep['episode_no'] ?? 0);
            $this->zipAddFromUrl($zip, $used, 'episodes/EP' . $no . '-' . self::fileNameFromUrl((string) ($ep['video_url'] ?? ''), 'video'), (string) ($ep['video_url'] ?? ''));
            $this->zipAddFromUrl($zip, $used, 'episodes/EP' . $no . '-cover-' . self::fileNameFromUrl((string) ($ep['poster'] ?? ''), 'cover'), (string) ($ep['poster'] ?? ''));
        }

        $zip->close();

        $filename = 'creation-' . $id . '-' . date('YmdHis') . '.zip';
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . (string) filesize($tmp));
        readfile($tmp);
        @unlink($tmp);
        exit;
    }

    /**
     * 读取素材内容：优先本地文件，其次 HTTP（15s 超时）
     */
    private function fetchMaterial(string $url): ?string
    {
        $path = (string) (parse_url($url, PHP_URL_PATH) ?: $url);
        $rel  = ltrim($path, '/');

        if ($rel !== '') {
            foreach ([public_path() . $rel, root_path() . 'public' . DIRECTORY_SEPARATOR . $rel] as $local) {
                if (is_file($local)) {
                    $bin = @file_get_contents($local);
                    if ($bin !== false) {
                        return $bin;
                    }
                }
            }
        }

        // 非 http 地址（如 /storage/xxx）兜底：按当前域名拼成完整地址再取
        if (!preg_match('#^https?://#i', $url)) {
            $url = rtrim($this->request->domain(), '/') . '/' . $rel;
        }

        $ctx = stream_context_create([
            'http' => ['timeout' => 15, 'follow_location' => 1],
            'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);
        $bin = @file_get_contents($url, false, $ctx);
        return $bin === false ? null : $bin;
    }

    /**
     * 把某个素材 URL 的内容写入 ZIP（先本地后 HTTP），同名自动加序号前缀
     */
    private function zipAddFromUrl(\ZipArchive $zip, array &$used, string $entry, string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }
        $bin = $this->fetchMaterial($url);
        if ($bin === null) {
            return false;
        }

        $entry = ltrim(str_replace('\\', '/', $entry), '/');
        if ($entry === '') {
            $entry = 'asset';
        }
        $dir  = dirname($entry);
        $base = basename($entry);
        $new  = $entry;
        $k    = 1;
        while (isset($used[$new])) {
            $new = ($dir !== '.' && $dir !== '' ? $dir . '/' : '') . $k . '_' . $base;
            $k++;
        }
        $used[$new] = true;
        $zip->addFromString($new, $bin);
        return true;
    }

    /**
     * 从 URL 取文件名（取 URL path 的 basename），取不到用兜底名
     */
    private static function fileNameFromUrl(string $url, string $fallback): string
    {
        $url  = trim($url);
        $path = $url !== '' ? (string) (parse_url($url, PHP_URL_PATH) ?: $url) : '';
        $name = basename(str_replace('\\', '/', $path));
        return $name !== '' ? $name : $fallback;
    }

    /**
     * 平台礼物抽成比例（0~0.9，默认 0.30）
     */
    private function commissionRate(): float
    {
        $rate = (float) \app\live\service\WalletService::config('short_commission_rate', (string) \app\live\service\WalletService::config('gift_commission_rate', '0.30'));
        return max(0.0, min(0.9, $rate));
    }

    /**
     * 角色礼物收入：按角色汇总其房间收到的礼物
     * @return array<int,array{gross:float,net:float,donors:int}>
     */
    private function personaIncomeMap(array $personaIds, float $rate): array
    {
        $personaIds = array_values(array_unique(array_filter($personaIds)));
        if (!$personaIds) {
            return [];
        }

        $rows = \think\facade\Db::connect('live_mysql')
            ->table('lp_room r')
            ->join('lp_gift_order go', 'go.room_id = r.id')
            ->whereIn('r.persona_id', $personaIds)
            ->field([
                'r.persona_id',
                'SUM(go.total_price) AS gross',
                'COUNT(DISTINCT go.user_id) AS donors',
            ])
            ->group('r.persona_id')
            ->select()
            ->toArray();

        $map = [];
        foreach ($rows as $row) {
            $gross = (float) $row['gross'];
            $map[(int) $row['persona_id']] = [
                'gross'  => $gross,
                'net'    => round($gross * (1 - $rate), 2),
                'donors' => (int) $row['donors'],
            ];
        }
        return $map;
    }

    /**
     * 用户累计到手收益：钱包流水收入项汇总
     * （礼物 gift_income + 短剧 short_income + 聊天好感度/视频解锁 affection_income/media_income，与收入排行榜同口径）
     * @return array<int,float>
     */
    private function userIncomeMap(array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter($userIds)));
        if (!$userIds) {
            return [];
        }

        $rows = \think\facade\Db::connect('live_mysql')
            ->table('lp_wallet_ledger')
            ->whereIn('biz_type', ['gift_income', 'short_income', 'affection_income', 'media_income'])
            ->where('direction', 1)
            ->whereIn('user_id', $userIds)
            ->field(['user_id', 'SUM(amount) AS income'])
            ->group('user_id')
            ->select()
            ->toArray();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['user_id']] = (float) $row['income'];
        }
        return $map;
    }

    /**
     * 短剧解锁收入：按短剧汇总
     *   毛额 = 买家实付（short_unlock 支出）之和，净额 = 作者到手（short_income 收入）之和
     * @return array<int,array{gross:float,net:float,buyers:int}>
     */
    private function shortIncomeMap(array $shortIds): array
    {
        $shortIds = array_values(array_unique(array_filter($shortIds)));
        if (!$shortIds) {
            return [];
        }

        $db = \think\facade\Db::connect('live_mysql');

        $episodes = $db->table('lp_short_episode')
            ->whereIn('short_id', $shortIds)
            ->field(['id', 'short_id'])
            ->select()
            ->toArray();
        if (!$episodes) {
            return [];
        }

        $epToShort = [];
        foreach ($episodes as $ep) {
            $epToShort[(int) $ep['id']] = (int) $ep['short_id'];
        }

        $rows = $db->table('lp_wallet_ledger')
            ->whereIn('biz_type', ['short_unlock', 'short_income'])
            ->whereIn('biz_id', array_keys($epToShort))
            ->field('biz_type, biz_id, SUM(amount) AS amt, COUNT(DISTINCT user_id) AS buyers')
            ->group('biz_type, biz_id')
            ->select()
            ->toArray();

        $map = [];
        foreach ($rows as $row) {
            $sid = $epToShort[(int) $row['biz_id']] ?? 0;
            if ($sid <= 0) {
                continue;
            }
            if (!isset($map[$sid])) {
                $map[$sid] = ['gross' => 0.0, 'net' => 0.0, 'buyers' => 0];
            }
            if ((string) $row['biz_type'] === 'short_unlock') {
                $map[$sid]['gross']  += (float) $row['amt'];
                $map[$sid]['buyers'] += (int) $row['buyers'];
            } else {
                $map[$sid]['net'] += (float) $row['amt'];
            }
        }
        return $map;
    }

    /**
     * 聊天作品收入：按 lp_ai_content 汇总
     *   毛额 = 买家实付（好感度购买 affection_buy biz_id=content_id + 视频解锁 media_unlock 经 lp_ai_media 归到 content_id）
     *   净额 = 作者到手（affection_income / media_income，biz_id=content_id）
     * @return array<int,array{gross:float,net:float,orders:int}>
     */
    private function contentIncomeMap(array $contentIds): array
    {
        $contentIds = array_values(array_unique(array_filter($contentIds)));
        if (!$contentIds) {
            return [];
        }

        $db  = \think\facade\Db::connect('live_mysql');
        $map = [];

        // 毛额：好感度购买（biz_id=content_id）
        $rows = $db->table('lp_wallet_ledger')
            ->where('biz_type', 'affection_buy')->where('direction', 2)
            ->whereIn('biz_id', $contentIds)
            ->field(['biz_id', 'SUM(amount) AS amt', 'COUNT(*) AS n'])
            ->group('biz_id')->select()->toArray();
        foreach ($rows as $r) {
            $cid = (int) $r['biz_id'];
            $map[$cid]['gross']  = (float) $r['amt'];
            $map[$cid]['orders'] = (int) $r['n'];
        }

        // 毛额：视频解锁（biz_id=media_id，经 lp_ai_media 归到 content_id）
        $rows = $db->table('lp_wallet_ledger l')
            ->join('lp_ai_media m', 'm.id = l.biz_id')
            ->where('l.biz_type', 'media_unlock')->where('l.direction', 2)
            ->whereIn('m.content_id', $contentIds)
            ->field(['m.content_id AS cid', 'SUM(l.amount) AS amt', 'COUNT(*) AS n'])
            ->group('m.content_id')->select()->toArray();
        foreach ($rows as $r) {
            $cid = (int) $r['cid'];
            $map[$cid]['gross']  = ($map[$cid]['gross'] ?? 0) + (float) $r['amt'];
            $map[$cid]['orders'] = ($map[$cid]['orders'] ?? 0) + (int) $r['n'];
        }

        // 净额：作者到手（affection_income / media_income，biz_id=content_id）
        $rows = $db->table('lp_wallet_ledger')
            ->whereIn('biz_type', ['affection_income', 'media_income'])->where('direction', 1)
            ->whereIn('biz_id', $contentIds)
            ->field(['biz_id', 'SUM(amount) AS amt'])
            ->group('biz_id')->select()->toArray();
        foreach ($rows as $r) {
            $cid = (int) $r['biz_id'];
            $map[$cid]['net'] = (float) $r['amt'];
        }

        foreach ($map as $cid => $v) {
            $map[$cid] = [
                'gross'  => (float) ($v['gross'] ?? 0),
                'net'    => (float) ($v['net'] ?? 0),
                'orders' => (int) ($v['orders'] ?? 0),
            ];
        }
        return $map;
    }

    /**
     * 数字展示：整数不带小数，否则保留两位
     */
    private static function fmtNum(float $n): string
    {
        return $n == floor($n) ? number_format($n) : number_format($n, 2);
    }

    /**
     * 逗号分隔字符串 -> 数组
     */
    private static function splitList(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }
        return array_values(array_filter(array_map('trim', explode(',', $value)), static fn($s) => $s !== ''));
    }

    /**
     * 作品是否已上架到对应专区：短剧=short_id、聊天=ai_content_id、直播=persona_id
     */
    private static function isPublished(array $row): bool
    {
        $type = (string) ($row['publish_type'] ?? '');
        if ($type === 'drama') {
            return (int) ($row['short_id'] ?? 0) > 0;
        }
        if ($type === 'live') {
            return (int) ($row['persona_id'] ?? 0) > 0;
        }
        return (int) ($row['ai_content_id'] ?? 0) > 0;
    }

    private function renderPage($list, array $stats, array $personaIncome = [], array $userIncome = [], float $rate = 0.3, array $shortIncome = [], array $contentIncome = []): string
    {
        $statusMap  = self::STATUS_MAP;
        $publishMap = self::PUBLISH_MAP;

        $rows = '';
        foreach ($list->items() as $row) {
            $st     = $statusMap[$row['status']] ?? '未知';
            $stNum  = (int)$row['status'];
            $name   = htmlspecialchars($row['nickname'] ?: '未命名');
            $no     = htmlspecialchars((string)($row['user_no'] ?? ''));
            $pname  = htmlspecialchars((string)$row['persona_name']);
            $ptype  = htmlspecialchars($publishMap[$row['publish_type']] ?? (string)$row['publish_type']);
            $adult  = (int)$row['is_adult'] === 1 ? '<span class="adult-chip">18+</span>' : '<span class="na">否</span>';
            $time   = $row['created_at'] ? substr((string)$row['created_at'], 0, 16) : '-';
            $cover  = $row['cover_url']
                ? '<div class="thumb"><img src="' . htmlspecialchars((string)$row['cover_url']) . '" alt="" onerror="this.style.display=\'none\'"></div>'
                : '<div class="thumb thumb-empty"></div>';

            $actions = '';
            if ($stNum === 0) {
                $actions = <<<ACT
                <button class="op-btn op-ok" onclick="doApprove({$row['id']})">通过</button>
                <button class="op-btn op-no" onclick="showReject({$row['id']})">驳回</button>
                ACT;
            } elseif ($stNum === 1 && !self::isPublished($row)) {
                $actions = <<<ACT
                <button class="op-btn op-ok" onclick="doPublish({$row['id']})">上架站内</button>
                ACT;
            }

            // 收入：短剧按解锁分成、聊天作品按好感度/视频解锁分成、直播作品按礼物收入；再叠加提交用户累计到手收益
            $pid    = (int)($row['persona_id'] ?? 0);
            $uid    = (int)($row['user_id'] ?? 0);
            $sid    = (int)($row['short_id'] ?? 0);
            $cid    = (int)($row['ai_content_id'] ?? 0);
            $uTotal = (float)($userIncome[$uid] ?? 0);
            if ($sid > 0) {
                $sInc    = $shortIncome[$sid] ?? null;
                $workInc = '<div class="inc-work">' . self::fmtNum((float)($sInc['net'] ?? 0)) . ' <span class="inc-cur">代币</span></div>'
                    . '<div class="inc-sub">解锁毛额 ' . self::fmtNum((float)($sInc['gross'] ?? 0)) . ' · ' . (int)($sInc['buyers'] ?? 0) . ' 人</div>';
            } elseif ($cid > 0) {
                $cInc    = $contentIncome[$cid] ?? null;
                $workInc = '<div class="inc-work">' . self::fmtNum((float)($cInc['net'] ?? 0)) . ' <span class="inc-cur">代币</span></div>'
                    . '<div class="inc-sub">消费毛额 ' . self::fmtNum((float)($cInc['gross'] ?? 0)) . ' · ' . (int)($cInc['orders'] ?? 0) . ' 笔</div>';
            } elseif ($pid > 0) {
                $pInc    = $personaIncome[$pid] ?? null;
                $workInc = '<div class="inc-work">' . self::fmtNum((float)($pInc['net'] ?? 0)) . ' <span class="inc-cur">代币</span></div>'
                    . '<div class="inc-sub">毛额 ' . self::fmtNum((float)($pInc['gross'] ?? 0)) . '</div>';
            } else {
                $workInc = '<div class="inc-none">未上架</div>';
            }
            $incomeCell = '<td class="td-income">' . $workInc
                . '<div class="inc-owner">作者累计 ' . self::fmtNum($uTotal) . '</div></td>';

            $rows .= <<<ROW
            <tr>
                <td>
                    <div class="proj-cell">
                        {$cover}
                        <div class="proj-info"><div class="proj-title">{$pname}</div></div>
                    </div>
                </td>
                <td>
                    <div class="owner-name">{$name}</div>
                    <div class="owner-no">#{$no}</div>
                </td>
                <td>{$ptype}</td>
                <td class="td-center">{$adult}</td>
                <td><span class="badge badge-{$stNum}">{$st}</span></td>
                <td>{$time}</td>
                {$incomeCell}
                <td>
                    <div class="op-group">
                        <button class="op-btn" onclick="showDetail({$row['id']})">查看</button>
                        {$actions}
                        <button class="op-btn" onclick="doDownload({$row['id']})">下载</button>
                        <button class="op-btn op-del" onclick="doDelete({$row['id']})">删除</button>
                    </div>
                </td>
            </tr>
            ROW;
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="8" class="empty-tip">暂无创作提交</td></tr>';
        }

        $pager = $list->render();

        $keywordVal = htmlspecialchars((string)$this->request->param('search', ''));
        $statusVal  = $this->request->param('status', '');
        $statusAll  = $statusVal === '' ? 'selected' : '';
        $status0    = (string)$statusVal === '0' ? 'selected' : '';
        $status1    = (string)$statusVal === '1' ? 'selected' : '';
        $status2    = (string)$statusVal === '2' ? 'selected' : '';

        $ratePct  = number_format($rate * 100, 0);
        $ownerPct = number_format((1 - $rate) * 100, 0);

        return <<<HTML
        <!DOCTYPE html>
        <html lang="zh-CN">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>创作审核</title>
            <style>
                *{margin:0;padding:0;box-sizing:border-box}
                body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI','PingFang SC','Microsoft YaHei',sans-serif;background:#f4f5fb;color:#1f2430;font-size:14px}
                .container{max-width:1320px;margin:0 auto;padding:28px 24px 60px}
                .page-head{margin-bottom:22px}
                .page-head h1{font-size:24px;font-weight:700;color:#1a1f36}
                .page-head .sub{margin-top:6px;font-size:13px;color:#8a90a3}
                .stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:22px}
                .stat-card{background:#fff;border:1px solid #eef0f6;border-radius:14px;padding:18px;box-shadow:0 1px 3px rgba(30,34,60,.04)}
                .stat-val{font-size:22px;font-weight:700;color:#1a1f36}
                .stat-label{font-size:12px;color:#8a90a3;margin-top:3px}
                .toolbar{display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap;align-items:center}
                .toolbar input{width:320px;padding:10px 14px;border:1px solid #e2e5ee;border-radius:10px;font-size:14px;background:#fff;color:#1f2430;outline:none}
                .toolbar input:focus{border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.12)}
                .toolbar select{padding:10px 14px;border:1px solid #e2e5ee;border-radius:10px;font-size:14px;background:#fff;color:#1f2430;outline:none;cursor:pointer}
                .btn{border:none;border-radius:10px;cursor:pointer;font-size:14px;font-weight:600;padding:10px 20px}
                .btn-primary{background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff}
                .btn-ghost{background:#fff;color:#5a6172;border:1px solid #e2e5ee;text-decoration:none;display:inline-flex;align-items:center}
                .table-card{background:#fff;border:1px solid #eef0f6;border-radius:14px;overflow:hidden;box-shadow:0 1px 3px rgba(30,34,60,.04)}
                .table-wrap{overflow-x:auto}
                table{width:100%;border-collapse:collapse;min-width:900px}
                th{background:#fafbfe;font-weight:600;font-size:12px;color:#8a90a3;text-transform:uppercase;letter-spacing:.03em;text-align:left;padding:14px 16px;border-bottom:1px solid #eef0f6;white-space:nowrap}
                td{padding:14px 16px;border-bottom:1px solid #f2f3f8;font-size:13px;color:#3a4051;white-space:nowrap;vertical-align:middle}
                tbody tr:hover{background:#fafbfe}
                tbody tr:last-child td{border-bottom:none}
                .td-center{text-align:center}
                .proj-cell{display:flex;align-items:center;gap:12px}
                .thumb{width:44px;height:44px;border-radius:10px;overflow:hidden;background:#eef0f6;flex-shrink:0}
                .thumb img{width:100%;height:100%;object-fit:cover;display:block}
                .thumb-empty{background:linear-gradient(135deg,#eef0ff,#f4ecfe)}
                .proj-title{font-weight:600;color:#1a1f36;max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
                .owner-name{font-weight:500;color:#3a4051}
                .owner-no{font-size:12px;color:#9aa0b0;margin-top:2px}
                .badge{display:inline-flex;align-items:center;gap:5px;padding:4px 11px;border-radius:999px;font-size:12px;font-weight:600}
                .badge::before{content:'';width:6px;height:6px;border-radius:50%;background:currentColor}
                .badge-0{background:#fdf6ec;color:#b45309}
                .badge-1{background:#e6f7f1;color:#059669}
                .badge-2{background:#fdeef2;color:#e11d48}
                .adult-chip{padding:2px 9px;border-radius:999px;background:#fdeef2;color:#e11d48;font-size:11px;font-weight:700}
                .na{color:#c0c4d0}
                .td-income{white-space:nowrap}
                .inc-work{font-size:13px;font-weight:700;color:#059669}
                .inc-cur{font-size:10px;font-weight:600;color:#9aa0b0;letter-spacing:.03em}
                .inc-sub{font-size:11px;color:#9aa0b0;margin-top:2px}
                .inc-owner{font-size:11px;color:#7c3aed;margin-top:4px}
                .inc-none{font-size:12px;color:#c0c4d0}
                .inc-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin:8px 0 4px}
                .inc-card{background:#fafbfe;border:1px solid #eef0f6;border-radius:12px;padding:12px 14px}
                .inc-card .k{font-size:11px;color:#8a90a3}
                .inc-card .v{font-size:17px;font-weight:800;color:#1a1f36;margin-top:4px}
                .inc-card .v.green{color:#059669}
                .inc-card .v.purple{color:#7c3aed}
                .link-row{display:flex;align-items:center;gap:10px;margin-top:10px}
                .link-row select{flex:1;padding:9px 12px;border:1px solid #e2e5ee;border-radius:10px;font-size:13px;background:#fff;color:#1f2430;outline:none}
                .link-hint{font-size:12px;color:#8a90a3;margin-top:8px;line-height:1.6}
                .op-group{display:flex;gap:6px}
                .op-btn{display:inline-flex;align-items:center;padding:6px 12px;border:1px solid #e2e5ee;border-radius:8px;background:#fff;color:#5a6172;font-size:12px;font-weight:500;cursor:pointer}
                .op-btn:hover{background:#f7f8fc}
                .op-ok{color:#059669;border-color:#bfe6d6}
                .op-ok:hover{background:#e6f7f1}
                .op-no{color:#e11d48;border-color:#f5c2c2}
                .op-no:hover{background:#fef2f2}
                .op-del{color:#e11d48;border-color:#f5c2c2}
                .op-del:hover{background:#fef2f2}
                .empty-tip{text-align:center;color:#9aa0b0;padding:40px 0}
                .page-bar{text-align:center;margin-top:22px}
                .page-bar ul{display:inline-flex;gap:6px;list-style:none}
                .page-bar li a,.page-bar li span{display:inline-block;padding:8px 13px;border:1px solid #e2e5ee;border-radius:8px;color:#5a6172;text-decoration:none;font-size:13px;background:#fff}
                .page-bar .active span{background:#6366f1;color:#fff;border-color:#6366f1}
                .modal-overlay{display:none;position:fixed;inset:0;background:rgba(20,22,40,.5);backdrop-filter:blur(3px);z-index:999;align-items:center;justify-content:center;padding:20px}
                .modal-overlay.active{display:flex}
                .modal{background:#fff;border-radius:16px;width:720px;max-width:100%;max-height:88vh;overflow-y:auto;box-shadow:0 24px 60px rgba(20,22,40,.25)}
                .modal-head{position:sticky;top:0;background:#fff;padding:20px 24px;border-bottom:1px solid #eef0f6;display:flex;align-items:center;justify-content:space-between;z-index:2}
                .modal-head h3{font-size:17px;font-weight:700;color:#1a1f36}
                .modal-close{width:32px;height:32px;border:none;border-radius:8px;background:#f2f3f8;color:#5a6172;cursor:pointer}
                .modal-body{padding:24px}
                .detail-top{display:flex;gap:20px;margin-bottom:20px}
                .detail-cover{width:160px;height:160px;border-radius:14px;overflow:hidden;background:linear-gradient(135deg,#eef0ff,#f4ecfe);flex-shrink:0;display:flex;align-items:center;justify-content:center;color:#b8bdd0;font-size:12px}
                .detail-cover img{width:100%;height:100%;object-fit:cover;display:block}
                .detail-title{font-size:19px;font-weight:700;color:#1a1f36}
                .detail-owner{margin-top:12px;font-size:13px;color:#5a6172}
                .detail-owner b{color:#1a1f36}
                .kv-list{display:grid;grid-template-columns:repeat(2,1fr);gap:10px 18px;margin:18px 0}
                .kv-item{font-size:13px;color:#3a4051;line-height:1.6}
                .kv-item .k{color:#8a90a3;margin-right:6px}
                .tag-chip{display:inline-block;padding:3px 10px;border-radius:999px;background:#f4ecfe;border:1px solid #e4d4fb;color:#7c3aed;font-size:12px;margin:0 4px 4px 0}
                .sec-title{font-size:13px;font-weight:700;color:#1a1f36;margin:16px 0 8px}
                .detail-desc{background:#fafbfe;border:1px solid #eef0f6;border-radius:12px;padding:14px 16px;font-size:13px;color:#4a5062;line-height:1.8;white-space:pre-wrap;word-break:break-word}
                .mat-list{display:flex;flex-wrap:wrap;gap:10px}
                .mat-item{display:flex;flex-direction:column;gap:6px;width:150px;border:1px solid #eef0f6;border-radius:10px;padding:8px;text-decoration:none;color:#3a4051;font-size:12px}
                .mat-item img{width:100%;height:82px;object-fit:cover;border-radius:6px;background:#f2f3f8}
                .mat-item .nm{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
                .ep-list{display:flex;flex-direction:column;gap:8px}
                .ep-item{display:flex;gap:12px;align-items:center;border:1px solid #eef0f6;border-radius:10px;padding:8px 10px;background:#fff}
                .ep-item img{width:96px;height:54px;object-fit:cover;border-radius:6px;background:#f2f3f8;flex-shrink:0}
                .ep-ph{width:96px;height:54px;border-radius:6px;background:#f2f3f8;color:#9aa0b0;font-size:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
                .ep-meta{min-width:0}
                .ep-ttl{font-size:13px;font-weight:600;color:#1a1f36;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
                .ep-sub{font-size:12px;color:#8a90a3;margin-top:3px}
                .ep-free{color:#059669;font-weight:600}
                .modal-footer{display:flex;gap:10px;justify-content:flex-end;margin-top:24px;padding-top:16px;border-top:1px solid #eef0f6}
                .btn-sm{padding:9px 18px;font-size:13px}
                .btn-danger{background:#e11d48;color:#fff}
                .btn-success{background:#059669;color:#fff}
                .reject-box textarea{width:100%;min-height:90px;padding:10px 14px;border:1px solid #e2e5ee;border-radius:10px;font-size:14px;font-family:inherit;resize:vertical;outline:none;box-sizing:border-box}
                @media (max-width:900px){ .stats-grid{grid-template-columns:repeat(2,1fr)} .detail-top{flex-direction:column} .kv-list{grid-template-columns:1fr} }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="page-head">
                    <h1>创作审核</h1>
                    <p class="sub">审核用户独立完成的角色创作 · 通过后关联角色上线即可产生收益（平台抽成 {$ratePct}%，作者到手 {$ownerPct}%）</p>
                </div>

                <div class="stats-grid">
                    <div class="stat-card"><div class="stat-val">{$stats['total']}</div><div class="stat-label">全部提交</div></div>
                    <div class="stat-card"><div class="stat-val">{$stats['pending']}</div><div class="stat-label">待审核</div></div>
                    <div class="stat-card"><div class="stat-val">{$stats['online']}</div><div class="stat-label">已上线</div></div>
                    <div class="stat-card"><div class="stat-val">{$stats['rejected']}</div><div class="stat-label">已驳回</div></div>
                </div>

                <form class="toolbar" method="get">
                    <input name="search" placeholder="搜索角色名 / 提交用户" value="{$keywordVal}">
                    <select name="status">
                        <option value="" {$statusAll}>全部状态</option>
                        <option value="0" {$status0}>待审核</option>
                        <option value="1" {$status1}>已上线</option>
                        <option value="2" {$status2}>已驳回</option>
                    </select>
                    <button class="btn btn-primary" type="submit">查询</button>
                    <a class="btn btn-ghost" href="/admin/live.Creator/index">重置</a>
                </form>

                <div class="table-card">
                    <div class="table-wrap">
                        <table>
                            <thead><tr>
                                <th>角色</th><th>提交用户</th><th>发布类型</th><th>18+</th><th>状态</th><th>提交时间</th><th>收入</th><th>操作</th>
                            </tr></thead>
                            <tbody>{$rows}</tbody>
                        </table>
                    </div>
                </div>
                <div class="page-bar">{$pager}</div>
            </div>

            <div class="modal-overlay" id="detailModal">
                <div class="modal" id="detailBox"></div>
            </div>

            <script>
            var PUBLISH_TEXT = {live:'直播角色',chat:'普通对话角色',drama:'短剧'};
            var STATUS_TEXT = {0:'待审核',1:'已上线',2:'已驳回'};
            var STYLE_TEXT = {realistic:'写实',anime:'二次元','3d':'3D',cyberpunk:'赛博朋克',chinese:'古风',korean:'韩系',western:'欧美'};
            var GENDER_TEXT = {female:'女性',male:'男性',other:'其他'};
            var LANG_TEXT = {'zh-CN':'中文','en-US':'英文','ja-JP':'日文','ms-MY':'马来语',multi:'多语言'};
            var VOICE_TEXT = {sweet:'甜美',mature:'御姐',magnetic:'磁性',loli:'萝莉',cold:'冷艳',gentle:'温柔',none:'不涉及语音'};
            function esc(s){return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
            function lbl(map,v){return v ? (map[v]||v) : '';}
            function kv(k,v){return '<div class="kv-item"><span class="k">'+k+'：</span>'+v+'</div>';}
            function fmt(n){n=Number(n)||0;var s=(Math.round(n*100)/100).toString();var a=s.split('.');a[0]=a[0].replace(/\B(?=(\d{3})+(?!\d))/g,',');return a.join('.');}

            function showDetail(id){
                document.getElementById('detailModal').classList.add('active');
                document.getElementById('detailBox').innerHTML = '<div class="modal-head"><h3>创作详情</h3><button class="modal-close" onclick="closeDetail()">✕</button></div><div class="modal-body"><div class="empty-tip">加载中...</div></div>';
                fetch('/admin/live.Creator/detailJson?id=' + id).then(function(r){return r.json()}).then(function(d){
                    if (d.code !== 1) {
                        document.getElementById('detailBox').innerHTML = '<div class="modal-head"><h3>创作详情</h3><button class="modal-close" onclick="closeDetail()">✕</button></div><div class="modal-body"><div class="empty-tip">'+esc(d.msg||'加载失败')+'</div></div>';
                        return;
                    }
                    var p = d.data;
                    var cover = p.cover_url ? '<div class="detail-cover"><img src="'+esc(p.cover_url)+'" onerror="this.style.display=\'none\'"></div>' : '<div class="detail-cover">无封面</div>';
                    var tags = p.tags || [];
                    var mats = p.materials || [];
                    var info = '';
                    if (tags.length) info += kv('标签', tags.map(function(t){return '<span class="tag-chip">'+esc(t)+'</span>';}).join(''));
                    if (p.style) info += kv('风格', esc(lbl(STYLE_TEXT,p.style)));
                    if (p.gender) info += kv('性别', esc(lbl(GENDER_TEXT,p.gender)));
                    if (p.age_range) info += kv('年龄段', esc(p.age_range));
                    if (p.language) info += kv('语言', esc(lbl(LANG_TEXT,p.language)));
                    var pers = p.personality || [];
                    if (pers.length) info += kv('性格特点', esc(pers.join('、')));
                    if (p.voice_style) info += kv('语音风格', esc(lbl(VOICE_TEXT,p.voice_style)));
                    info += kv('发布类型', esc(p.publish_text || ''));
                    info += kv('18+ 内容', p.is_adult ? '<span class="adult-chip">18+</span>' : '否');
                    if (p.reference_url) info += kv('参考链接', '<a href="'+esc(p.reference_url)+'" target="_blank" style="color:#6366f1">'+esc(p.reference_url)+'</a>');
                    if (p.reject_reason) info += kv('驳回原因', '<span style="color:#e11d48">'+esc(p.reject_reason)+'</span>');

                    var matHtml = '';
                    if (mats.length) {
                        for (var i=0;i<mats.length;i++){
                            var m = mats[i] || {};
                            var isImg = /\.(png|jpe?g|gif|webp)$/i.test(m.url||'');
                            matHtml += '<a class="mat-item" href="'+esc(m.url)+'" target="_blank" download>' +
                                (isImg ? '<img src="'+esc(m.url)+'" onerror="this.style.visibility=\'hidden\'">' : '<div style="height:82px;display:flex;align-items:center;justify-content:center;background:#f2f3f8;border-radius:6px">附件</div>') +
                                '<span class="nm">'+esc(m.name || m.url || '')+'</span></a>';
                        }
                    } else {
                        matHtml = '<div class="empty-tip">无资料</div>';
                    }

                    // 短剧剧集（与后台 lp_short_episode 同结构）
                    var eps = p.episodes || [];
                    var freeEp = Number(p.free_episodes) || 0;
                    var epHtml = '';
                    if (eps.length) {
                        epHtml = '<div class="sec-title">剧集（' + eps.length + ' 集' + (freeEp > 0 ? ' · 前 ' + freeEp + ' 集免费' : '') + '）</div><div class="ep-list">';
                        for (var ei = 0; ei < eps.length; ei++) {
                            var ep = eps[ei] || {};
                            var epNo = Number(ep.episode_no) || (ei + 1);
                            var epPrice = Number(ep.price) || 0;
                            var epFree = (epNo <= freeEp) || epPrice <= 0;
                            var cover = ep.poster ? '<img src="' + esc(ep.poster) + '" onerror="this.style.visibility=\'hidden\'">' : '<div class="ep-ph">EP' + epNo + '</div>';
                            var priceTxt = epFree ? '<span class="ep-free">免费</span>' : (epPrice + ' 代币');
                            epHtml += '<div class="ep-item">' + cover +
                                '<div class="ep-meta"><div class="ep-ttl">EP' + epNo + ' · ' + esc(ep.title || '') + '</div>' +
                                '<div class="ep-sub">' + (ep.duration ? esc(ep.duration) + ' · ' : '') + priceTxt + '</div>' +
                                '<div class="ep-sub">' + (ep.video_url ? '<a href="' + esc(ep.video_url) + '" target="_blank" download style="color:#6366f1">视频</a>' : '<span style="color:#e11d48">缺视频</span>') + '</div></div></div>';
                        }
                        epHtml += '</div>';
                    }

                    // 收入：短剧按解锁分成、聊天作品按好感度/视频解锁分成、直播作品按礼物收入；外加作者累计到手
                    var inc = p.income || {};
                    var incPersona = inc.persona;
                    var incShort = inc.short;
                    var incContent = inc.content;
                    var rate = Number(inc.rate) || 0;
                    var authorTotal = Number(inc.author_total) || 0;

                    var optHtml = '<option value="0">— 不关联 —</option>';
                    var opts = p.persona_options || [];
                    for (var oi = 0; oi < opts.length; oi++) {
                        var o = opts[oi];
                        var own = o.nickname ? (' · ' + esc(o.nickname)) : (o.user_id ? (' · user#' + o.user_id) : '');
                        optHtml += '<option value="' + o.id + '"' + (String(o.id) === String(p.persona_id) ? ' selected' : '') + '>#' + o.id + ' ' + esc(o.name) + own + '</option>';
                    }

                    var incHtml;
                    if (Number(p.short_id) > 0) {
                        // 短剧：按剧集解锁分成统计
                        var sGross  = incShort ? (Number(incShort.gross) || 0) : 0;
                        var sNet    = incShort ? (Number(incShort.net) || 0) : 0;
                        var sBuyers = incShort ? (Number(incShort.buyers) || 0) : 0;
                        incHtml =
                            '<div class="sec-title">收入</div>' +
                            '<div class="inc-grid">' +
                                '<div class="inc-card"><div class="k">解锁毛额</div><div class="v">' + fmt(sGross) + '</div></div>' +
                                '<div class="inc-card"><div class="k">作者分成（抽成 ' + (rate * 100).toFixed(0) + '%）</div><div class="v green">' + fmt(sNet) + '</div></div>' +
                                '<div class="inc-card"><div class="k">作者累计到手</div><div class="v purple">' + fmt(authorTotal) + '</div></div>' +
                            '</div>' +
                            '<div class="link-hint">短剧 #' + p.short_id + ' · 已解锁 ' + sBuyers + ' 人（短剧收入按剧集解锁分成统计）</div>';
                    } else if (Number(p.content_id) > 0) {
                        var cGross  = incContent ? (Number(incContent.gross) || 0) : 0;
                        var cNet    = incContent ? (Number(incContent.net) || 0) : 0;
                        var cOrders = incContent ? (Number(incContent.orders) || 0) : 0;
                        incHtml =
                            '<div class="sec-title">收入</div>' +
                            '<div class="inc-grid">' +
                                '<div class="inc-card"><div class="k">消费毛额</div><div class="v">' + fmt(cGross) + '</div></div>' +
                                '<div class="inc-card"><div class="k">作者分成（抽成 ' + (rate * 100).toFixed(0) + '%）</div><div class="v green">' + fmt(cNet) + '</div></div>' +
                                '<div class="inc-card"><div class="k">作者累计到手</div><div class="v purple">' + fmt(authorTotal) + '</div></div>' +
                            '</div>' +
                            '<div class="link-hint">聊天内容 #' + p.content_id + ' · ' + cOrders + ' 笔消费（好感度购买 / 视频解锁分成）</div>';
                    } else {
                        var grossVal = incPersona ? (Number(incPersona.gross) || 0) : 0;
                        var netVal = incPersona ? (Number(incPersona.net) || 0) : 0;
                        var donors = incPersona ? (Number(incPersona.donors) || 0) : 0;
                        incHtml =
                            '<div class="sec-title">收入</div>' +
                            '<div class="inc-grid">' +
                                '<div class="inc-card"><div class="k">作品礼物毛额</div><div class="v">' + (incPersona ? fmt(grossVal) : '—') + '</div></div>' +
                                '<div class="inc-card"><div class="k">作品净收入（抽成 ' + (rate * 100).toFixed(0) + '%）</div><div class="v green">' + (incPersona ? fmt(netVal) : '—') + '</div></div>' +
                                '<div class="inc-card"><div class="k">作者累计到手</div><div class="v purple">' + fmt(authorTotal) + '</div></div>' +
                            '</div>' +
                            '<div class="link-hint">' + (incPersona ? ('送礼人数 ' + donors + ' 人 · 已关联角色 #' + p.persona_id) : '该作品尚未关联角色，暂无法统计作品收入；请在下方关联对应角色。') + '</div>' +
                            '<div class="link-row">' +
                                '<select id="personaSel">' + optHtml + '</select>' +
                                '<button class="btn btn-primary btn-sm" onclick="doLinkPersona(' + p.id + ')">保存关联</button>' +
                            '</div>';
                    }

                    var footer = '<button class="btn btn-ghost" onclick="closeDetail()">关闭</button>';
                    footer += '<button class="btn btn-ghost btn-sm" onclick="doDownload('+p.id+')">下载资料</button>';
                    footer += '<button class="btn btn-danger btn-sm" onclick="doDelete('+p.id+')">删除</button>';
                    if (p.status === 0) {
                        footer += '<button class="btn btn-danger btn-sm" onclick="showReject('+p.id+')">驳回</button>';
                        footer += '<button class="btn btn-success btn-sm" onclick="doApprove('+p.id+',1)">通过</button>';
                    }

                    var html =
                        '<div class="modal-head"><h3>创作详情</h3><button class="modal-close" onclick="closeDetail()">✕</button></div>' +
                        '<div class="modal-body">' +
                            '<div class="detail-top">' + cover +
                                '<div><div class="detail-title">'+esc(p.persona_name)+'</div>' +
                                '<div class="detail-owner">提交用户：<b>'+esc(p.nickname)+'</b>（#'+esc(p.user_no)+'）</div>' +
                                '<div class="detail-owner">提交时间：'+esc(p.created_at||'-')+(p.reviewed_at?'<br>审核时间：'+esc(p.reviewed_at):'')+'</div>' +
                                '<div class="detail-owner" style="margin-top:8px"><span class="badge badge-'+p.status+'">'+esc(p.status_text||'')+'</span></div></div>' +
                            '</div>' +
                            incHtml +
                            (info ? '<div class="kv-list">'+info+'</div>' : '') +
                            (p.highlights ? '<div class="sec-title">项目亮点</div><div class="detail-desc">'+esc(p.highlights)+'</div>' : '') +
                            '<div class="sec-title">角色描述</div><div class="detail-desc">'+(p.description?esc(p.description):'暂无描述')+'</div>' +
                            '<div class="sec-title">角色资料（'+mats.length+'）</div><div class="mat-list">'+matHtml+'</div>' +
                            epHtml +
                            '<div class="modal-footer">'+footer+'</div>' +
                        '</div>';
                    document.getElementById('detailBox').innerHTML = html;
                }).catch(function(){
                    document.getElementById('detailBox').innerHTML = '<div class="modal-head"><h3>创作详情</h3><button class="modal-close" onclick="closeDetail()">✕</button></div><div class="modal-body"><div class="empty-tip">网络错误</div></div>';
                });
            }
            function closeDetail(){ document.getElementById('detailModal').classList.remove('active'); }

            function doApprove(id, closeAfter){
                if (!confirm('确定通过该创作？通过后会自动上架到对应专区（直播→直播间，聊天→聊天内容，短剧→短剧）。')) return;
                fetch('/admin/live.Creator/approve?id=' + id).then(function(r){return r.json()}).then(function(d){
                    if (d.code === 1) { alert(d.msg || '审核通过'); location.reload(); }
                    else { alert(d.msg || '操作失败'); }
                }).catch(function(){ alert('网络错误'); });
            }

            function doPublish(id){
                if (!confirm('把该作品上架到对应专区（直播→直播间，聊天→聊天内容，短剧→短剧）？')) return;
                fetch('/admin/live.Creator/publish?id=' + id).then(function(r){return r.json()}).then(function(d){
                    if (d.code === 1) { alert(d.msg || '已上架'); location.reload(); }
                    else { alert(d.msg || '操作失败'); }
                }).catch(function(){ alert('网络错误'); });
            }

            function showReject(id){
                var reason = prompt('请输入驳回原因：', '');
                if (reason === null) return;
                reason = reason.trim();
                if (!reason) { alert('请填写驳回原因'); return; }
                fetch('/admin/live.Creator/reject?id=' + id + '&reason=' + encodeURIComponent(reason)).then(function(r){return r.json()}).then(function(d){
                    if (d.code === 1) { alert('已驳回'); location.reload(); }
                    else { alert(d.msg || '操作失败'); }
                }).catch(function(){ alert('网络错误'); });
            }

            function doDownload(id){
                var a = document.createElement('a');
                a.href = '/admin/live.Creator/download?id=' + id;
                a.style.display = 'none';
                document.body.appendChild(a);
                a.click();
                setTimeout(function(){ if (a.parentNode) a.parentNode.removeChild(a); }, 800);
            }

            function doDelete(id){
                if (!confirm('确定删除这条创作提交？删除后不可恢复。')) return;
                fetch('/admin/live.Creator/removeRecord?id=' + id).then(function(r){return r.json()}).then(function(d){
                    if (d.code === 1) { alert('已删除'); location.reload(); }
                    else { alert(d.msg || '操作失败'); }
                }).catch(function(){ alert('网络错误'); });
            }

            function doLinkPersona(id){
                var sel = document.getElementById('personaSel');
                if (!sel) return;
                fetch('/admin/live.Creator/linkPersona?id=' + id + '&persona_id=' + encodeURIComponent(sel.value))
                    .then(function(r){return r.json()}).then(function(d){
                        if (d.code === 1) { alert(d.msg || '已保存'); location.reload(); }
                        else { alert(d.msg || '操作失败'); }
                    }).catch(function(){ alert('网络错误'); });
            }

            document.getElementById('detailModal').addEventListener('click', function(e){ if (e.target === this) closeDetail(); });
            </script>
        </body>
        </html>
        HTML;
    }
}
