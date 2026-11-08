<?php

declare(strict_types=1);

namespace app\admin\controller\track;

use Throwable;
use think\facade\Db;
use app\common\controller\Backend;

/**
 * 行为分析报表（埋点：lp_track_event + 登录日志：lp_user_login_log）
 *
 * 单页多 Tab，action 与前端 Tab 一一对应：
 * - overview 总览漏斗（PV/UV/注册/登录 + 按日趋势）
 * - page     页面分析（PV/UV/停留/退出率）
 * - feature  功能使用（事件次数/触达访客/触达用户）
 * - login    登录分析（次数分布/多次登录用户/登录明细）
 * - detail   行为明细（按 vid/用户还原访问路径）
 *
 * 渠道/域名归因：lp_track_event 本身不存渠道，按 sid 关联 lp_promo_visit（uk_sid）。
 */
final class Analysis extends Backend
{
    protected string $connection = 'live_mysql';

    /** 时间范围上限（天），防止全表扫描 */
    private const MAX_RANGE_DAYS = 92;

    public function initialize(): void
    {
        parent::initialize();
    }

    /* ============================================================
     * 总览漏斗
     * ============================================================ */
    public function overview(): void
    {
        [$start, $end] = $this->range();
        [$w, $b] = $this->trackWhere();

        // ---- 浏览卡片（lp_track_event 的 pageview 行） ----
        $pvRow = Db::connect($this->connection)->query(
            "SELECT COUNT(*) AS pv, COUNT(DISTINCT vid) AS uv, COUNT(DISTINCT sid) AS sessions,"
            . " IFNULL(AVG(NULLIF(duration, 0)), 0) AS avg_duration"
            . " FROM lp_track_event WHERE {$w} AND event = 'pageview'",
            $b
        )[0] ?? [];

        // ---- 注册（lp_user 按创建时间；域名筛选时用 reg_domain；缺列的旧库降级为 0） ----
        $regWhere  = 'created_at BETWEEN ? AND ?';
        $regBind   = [$start . ' 00:00:00', $end . ' 23:59:59'];
        $domain    = $this->filterParam('domain');
        if ($domain !== '') {
            $regWhere .= ' AND reg_domain = ?';
            $regBind[] = $domain;
        }
        try {
            $registers = (int) (Db::connect($this->connection)->query(
                "SELECT COUNT(*) AS c FROM lp_user WHERE {$regWhere}",
                $regBind
            )[0]['c'] ?? 0);
        } catch (Throwable $e) {
            $registers = 0;
        }

        // ---- 登录（lp_user_login_log；域名/渠道筛选走 sid 关联 lp_promo_visit） ----
        [$lw, $lb]     = $this->loginWhere(true);
        $loginRow = Db::connect($this->connection)->query(
            "SELECT COUNT(*) AS login_count,"
            . " COUNT(DISTINCT CASE WHEN user_id > 0 THEN user_id END) AS login_users"
            . " FROM lp_user_login_log l WHERE {$lw}",
            $lb
        )[0] ?? [];
        $loginUsers = (int) ($loginRow['login_users'] ?? 0);
        $multiUsers = 0;
        if ($loginUsers > 0) {
            $sub        = $this->loginUserCountSub(true);
            $multiUsers = (int) (Db::connect($this->connection)->query(
                "SELECT COUNT(*) AS c FROM ({$sub}) t WHERE t.c >= 2",
                $lb
            )[0]['c'] ?? 0);
        }

        $uv = (int) ($pvRow['uv'] ?? 0);

        // ---- 按日趋势：pv/uv + 注册 + 登录 ----
        $trend = [];
        for ($t = strtotime($start); $t <= strtotime($end); $t += 86400) {
            $d           = date('Y-m-d', $t);
            $trend[$d]   = ['date' => $d, 'pv' => 0, 'uv' => 0, 'register' => 0, 'login' => 0];
        }
        $rows = Db::connect($this->connection)->query(
            "SELECT DATE(created_at) AS d, COUNT(*) AS pv, COUNT(DISTINCT vid) AS uv"
            . " FROM lp_track_event WHERE {$w} AND event = 'pageview' GROUP BY DATE(created_at)",
            $b
        );
        foreach ($rows as $r) {
            $d = (string) $r['d'];
            if (isset($trend[$d])) {
                $trend[$d]['pv'] = (int) $r['pv'];
                $trend[$d]['uv'] = (int) $r['uv'];
            }
        }
        try {
            $rows = Db::connect($this->connection)->query(
                "SELECT DATE(created_at) AS d, COUNT(*) AS c FROM lp_user WHERE {$regWhere} GROUP BY DATE(created_at)",
                $regBind
            );
        } catch (Throwable $e) {
            $rows = [];
        }
        foreach ($rows as $r) {
            $d = (string) $r['d'];
            if (isset($trend[$d])) {
                $trend[$d]['register'] = (int) $r['c'];
            }
        }
        $rows = Db::connect($this->connection)->query(
            "SELECT DATE(l.created_at) AS d,"
            . " COUNT(DISTINCT CASE WHEN l.success = 1 THEN l.user_id END) AS c"
            . " FROM lp_user_login_log l WHERE {$lw} GROUP BY DATE(l.created_at)",
            $lb
        );
        foreach ($rows as $r) {
            $d = (string) $r['d'];
            if (isset($trend[$d])) {
                $trend[$d]['login'] = (int) $r['c'];
            }
        }

        // ---- 筛选下拉选项：域名 / 渠道（lp_promo_visit 未建的站库降级为空） ----
        try {
            $domains  = array_column(Db::connect($this->connection)->query(
                'SELECT DISTINCT domain FROM lp_promo_visit WHERE domain <> \'\' ORDER BY domain LIMIT 100'
            ), 'domain');
            $channels = array_column(Db::connect($this->connection)->query(
                'SELECT DISTINCT channel FROM lp_promo_visit WHERE channel <> \'\' ORDER BY channel LIMIT 100'
            ), 'channel');
        } catch (Throwable $e) {
            $domains  = [];
            $channels = [];
        }

        $this->success('', [
            'cards' => [
                'pv'            => (int) ($pvRow['pv'] ?? 0),
                'uv'            => $uv,
                'sessions'      => (int) ($pvRow['sessions'] ?? 0),
                'avg_duration'  => round((float) ($pvRow['avg_duration'] ?? 0), 1),
                'registers'     => $registers,
                'register_rate' => $uv > 0 ? round($registers / $uv * 100, 2) : 0,
                'login_count'   => (int) ($loginRow['login_count'] ?? 0),
                'login_users'   => $loginUsers,
                'multi_users'   => $multiUsers,
                'multi_rate'    => $loginUsers > 0 ? round($multiUsers / $loginUsers * 100, 2) : 0,
            ],
            'trend'   => array_values($trend),
            'options' => ['domains' => $domains, 'channels' => $channels],
            'range'   => ['start' => $start, 'end' => $end],
        ]);
    }

    /* ============================================================
     * 页面分析：PV / UV / 平均停留 / 退出率
     * 退出 = 该页是会话内最后一次页面浏览
     * ============================================================ */
    public function page(): void
    {
        [$start, $end] = $this->range();
        [$w, $b] = $this->trackWhere();

        $keyword = trim((string) $this->request->get('keyword', ''));
        $kwSql   = '';
        $kwBind  = [];
        if ($keyword !== '') {
            $kwSql  = ' AND page LIKE ?';
            $kwBind = ['%' . $keyword . '%'];
        }

        $main = "SELECT page, COUNT(*) AS pv, COUNT(DISTINCT vid) AS uv,"
            . " IFNULL(AVG(NULLIF(duration, 0)), 0) AS avg_duration"
            . " FROM lp_track_event WHERE {$w} AND event = 'pageview' {$kwSql} GROUP BY page";
        $exit = "SELECT t.page, COUNT(*) AS exits FROM lp_track_event t"
            . " JOIN (SELECT sid, MAX(id) AS mid FROM lp_track_event WHERE {$w} AND event = 'pageview' GROUP BY sid) m ON m.mid = t.id"
            . ' GROUP BY t.page';
        $combined = "SELECT g.page, g.pv, g.uv, g.avg_duration, IFNULL(e.exits, 0) AS exits"
            . " FROM ({$main}) g LEFT JOIN ({$exit}) e ON e.page = g.page";
        $binds = array_merge($b, $kwBind, $b);

        $total = (int) (Db::connect($this->connection)->query(
            "SELECT COUNT(*) AS c FROM ({$combined}) x",
            $binds
        )[0]['c'] ?? 0);

        // 排序白名单（外层查询别名是 x，列名不能带内层前缀）
        $sort  = (string) $this->request->get('sort', 'pv');
        $order = match ($sort) {
            'duration' => 'avg_duration DESC',
            'exit'     => 'exits DESC',
            default    => 'pv DESC',
        };

        [$page, $limit] = $this->pager();
        $offset = ($page - 1) * $limit;
        $rows = Db::connect($this->connection)->query(
            "SELECT * FROM ({$combined}) x ORDER BY {$order} LIMIT {$limit} OFFSET {$offset}",
            $binds
        );
        foreach ($rows as &$r) {
            $r['pv']          = (int) $r['pv'];
            $r['uv']          = (int) $r['uv'];
            $r['avg_duration'] = round((float) $r['avg_duration'], 1);
            $r['exits']       = (int) $r['exits'];
            $r['exit_rate']   = $r['pv'] > 0 ? round($r['exits'] / $r['pv'] * 100, 2) : 0;
        }
        unset($r);

        $this->success('', ['list' => $rows, 'total' => $total]);
    }

    /* ============================================================
     * 功能使用：事件次数 / 触达访客 / 触达用户
     * ============================================================ */
    public function feature(): void
    {
        [$w, $b] = $this->trackWhere();

        $keyword = trim((string) $this->request->get('keyword', ''));
        $kwSql   = '';
        $kwBind  = [];
        if ($keyword !== '') {
            $kwSql  = ' AND event LIKE ?';
            $kwBind = ['%' . $keyword . '%'];
        }

        $rows = Db::connect($this->connection)->query(
            'SELECT event, COUNT(*) AS cnt, COUNT(DISTINCT vid) AS visitors,'
            . ' COUNT(DISTINCT CASE WHEN user_id > 0 THEN user_id END) AS users,'
            . ' MAX(created_at) AS last_at'
            . " FROM lp_track_event WHERE {$w} AND event <> 'pageview' {$kwSql}"
            . ' GROUP BY event ORDER BY cnt DESC LIMIT 200',
            array_merge($b, $kwBind)
        );

        $this->success('', ['list' => $rows, 'total' => count($rows)]);
    }

    /* ============================================================
     * 登录分析：次数分布 / 多次登录用户 / 登录明细
     * ============================================================ */
    public function login(): void
    {
        [$start, $end] = $this->range();
        [$lw, $lb] = $this->loginWhere(true);
        $sub = $this->loginUserCountSub(true);

        // ---- 登录次数分布（按用户） ----
        $buckets  = ['1次' => 0, '2-3次' => 0, '4-10次' => 0, '10次以上' => 0];
        $cntRows  = Db::connect($this->connection)->query(
            "SELECT t.c FROM ({$sub}) t",
            $lb
        );
        foreach ($cntRows as $r) {
            $c = (int) $r['c'];
            if ($c <= 1) {
                $buckets['1次']++;
            } elseif ($c <= 3) {
                $buckets['2-3次']++;
            } elseif ($c <= 10) {
                $buckets['4-10次']++;
            } else {
                $buckets['10次以上']++;
            }
        }

        // ---- 多次登录用户列表 ----
        [$_, $limit]  = $this->pager();
        $multiPage  = max(1, (int) $this->request->get('multi_page/d', 1));
        $multiTotal = (int) (Db::connect($this->connection)->query(
            "SELECT COUNT(*) AS c FROM ({$sub}) t WHERE t.c >= 2",
            $lb
        )[0]['c'] ?? 0);
        $multiList = Db::connect($this->connection)->query(
            'SELECT t.user_id, u.user_no, u.nickname, u.created_at AS reg_time,'
            . ' t.c AS login_count, t.last_at'
            . " FROM ({$sub}) t"
            . ' LEFT JOIN lp_user u ON u.id = t.user_id'
            . ' WHERE t.c >= 2 ORDER BY t.c DESC, t.last_at DESC'
            . " LIMIT {$limit} OFFSET " . (($multiPage - 1) * $limit),
            $lb
        );

        // ---- 登录明细（含失败；关键词：账号/IP/vid/用户名） ----
        [$dw, $db2]     = $this->loginDetailWhere();
        $detailPage  = max(1, (int) $this->request->get('detail_page/d', 1));
        $detailTotal = (int) (Db::connect($this->connection)->query(
            "SELECT COUNT(*) AS c FROM lp_user_login_log l LEFT JOIN lp_user u ON u.id = l.user_id WHERE {$dw}",
            $db2
        )[0]['c'] ?? 0);
        $detailList = Db::connect($this->connection)->query(
            'SELECT l.id, l.user_id, u.user_no, u.nickname, l.account, l.success, l.is_register,'
            . ' l.fail_reason, l.vid, l.sid, l.ip, l.ua, l.created_at'
            . ' FROM lp_user_login_log l LEFT JOIN lp_user u ON u.id = l.user_id'
            . " WHERE {$dw} ORDER BY l.id DESC"
            . " LIMIT {$limit} OFFSET " . (($detailPage - 1) * $limit),
            $db2
        );

        $this->success('', [
            'distribution' => $buckets,
            'range'        => ['start' => $start, 'end' => $end],
            'multi'        => ['list' => $multiList, 'total' => $multiTotal, 'page' => $multiPage, 'limit' => $limit],
            'detail'       => ['list' => $detailList, 'total' => $detailTotal, 'page' => $detailPage, 'limit' => $limit],
        ]);
    }

    /* ============================================================
     * 行为明细：按 vid / user_no / 用户ID 还原访问路径
     * ============================================================ */
    public function detail(): void
    {
        $keyword = trim((string) $this->request->get('keyword', ''));
        if ($keyword === '') {
            $this->success('', ['mode' => '', 'sessions' => [], 'timeline' => []]);
            return;
        }

        $vids  = [];
        $user  = null;
        $mode  = 'vid';
        $kwInt = (int) $keyword;
        try {
            if (str_starts_with($keyword, 'v_') || str_starts_with($keyword, 's_')) {
                $vids[] = $keyword;
            } elseif ($kwInt > 0 || str_starts_with($keyword, 'U')) {
                // 用户编号（U 开头）或数字ID
                $user = Db::connect($this->connection)->table('lp_user')
                    ->field(['id', 'user_no', 'nickname'])
                    ->where('user_no', $keyword)
                    ->find();
                if (!$user && $kwInt > 0) {
                    $user = Db::connect($this->connection)->table('lp_user')
                        ->field(['id', 'user_no', 'nickname'])
                        ->where('id', $kwInt)
                        ->find();
                }
                if ($user) {
                    $mode  = 'user';
                    $uid   = (int) $user['id'];
                    $vids  = array_column(Db::connect($this->connection)->query(
                        'SELECT DISTINCT vid FROM lp_track_event WHERE user_id = ? AND vid <> \'\' LIMIT 20',
                        [$uid]
                    ), 'vid');
                    $vids  = array_merge($vids, array_column(Db::connect($this->connection)->query(
                        'SELECT DISTINCT vid FROM lp_user_login_log WHERE user_id = ? AND vid <> \'\' LIMIT 20',
                        [$uid]
                    ), 'vid'));
                    $vids  = array_values(array_unique(array_filter($vids)));
                }
            } else {
                $vids[] = $keyword;
            }
        } catch (Throwable $e) {
            $user = null;
        }

        if ($vids === []) {
            $this->success('', ['mode' => $mode, 'user' => null, 'sessions' => [], 'timeline' => [], 'logins' => []]);
            return;
        }

        // ---- 会话列表（最近 20 个） ----
        [$w, $b] = $this->trackWhere();
        $vidIn     = implode(',', array_fill(0, count($vids), '?'));
        $sessions  = Db::connect($this->connection)->query(
            "SELECT sid, MIN(created_at) AS started_at, MAX(created_at) AS ended_at,"
            . " COUNT(*) AS events, SUM(event = 'pageview') AS pv,"
            . ' SUM(event <> \'pageview\') AS acts, IFNULL(SUM(duration), 0) AS stay'
            . " FROM lp_track_event WHERE {$w} AND (vid IN ({$vidIn})"
            . ($mode === 'user' && $user ? ' OR user_id = ?' : '') . ')'
            . ' GROUP BY sid ORDER BY started_at DESC LIMIT 20',
            array_merge($b, $vids, ($mode === 'user' && $user) ? [(int) $user['id']] : [])
        );

        // ---- 时间线（默认最新会话；可传 sid 查看） ----
        $sid      = trim((string) $this->request->get('sid', ''));
        $timeline = [];
        if ($sid === '' && $sessions !== []) {
            $sid = (string) ($sessions[0]['sid'] ?? '');
        }
        if ($sid !== '') {
            $timeline = Db::connect($this->connection)->query(
                'SELECT event, page, title, duration, user_id, props, created_at'
                . ' FROM lp_track_event WHERE sid = ? ORDER BY id ASC LIMIT 500',
                [$sid]
            );
        }

        // ---- 该用户最近登录记录（用户模式） ----
        $logins = [];
        if ($mode === 'user' && $user) {
            $logins = Db::connect($this->connection)->query(
                'SELECT account, success, is_register, fail_reason, ip, created_at'
                . ' FROM lp_user_login_log WHERE user_id = ? ORDER BY id DESC LIMIT 20',
                [(int) $user['id']]
            );
        }

        $this->success('', [
            'mode'     => $mode,
            'user'     => $user,
            'vids'     => $vids,
            'sessions' => $sessions,
            'sid'      => $sid,
            'timeline' => $timeline,
            'logins'   => $logins,
        ]);
    }

    /* ============================================================
     * 私有：参数与过滤条件
     * ============================================================ */

    /** 时间范围：?start=YYYY-MM-DD&end=YYYY-MM-DD，默认近 7 天，最长 92 天 */
    private function range(): array
    {
        $end   = trim((string) $this->request->get('end', ''));
        $start = trim((string) $this->request->get('start', ''));
        $tsEnd   = $end !== '' ? strtotime($end) : false;
        $tsStart = $start !== '' ? strtotime($start) : false;
        if ($tsEnd === false || $tsEnd > time()) {
            $tsEnd = time();
        }
        if ($tsStart === false || $tsStart > $tsEnd) {
            $tsStart = $tsEnd - 6 * 86400;
        }
        if ($tsEnd - $tsStart > (self::MAX_RANGE_DAYS - 1) * 86400) {
            $tsStart = $tsEnd - (self::MAX_RANGE_DAYS - 1) * 86400;
        }
        return [date('Y-m-d', $tsStart), date('Y-m-d', $tsEnd)];
    }

    private function filterParam(string $name): string
    {
        return mb_substr(trim((string) $this->request->get($name, '')), 0, 64);
    }

    /** 分页参数 */
    private function pager(): array
    {
        $page  = max(1, (int) $this->request->get('page/d', 1));
        $limit = min(100, max(5, (int) $this->request->get('limit/d', 20)));
        return [$page, $limit];
    }

    /**
     * lp_track_event 过滤条件（时间 + 域名；渠道按 sid 关联 lp_promo_visit）
     * @return array{0:string,1:array} [whereSql, binds]
     */
    private function trackWhere(): array
    {
        [$start, $end] = $this->range();
        $where = 'created_at BETWEEN ? AND ?';
        $bind  = [$start . ' 00:00:00', $end . ' 23:59:59'];

        $domain = $this->filterParam('domain');
        if ($domain !== '') {
            $where .= ' AND domain = ?';
            $bind[] = $domain;
        }
        $channel = $this->filterParam('channel');
        if ($channel !== '') {
            $where .= ' AND sid IN (SELECT sid FROM lp_promo_visit WHERE channel = ?)';
            $bind[] = $channel;
        }
        return [$where, $bind];
    }

    /**
     * lp_user_login_log 过滤条件（别名 l：时间 + 成功 + 域名/渠道按 sid 关联 lp_promo_visit）
     * @return array{0:string,1:array}
     */
    private function loginWhere(bool $onlySuccess): array
    {
        [$start, $end] = $this->range();
        $where = 'l.created_at BETWEEN ? AND ?';
        $bind  = [$start . ' 00:00:00', $end . ' 23:59:59'];
        if ($onlySuccess) {
            $where .= ' AND l.success = 1';
        }

        $domain = $this->filterParam('domain');
        if ($domain !== '') {
            $where .= ' AND l.sid IN (SELECT sid FROM lp_promo_visit WHERE domain = ?)';
            $bind[] = $domain;
        }
        $channel = $this->filterParam('channel');
        if ($channel !== '') {
            $where .= ' AND l.sid IN (SELECT sid FROM lp_promo_visit WHERE channel = ?)';
            $bind[] = $channel;
        }
        return [$where, $bind];
    }

    /** 登录明细过滤（含失败 + 关键词搜索） */
    private function loginDetailWhere(): array
    {
        [$where, $bind] = $this->loginWhere(false);

        $success = $this->request->get('success', '');
        if ($success === '0' || $success === '1') {
            $where .= ' AND l.success = ?';
            $bind[] = (int) $success;
        }
        $keyword = trim((string) $this->request->get('keyword', ''));
        if ($keyword !== '') {
            $where .= ' AND (l.account LIKE ? OR l.ip LIKE ? OR l.vid LIKE ? OR u.user_no LIKE ? OR u.nickname LIKE ?)';
            $kw     = '%' . $keyword . '%';
            array_push($bind, $kw, $kw, $kw, $kw, $kw);
        }
        return [$where, $bind];
    }

    /** 按用户聚合成功登录次数子查询（依赖 loginWhere 的绑定） */
    private function loginUserCountSub(bool $onlySuccess): string
    {
        [$lw, ] = $this->loginWhere($onlySuccess);
        return 'SELECT user_id, COUNT(*) AS c, MAX(created_at) AS last_at'
            . " FROM lp_user_login_log l WHERE {$lw} AND user_id > 0 GROUP BY user_id";
    }
}
