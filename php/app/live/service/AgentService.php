<?php
declare(strict_types=1);

namespace app\live\service;

use think\facade\Db;
use think\facade\Log;

/**
 * 代理体系：权限、注册归因、数据统计
 *
 * 代理是独立于 BuildAdmin 管理员的业务角色：
 * - 账号在 lp_agent，登录态用 Token（type=agent）
 * - 专属域名在 lp_agent_domain，只能由总后台分配
 * - 推广链接在 lp_promo_link.agent_id 上归属代理
 * - 注册时把归属快照写到 lp_user.reg_agent_id / reg_agent_name
 */
final class AgentService
{
    /** 代理端权限：key => 名称 */
    public const PERMS = [
        'overview'    => '数据概览',
        'link'        => '推广链接（查看/复制）',
        'link_create' => '新建推广链接',
        'member'      => '会员明细',
        'domain'      => '专属域名',
        'account'     => '账号设置',
    ];

    /** 权限说明（后台勾选卡片用） */
    public const PERM_DESCS = [
        'overview'    => '查看自己名下的注册、充值、订阅与转化',
        'link'        => '查看并复制自己名下的推广链接',
        'link_create' => '允许自助创建、停用和删除推广链接',
        'member'      => '查看自己名下的会员明细（邮箱打码）',
        'domain'      => '查看总部分配的专属推广域名',
        'account'     => '修改自己的登录密码（始终可用）',
    ];

    /** 新建代理时默认勾选（账号设置不可关闭） */
    public const DEFAULT_PERMS = ['overview', 'link', 'link_create', 'member', 'account'];

    /** 无论怎么配置都保留的权限 */
    public const ALWAYS_PERMS = ['account'];

    /** 一次请求内的表结构探测结果 */
    private static ?bool $ready = null;

    private static array $columnCache = [];

    // ================= 表结构 =================

    /**
     * 代理相关表和字段是否已经就绪
     * 未执行 upgrade_agent.sql 的站点会返回 false，注册流程据此跳过代理归因。
     */
    public static function ready(): bool
    {
        if (self::$ready !== null) {
            return self::$ready;
        }
        try {
            $db = Db::connect('live_mysql');
            $tables = $db->query("SHOW TABLES LIKE 'lp_agent'");
            if (!$tables) {
                return self::$ready = false;
            }
            if (!$db->query("SHOW TABLES LIKE 'lp_agent_domain'")) {
                return self::$ready = false;
            }
            $required = [
                ['lp_agent', 'perms'],
                ['lp_user', 'reg_agent_id'],
                ['lp_user', 'reg_agent_name'],
                ['lp_promo_link', 'agent_id'],
                ['lp_promo_pixel', 'agent_id'],
            ];
            foreach ($required as [$table, $column]) {
                if (!self::hasColumn($table, $column)) {
                    return self::$ready = false;
                }
            }
            return self::$ready = true;
        } catch (\Throwable $e) {
            Log::warning('[agent] 表结构探测失败：' . $e->getMessage());
            return self::$ready = false;
        }
    }

    /** 字段是否存在（带请求内缓存） */
    public static function hasColumn(string $table, string $column): bool
    {
        $key = $table . '.' . $column;
        if (array_key_exists($key, self::$columnCache)) {
            return self::$columnCache[$key];
        }
        try {
            $row = Db::connect('live_mysql')->query(
                'SELECT COUNT(*) AS c FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [$table, $column]
            );
            return self::$columnCache[$key] = ((int) ($row[0]['c'] ?? 0)) > 0;
        } catch (\Throwable) {
            return self::$columnCache[$key] = false;
        }
    }

    // ================= 权限 =================

    /** 把数据库里的 JSON / 逗号串解析成合法权限数组 */
    public static function parsePerms(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return self::DEFAULT_PERMS;
        }
        $raw = trim($raw);
        $list = [];
        if (str_starts_with($raw, '[')) {
            $decoded = json_decode($raw, true);
            $list = is_array($decoded) ? $decoded : [];
        } else {
            $list = preg_split('/[\s,;]+/', $raw) ?: [];
        }
        $out = [];
        foreach ($list as $key) {
            $key = is_string($key) ? trim($key) : '';
            if ($key !== '' && isset(self::PERMS[$key]) && !in_array($key, $out, true)) {
                $out[] = $key;
            }
        }
        // 新建链接依赖「推广链接」查看权限
        if (in_array('link_create', $out, true) && !in_array('link', $out, true)) {
            $out[] = 'link';
        }
        return $out;
    }

    /** 规范化后存库 */
    public static function permsJson(array $keys): string
    {
        $out = [];
        foreach ($keys as $key) {
            $key = is_string($key) ? trim($key) : '';
            if ($key !== '' && isset(self::PERMS[$key]) && !in_array($key, $out, true)) {
                $out[] = $key;
            }
        }
        if (in_array('link_create', $out, true) && !in_array('link', $out, true)) {
            $out[] = 'link';
        }
        return json_encode($out, JSON_UNESCAPED_UNICODE);
    }

    /** 当前代理是否有某个权限 */
    public static function hasPerm(array $agent, string $key): bool
    {
        if (in_array($key, self::ALWAYS_PERMS, true)) {
            return true;
        }
        return in_array($key, self::parsePerms($agent['perms'] ?? null), true);
    }

    // ================= 归因 =================

    /**
     * 注册归因：优先专属域名，其次推广链接名称
     *
     * 域名优先是为了防「在别人域名后面拼自己的 ?name=」抢归属；
     * 平台共享域名下的代理链接仍按 name 归属。
     *
     * @return array{id:int,name:string}
     */
    public static function resolve(string $domain, string $linkName): array
    {
        $empty = ['id' => 0, 'name' => ''];
        if (!self::ready()) {
            return $empty;
        }

        $domain = PromoService::normalizeDomain($domain);
        try {
            $db = Db::connect('live_mysql');

            if ($domain !== '') {
                $row = $db->query(
                    'SELECT a.id, a.name FROM lp_agent_domain d
                     JOIN lp_agent a ON a.id = d.agent_id
                     WHERE d.domain = ? AND d.status = 1 AND a.status = 1
                     LIMIT 1',
                    [$domain]
                );
                if ($row) {
                    return ['id' => (int) $row[0]['id'], 'name' => (string) $row[0]['name']];
                }
            }

            $linkName = mb_substr(trim($linkName), 0, 128);
            if ($linkName !== '') {
                $row = $db->query(
                    'SELECT a.id, a.name FROM lp_promo_link l
                     JOIN lp_agent a ON a.id = l.agent_id
                     WHERE l.name = ? AND l.agent_id > 0 AND a.status = 1
                     LIMIT 1',
                    [$linkName]
                );
                if ($row) {
                    return ['id' => (int) $row[0]['id'], 'name' => (string) $row[0]['name']];
                }
            }
        } catch (\Throwable $e) {
            // 归因失败绝不影响注册
            Log::warning('[agent] 注册归因失败 domain=' . $domain . ' name=' . $linkName . ' err=' . $e->getMessage());
        }

        return $empty;
    }

    // ================= 统计 =================

    /** 单个代理在时间范围内的核心指标 */
    public static function stats(int $agentId, string $from = '', string $to = ''): array
    {
        $out = [
            'regs' => 0, 'buyers' => 0, 'amount' => 0.0,
            'subs' => 0, 'sub_amount' => 0.0, 'conversion' => 0.0,
        ];
        if ($agentId <= 0 || !self::ready()) {
            return $out;
        }

        [$where, $bind] = self::userWhere($agentId, $from, $to);

        try {
            $db = Db::connect('live_mysql');
            $reg = $db->query("SELECT COUNT(*) AS c FROM lp_user u WHERE 1=1 {$where}", $bind);
            $pay = $db->query(
                "SELECT COUNT(DISTINCT o.user_id) AS buyers, IFNULL(SUM(o.pay_amount),0) AS amount
                 FROM lp_recharge_order o JOIN lp_user u ON u.id = o.user_id
                 WHERE o.status = 1 AND o.biz_type <> 'vip' {$where}",
                $bind
            );
            $sub = $db->query(
                "SELECT COUNT(DISTINCT o.user_id) AS subs, IFNULL(SUM(o.pay_amount),0) AS amount
                 FROM lp_recharge_order o JOIN lp_user u ON u.id = o.user_id
                 WHERE o.status = 1 AND o.biz_type = 'vip' {$where}",
                $bind
            );

            $out['regs']       = (int) ($reg[0]['c'] ?? 0);
            $out['buyers']     = (int) ($pay[0]['buyers'] ?? 0);
            $out['amount']     = (float) ($pay[0]['amount'] ?? 0);
            $out['subs']       = (int) ($sub[0]['subs'] ?? 0);
            $out['sub_amount'] = (float) ($sub[0]['amount'] ?? 0);
            $out['conversion'] = $out['regs'] > 0 ? round($out['buyers'] / $out['regs'] * 100, 1) : 0.0;
        } catch (\Throwable $e) {
            Log::warning('[agent] 统计失败 agent=' . $agentId . ' err=' . $e->getMessage());
        }

        return $out;
    }

    /** 总后台：按代理维度汇总（无数据的代理也会列出） */
    public static function allAgentStats(string $from = '', string $to = ''): array
    {
        if (!self::ready()) {
            return [];
        }

        $build = static function (string $sfx) use ($from, $to): array {
            $w = ' AND u.reg_agent_id > 0';
            $b = [];
            if ($from !== '') {
                $w .= ' AND u.created_at >= :from' . $sfx;
                $b['from' . $sfx] = $from . ' 00:00:00';
            }
            if ($to !== '') {
                $w .= ' AND u.created_at <= :to' . $sfx;
                $b['to' . $sfx] = $to . ' 23:59:59';
            }
            return [$w, $b];
        };
        [$w1, $b1] = $build('1');
        [$w2, $b2] = $build('2');
        [$w3, $b3] = $build('3');

        $sql = "SELECT a.id, a.agent_no, a.name, a.username, a.status, a.perms, a.rate,
                       IFNULL(d.regs,0) AS regs,
                       IFNULL(p.buyers,0) AS buyers, IFNULL(p.amount,0) AS amount,
                       IFNULL(v.subs,0) AS subs, IFNULL(v.sub_amount,0) AS sub_amount
                FROM lp_agent a
                LEFT JOIN (
                    SELECT u.reg_agent_id AS k, COUNT(*) AS regs
                    FROM lp_user u WHERE 1=1 {$w1}
                    GROUP BY u.reg_agent_id
                ) d ON d.k = a.id
                LEFT JOIN (
                    SELECT u.reg_agent_id AS k, COUNT(DISTINCT o.user_id) AS buyers, SUM(o.pay_amount) AS amount
                    FROM lp_recharge_order o JOIN lp_user u ON u.id = o.user_id
                    WHERE o.status = 1 AND o.biz_type <> 'vip' {$w2}
                    GROUP BY u.reg_agent_id
                ) p ON p.k = a.id
                LEFT JOIN (
                    SELECT u.reg_agent_id AS k, COUNT(DISTINCT o.user_id) AS subs, SUM(o.pay_amount) AS sub_amount
                    FROM lp_recharge_order o JOIN lp_user u ON u.id = o.user_id
                    WHERE o.status = 1 AND o.biz_type = 'vip' {$w3}
                    GROUP BY u.reg_agent_id
                ) v ON v.k = a.id
                ORDER BY regs DESC, a.id DESC
                LIMIT 500";

        try {
            return Db::connect('live_mysql')->query($sql, array_merge($b1, $b2, $b3));
        } catch (\Throwable $e) {
            Log::warning('[agent] 代理报表失败：' . $e->getMessage());
            return [];
        }
    }

    /** 代理自己的链接 TOP N（按注册数） */
    public static function topLinks(int $agentId, int $limit = 5): array
    {
        if ($agentId <= 0 || !self::ready()) {
            return [];
        }
        $limit = max(1, min(50, $limit));
        try {
            return Db::connect('live_mysql')->query(
                "SELECT reg_link_name AS k, COUNT(*) AS c
                 FROM lp_user
                 WHERE reg_agent_id = ? AND reg_link_name <> ''
                 GROUP BY reg_link_name ORDER BY c DESC LIMIT {$limit}",
                [$agentId]
            );
        } catch (\Throwable) {
            return [];
        }
    }

    /** 代理名下会员分页 */
    public static function memberPage(int $agentId, int $page = 1, int $perPage = 30, string $keyword = ''): array
    {
        $out = ['total' => 0, 'rows' => [], 'page' => 1, 'pages' => 0];
        if ($agentId <= 0 || !self::ready()) {
            return $out;
        }

        $page    = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $where   = 'u.reg_agent_id = :agent';
        $bind    = ['agent' => $agentId];
        if ($keyword !== '') {
            $where .= ' AND (u.email LIKE :kw OR u.nickname LIKE :kw)';
            $bind['kw'] = '%' . $keyword . '%';
        }

        try {
            $db    = Db::connect('live_mysql');
            $total = (int) ($db->query("SELECT COUNT(*) AS c FROM lp_user u WHERE {$where}", $bind)[0]['c'] ?? 0);
            $pages = $total > 0 ? (int) ceil($total / $perPage) : 0;
            $page  = $pages > 0 ? min($page, $pages) : 1;
            $offset = ($page - 1) * $perPage;

            $rows = $db->query(
                "SELECT u.id, u.nickname, u.email, u.created_at, u.reg_link_name,
                        (SELECT IFNULL(SUM(o.pay_amount),0) FROM lp_recharge_order o
                          WHERE o.user_id = u.id AND o.status = 1 AND o.biz_type <> 'vip') AS pay_amount,
                        (SELECT IFNULL(SUM(o.pay_amount),0) FROM lp_recharge_order o
                          WHERE o.user_id = u.id AND o.status = 1 AND o.biz_type = 'vip') AS sub_amount
                 FROM lp_user u WHERE {$where}
                 ORDER BY u.id DESC LIMIT {$offset}, {$perPage}",
                $bind
            );

            $out = ['total' => $total, 'rows' => $rows, 'page' => $page, 'pages' => $pages];
        } catch (\Throwable $e) {
            Log::warning('[agent] 会员列表失败 agent=' . $agentId . ' err=' . $e->getMessage());
        }

        return $out;
    }

    /** 邮箱打码：ab***@gmail.com */
    public static function maskEmail(string $email): string
    {
        $email = trim($email);
        $at = strpos($email, '@');
        if ($at === false) {
            return $email === '' ? '' : mb_substr($email, 0, 1) . '***';
        }
        $local = substr($email, 0, $at);
        $domain = substr($email, $at);
        $len = mb_strlen($local);
        if ($len <= 1) {
            $masked = $local . '***';
        } elseif ($len === 2) {
            $masked = mb_substr($local, 0, 1) . '***';
        } else {
            $masked = mb_substr($local, 0, 2) . '***';
        }
        return $masked . $domain;
    }

    // ================= 代理资源 =================

    /** 代理被分配的域名 */
    public static function domains(int $agentId, bool $onlyEnabled = false): array
    {
        if ($agentId <= 0 || !self::ready()) {
            return [];
        }
        try {
            $q = Db::connect('live_mysql')->table('lp_agent_domain')
                ->where('agent_id', $agentId)->order('is_primary', 'desc')->order('id', 'asc');
            if ($onlyEnabled) {
                $q->where('status', 1);
            }
            return $q->select()->toArray();
        } catch (\Throwable) {
            return [];
        }
    }

    /** 代理被分配的渠道（不含像素 ID / Token 等敏感字段） */
    public static function pixels(int $agentId, bool $onlyEnabled = false): array
    {
        if ($agentId <= 0 || !self::ready()) {
            return [];
        }
        try {
            $q = Db::connect('live_mysql')->table('lp_promo_pixel')
                ->field('id,platform,name,status')
                ->where('agent_id', $agentId)->order('sort', 'asc')->order('id', 'asc');
            if ($onlyEnabled) {
                $q->where('status', 1);
            }
            return $q->select()->toArray();
        } catch (\Throwable) {
            return [];
        }
    }

    /** 生成下一个代理编号 A0001 */
    public static function nextAgentNo(): string
    {
        $db  = Db::connect('live_mysql');
        $max = (int) ($db->query('SELECT IFNULL(MAX(id),0) AS m FROM lp_agent')[0]['m'] ?? 0);
        for ($i = 1; $i <= 200; $i++) {
            $no = 'A' . str_pad((string) ($max + $i), 4, '0', STR_PAD_LEFT);
            $exists = $db->table('lp_agent')->where('agent_no', $no)->count();
            if (!$exists) {
                return $no;
            }
        }
        return 'A' . date('YmdHis');
    }

    /** 代理新建链接时的名称前缀，保证全局唯一 */
    public static function linkName(string $agentNo, string $slug): string
    {
        $slug = trim(preg_replace('/\s+/u', '-', $slug) ?? $slug);
        $slug = mb_substr($slug, 0, 90);
        return mb_substr($agentNo . '-' . $slug, 0, 128);
    }

    /** 组装推广链接 */
    public static function buildLink(string $domain, int $pixelId, string $name, string $channel): string
    {
        $qs = [];
        if ($pixelId > 0) {
            $qs['px'] = (string) $pixelId;
        }
        if ($name !== '') {
            $qs['name'] = $name;
        }
        if ($channel !== '') {
            $qs['ch'] = $channel;
        }
        return 'https://' . $domain . '/' . ($qs !== [] ? '?' . http_build_query($qs) : '');
    }

    // ================= 内部 =================

    /** @return array{0:string,1:array} */
    private static function userWhere(int $agentId, string $from, string $to): array
    {
        $where = ' AND u.reg_agent_id = :agent';
        $bind  = ['agent' => $agentId];
        if ($from !== '') {
            $where .= ' AND u.created_at >= :from';
            $bind['from'] = $from . ' 00:00:00';
        }
        if ($to !== '') {
            $where .= ' AND u.created_at <= :to';
            $bind['to'] = $to . ' 23:59:59';
        }
        return [$where, $bind];
    }
}
