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
        'sub_agent'   => '子代理管理',
        'account'     => '账号设置',
    ];

    /** 权限说明（后台勾选卡片用） */
    public const PERM_DESCS = [
        'overview'    => '查看自己与全部下级代理的注册、充值、订阅与转化',
        'link'        => '查看并复制自己名下的推广链接',
        'link_create' => '允许自助创建、停用和删除推广链接',
        'member'      => '查看自己与下级代理名下的会员明细（邮箱打码）',
        'domain'      => '查看总部/上级分配的专属推广域名',
        'sub_agent'   => '创建和管理自己的下级代理（可继续开下级）',
        'account'     => '修改自己的登录密码（始终可用）',
    ];

    /** 权限短名（列表标签用，避免换行） */
    public const PERM_SHORT = [
        'overview'    => '概览',
        'link'        => '链接',
        'link_create' => '建链',
        'member'      => '会员',
        'domain'      => '域名',
        'sub_agent'   => '子代理',
        'account'     => '账号',
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

    /** 当前代理可以授予下级的权限：只能是它自己拥有的（account 始终保留） */
    public static function grantablePerms(array $agent): array
    {
        $out = [];
        foreach (self::parsePerms($agent['perms'] ?? null) as $key) {
            if (isset(self::PERMS[$key])) {
                $out[] = $key;
            }
        }
        if (!in_array('account', $out, true)) {
            $out[] = 'account';
        }
        return $out;
    }

    /** 把提交的权限裁剪到可授予范围内 */
    public static function restrictPerms(array $keys, array $grantable): array
    {
        $out = [];
        foreach ($keys as $key) {
            $key = is_string($key) ? trim($key) : '';
            if ($key !== '' && in_array($key, $grantable, true) && !in_array($key, $out, true)) {
                $out[] = $key;
            }
        }
        if (!in_array('account', $out, true)) {
            $out[] = 'account';
        }
        return $out;
    }

    // ================= 层级 =================

    /** 全部代理的 id => parent_id（请求内缓存） */
    private static ?array $parentMap = null;

    /** 代理 id => 名称（请求内缓存） */
    private static ?array $nameMap = null;

    private static function parentMap(): array
    {
        if (self::$parentMap !== null) {
            return self::$parentMap;
        }
        $map = [];
        try {
            foreach (Db::connect('live_mysql')->query('SELECT id, parent_id FROM lp_agent') as $row) {
                $map[(int) $row['id']] = (int) $row['parent_id'];
            }
        } catch (\Throwable) {
            // 未升级 parent_id 时按「全部是总部直属」处理
        }
        return self::$parentMap = $map;
    }

    private static function nameMap(): array
    {
        if (self::$nameMap !== null) {
            return self::$nameMap;
        }
        $map = [];
        try {
            foreach (Db::connect('live_mysql')->query('SELECT id, name FROM lp_agent') as $row) {
                $map[(int) $row['id']] = (string) $row['name'];
            }
        } catch (\Throwable) {
        }
        return self::$nameMap = $map;
    }

    public static function agentName(int $agentId): string
    {
        return self::nameMap()[$agentId] ?? '';
    }

    /** 直属下级 */
    public static function children(int $agentId): array
    {
        if ($agentId <= 0 || !self::ready()) {
            return [];
        }
        try {
            return Db::connect('live_mysql')->table('lp_agent')
                ->where('parent_id', $agentId)->order('id', 'asc')->select()->toArray();
        } catch (\Throwable) {
            return [];
        }
    }

    /** 全部下级（含多级） */
    public static function descendantIds(int $agentId): array
    {
        if ($agentId <= 0) {
            return [];
        }
        $map   = self::parentMap();
        $out   = [];
        $queue = [$agentId];
        $seen  = [$agentId => true];
        while ($queue !== []) {
            $cur = (int) array_shift($queue);
            foreach ($map as $id => $parent) {
                if ($parent === $cur && !isset($seen[$id])) {
                    $seen[$id] = true;
                    $out[]     = $id;
                    $queue[]   = $id;
                }
            }
        }
        return $out;
    }

    /** 自己 + 全部下级 */
    public static function teamIds(int $agentId): array
    {
        return $agentId > 0 ? array_merge([$agentId], self::descendantIds($agentId)) : [];
    }

    /** 上级链（由近到远） */
    public static function ancestorIds(int $agentId): array
    {
        $map   = self::parentMap();
        $out   = [];
        $cur   = $map[$agentId] ?? 0;
        $guard = 0;
        while ($cur > 0 && !in_array($cur, $out, true) && $guard++ < 50) {
            $out[] = $cur;
            $cur   = $map[$cur] ?? 0;
        }
        return $out;
    }

    /** 层级深度：总部直属 = 1 */
    public static function depth(int $agentId): int
    {
        return count(self::ancestorIds($agentId)) + 1;
    }

    /** 上级代理 id（0 = 总部直属） */
    public static function parentId(int $agentId): int
    {
        return self::parentMap()[$agentId] ?? 0;
    }

    /** 是否直接下级 */
    public static function isDirectChild(int $parentId, int $agentId): bool
    {
        return $parentId > 0 && $agentId > 0 && self::parentId($agentId) === $parentId;
    }

    /** 能否把 parentId 设为 agentId 的上级（防止自己/下级成环） */
    public static function canSetParent(int $agentId, int $parentId): bool
    {
        if ($parentId === 0) {
            return true;
        }
        if ($parentId === $agentId) {
            return false;
        }
        return !in_array($parentId, self::descendantIds($agentId), true);
    }

    /** 生成子代理编号：A0001-01、A0001-01-01…… */
    public static function nextSubAgentNo(int $parentId): string
    {
        $db  = Db::connect('live_mysql');
        $no  = '';
        if ($parentId > 0) {
            $row = $db->table('lp_agent')->where('id', $parentId)->find();
            $no  = (string) ($row['agent_no'] ?? '');
        }
        $base = $no !== '' ? mb_substr($no, 0, 26) : 'A';
        for ($i = 1; $i <= 200; $i++) {
            $candidate = $base . '-' . str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            if ($db->table('lp_agent')->where('agent_no', $candidate)->count() === 0) {
                return $candidate;
            }
        }
        return self::nextAgentNo();
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

    /** 一个代理或一组代理（团队）在时间范围内的核心指标 */
    public static function stats(int|array $agentIds, string $from = '', string $to = ''): array
    {
        $out = [
            'regs' => 0, 'buyers' => 0, 'amount' => 0.0,
            'subs' => 0, 'sub_amount' => 0.0, 'conversion' => 0.0,
        ];
        $ids = self::normalizeIds($agentIds);
        if ($ids === [] || !self::ready()) {
            return $out;
        }

        [$where, $bind] = self::userWhere($ids, $from, $to);

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
            Log::warning('[agent] 统计失败 agent=' . implode(',', $ids) . ' err=' . $e->getMessage());
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

        $parentField = self::hasColumn('lp_agent', 'parent_id') ? 'a.parent_id' : '0 AS parent_id';
        $sql = "SELECT a.id, {$parentField}, a.agent_no, a.name, a.username, a.status, a.perms, a.rate,
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
    public static function topLinks(int|array $agentIds, int $limit = 5): array
    {
        $ids = self::normalizeIds($agentIds);
        if ($ids === [] || !self::ready()) {
            return [];
        }
        $limit = max(1, min(50, $limit));
        $ph    = implode(',', array_fill(0, count($ids), '?'));
        try {
            return Db::connect('live_mysql')->query(
                "SELECT reg_link_name AS k, COUNT(*) AS c
                 FROM lp_user
                 WHERE reg_agent_id IN ({$ph}) AND reg_link_name <> ''
                 GROUP BY reg_link_name ORDER BY c DESC LIMIT {$limit}",
                $ids
            );
        } catch (\Throwable) {
            return [];
        }
    }

    /** 会员分页（支持单个代理或团队） */
    public static function memberPage(int|array $agentIds, int $page = 1, int $perPage = 30, string $keyword = ''): array
    {
        $out = ['total' => 0, 'rows' => [], 'page' => 1, 'pages' => 0];
        $ids = self::normalizeIds($agentIds);
        if ($ids === [] || !self::ready()) {
            return $out;
        }

        $page    = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $ph      = implode(',', array_fill(0, count($ids), '?'));
        $where   = "u.reg_agent_id IN ({$ph})";
        $bind    = $ids;
        if ($keyword !== '') {
            $where .= ' AND (u.email LIKE ? OR u.nickname LIKE ?)';
            $bind[] = '%' . $keyword . '%';
            $bind[] = '%' . $keyword . '%';
        }

        try {
            $db    = Db::connect('live_mysql');
            $total = (int) ($db->query("SELECT COUNT(*) AS c FROM lp_user u WHERE {$where}", $bind)[0]['c'] ?? 0);
            $pages = $total > 0 ? (int) ceil($total / $perPage) : 0;
            $page  = $pages > 0 ? min($page, $pages) : 1;
            $offset = ($page - 1) * $perPage;

            $rows = $db->query(
                "SELECT u.id, u.nickname, u.email, u.created_at, u.reg_link_name,
                        u.reg_agent_id, u.reg_agent_name,
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
            Log::warning('[agent] 会员列表失败 agent=' . implode(',', $ids) . ' err=' . $e->getMessage());
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

    /** 实际可用的推广域名：自己没有就往上找上级（多级继承） */
    public static function effectiveDomains(int $agentId, bool $onlyEnabled = true): array
    {
        foreach (array_merge([$agentId], self::ancestorIds($agentId)) as $owner) {
            $rows = self::domains((int) $owner, $onlyEnabled);
            if ($rows !== []) {
                foreach ($rows as &$row) {
                    $row['owner_id']  = (int) $owner;
                    $row['inherited'] = (int) $owner !== $agentId;
                }
                unset($row);
                return $rows;
            }
        }
        return [];
    }

    /** 实际可用的渠道：自己没有就往上找上级（多级继承） */
    public static function effectivePixels(int $agentId, bool $onlyEnabled = true): array
    {
        foreach (array_merge([$agentId], self::ancestorIds($agentId)) as $owner) {
            $rows = self::pixels((int) $owner, $onlyEnabled);
            if ($rows !== []) {
                foreach ($rows as &$row) {
                    $row['owner_id']  = (int) $owner;
                    $row['inherited'] = (int) $owner !== $agentId;
                }
                unset($row);
                return $rows;
            }
        }
        return [];
    }

    // ================= 复用总后台（BuildAdmin） =================

    /** 可分配给代理的后台菜单（ba_admin_rule.name => 显示名） */
    public const ADMIN_MENUS = [
        'dashboard'        => '控制台',
        'user/liveUser'    => '会员列表',
        'live/agent'       => '代理管理（子代理）',
        'live/agentReport' => '代理数据',
    ];

    /** 请求内缓存：后台管理员ID => 代理行 */
    private static array $adminAgentCache = [];

    /** 请求内缓存：scope cookie 解析结果 */
    private static ?array $scopeIds = null;

    /** 按菜单 name 取出「菜单 + 只读按钮」的规则 ID（写操作按钮不授予，代理只读） */
    public static function adminRuleIds(array $menuNames): array
    {
        $menuNames = array_values(array_intersect($menuNames, array_keys(self::ADMIN_MENUS)));
        if ($menuNames === []) {
            return [];
        }
        try {
            $rows = Db::name('admin_rule')->field('id,pid,name,type')->select()->toArray();
        } catch (\Throwable) {
            return [];
        }
        $children = [];
        $idByName = [];
        $parentOf = [];
        $isButton = [];
        $readable = [];
        foreach ($rows as $row) {
            $rid                             = (int) $row['id'];
            $children[(int) $row['pid']][]   = $rid;
            $idByName[(string) $row['name']] = $rid;
            $parentOf[$rid]                  = (int) $row['pid'];
            $isButton[$rid]                  = (string) $row['type'] === 'button';
            // 只读按钮：查看 / 详情类
            $readable[$rid]                  = (bool) preg_match('#/(index|detail|info)$#i', (string) $row['name']);
        }
        $out = [];
        foreach ($menuNames as $name) {
            $start = $idByName[$name] ?? 0;
            if ($start <= 0) {
                continue;
            }
            $queue = [$start];
            while ($queue !== []) {
                $cur = (int) array_pop($queue);
                if (in_array($cur, $out, true)) {
                    continue;
                }
                if (!empty($isButton[$cur]) && empty($readable[$cur])) {
                    continue;
                }
                $out[] = $cur;
                foreach ($children[$cur] ?? [] as $child) {
                    $queue[] = $child;
                }
            }
            // 补祖先目录，否则后台菜单树挂不上
            $parent = $parentOf[$start] ?? 0;
            $guard  = 0;
            while ($parent > 0 && !in_array($parent, $out, true) && $guard++ < 10) {
                $out[]  = $parent;
                $parent = $parentOf[$parent] ?? 0;
            }
        }
        sort($out);
        return $out;
    }

    /** 已保存的菜单 name 列表（用于后台表单回显） */
    public static function adminMenuNames(?string $json): array
    {
        $ids = json_decode((string) $json, true);
        if (!is_array($ids) || $ids === []) {
            return [];
        }
        $ids = array_map('intval', $ids);
        try {
            $names = Db::name('admin_rule')->whereIn('id', $ids)->column('name');
            return array_values(array_intersect($names, array_keys(self::ADMIN_MENUS)));
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * 同步代理对应的后台管理员 + 专属角色组
     * 管理员账号与代理账号共用同一密码（bcrypt），代理无需记两套密码。
     */
    public static function syncAdminAccount(array $agent, array $menuNames = []): array
    {
        if (!self::hasColumn('lp_agent', 'admin_id')) {
            return ['ok' => false, 'msg' => '请先执行 upgrade_agent.sql 升级脚本'];
        }
        $agentId = (int) ($agent['id'] ?? 0);
        if ($agentId <= 0) {
            return ['ok' => false, 'msg' => '代理不存在'];
        }

        $db       = Db::connect('live_mysql');
        $agentNo  = (string) ($agent['agent_no'] ?? ('A' . $agentId));
        $username = mb_substr('ag' . strtolower(preg_replace('/[^A-Za-z0-9]/', '', $agentNo) ?: (string) $agentId), 0, 30);
        $nickname = mb_substr((string) ($agent['name'] ?? $agentNo), 0, 30);
        $status   = (int) ($agent['status'] ?? 0) === 1 ? 'enable' : 'disable';
        $hash     = (string) ($agent['password_hash'] ?? '');
        $ruleIds  = self::adminRuleIds($menuNames);
        $now      = time();

        try {
            // 角色组：每个代理一个，便于单独勾选菜单
            $groupId = (int) ($agent['admin_group_id'] ?? 0);
            $group   = $groupId > 0 ? Db::name('admin_group')->where('id', $groupId)->find() : null;
            $groupData = [
                'name'   => mb_substr('代理-' . $agentNo, 0, 50),
                'pid'    => 0,
                'rules'  => implode(',', $ruleIds),
                'status' => 1,
            ];
            if ($group) {
                Db::name('admin_group')->where('id', $groupId)->update($groupData + ['update_time' => $now]);
            } else {
                $groupData['create_time'] = $now;
                $groupData['update_time'] = $now;
                $groupId = (int) Db::name('admin_group')->insertGetId($groupData);
            }

            // 管理员账号
            $adminId = (int) ($agent['admin_id'] ?? 0);
            $admin   = $adminId > 0 ? Db::name('admin')->where('id', $adminId)->find() : null;
            if (!$admin) {
                $admin = Db::name('admin')->where('username', $username)->find();
            }
            $adminData = [
                'username' => $username,
                'nickname' => $nickname,
                'password' => $hash,
                'salt'     => '',
                'status'   => $status,
            ];
            if ($admin) {
                $adminId = (int) $admin['id'];
                Db::name('admin')->where('id', $adminId)->update($adminData + ['update_time' => $now]);
            } else {
                $adminData += ['avatar' => '', 'email' => '', 'mobile' => '', 'create_time' => $now, 'update_time' => $now];
                $adminId = (int) Db::name('admin')->insertGetId($adminData);
            }

            Db::name('admin_group_access')->where('uid', $adminId)->delete();
            Db::name('admin_group_access')->insert(['uid' => $adminId, 'group_id' => $groupId]);

            $db->table('lp_agent')->where('id', $agentId)->update([
                'admin_id'       => $adminId,
                'admin_group_id' => $groupId,
                'admin_rules'    => json_encode($ruleIds, JSON_UNESCAPED_UNICODE),
                'updated_at'     => date('Y-m-d H:i:s'),
            ]);

            return ['ok' => true, 'admin_id' => $adminId, 'group_id' => $groupId, 'username' => $username];
        } catch (\Throwable $e) {
            Log::warning('[agent] 同步后台账号失败 agent=' . $agentId . ' err=' . $e->getMessage());
            return ['ok' => false, 'msg' => '同步后台账号失败：' . $e->getMessage()];
        }
    }

    /** 为代理签发一个后台管理员 Token（不清理旧 Token，允许多端） */
    public static function issueAdminToken(int $adminId, int $ttl = 604800): string
    {
        $token = bin2hex(random_bytes(32));
        \app\common\facade\Token::set($token, 'admin', $adminId, $ttl);
        return $token;
    }

    /** 当前后台管理员是否绑定某个代理 */
    public static function agentByAdminId(int $adminId): ?array
    {
        if ($adminId <= 0) {
            return null;
        }
        if (array_key_exists($adminId, self::$adminAgentCache)) {
            return self::$adminAgentCache[$adminId];
        }
        try {
            $row = Db::connect('live_mysql')->table('lp_agent')->where('admin_id', $adminId)->find();
            return self::$adminAgentCache[$adminId] = ($row ?: null);
        } catch (\Throwable) {
            return self::$adminAgentCache[$adminId] = null;
        }
    }

    /** 总后台所在域名（未配置时用当前域名） */
    public static function adminHost(): string
    {
        $host = trim((string) env('AGENT_ADMIN_HOST', ''));
        return preg_replace('#^https?://#i', '', rtrim($host, '/')) ?: '';
    }

    /** 该后台登录名是否属于代理（代理后台登录页免图形验证码） */
    public static function isAgentLoginName(string $username): bool
    {
        $username = strtolower(trim($username));
        if ($username === '') {
            return false;
        }
        try {
            $admin = Db::name('admin')->where('username', $username)->find();
            if (!$admin) {
                return false;
            }
            return (bool) Db::connect('live_mysql')->table('lp_agent')
                ->where('admin_id', (int) $admin['id'])->find();
        } catch (\Throwable) {
            return false;
        }
    }

    /** 代理的后台登录名（列表展示用）：admin_id => username */
    public static function adminUsernames(array $adminIds): array
    {
        $adminIds = self::normalizeIds($adminIds);
        if ($adminIds === []) {
            return [];
        }
        try {
            return Db::name('admin')->whereIn('id', $adminIds)->column('username', 'id');
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * 后台登录页允许用「代理中心账号」登录：
     * 传 portal username 时映射成对应的后台账号；映射不到原样返回
     */
    public static function mapLoginName(string $username): string
    {
        $username = trim($username);
        if ($username === '') {
            return $username;
        }
        try {
            // 与管理员同名时以管理员为准，避免顶掉总部账号
            if (Db::name('admin')->where('username', $username)->find()) {
                return $username;
            }
            $row = Db::connect('live_mysql')->table('lp_agent')->where('username', $username)->find();
            if (!$row || (int) ($row['admin_id'] ?? 0) <= 0) {
                return $username;
            }
            $name = (string) Db::name('admin')->where('id', (int) $row['admin_id'])->value('username');
            return $name !== '' ? $name : $username;
        } catch (\Throwable) {
            return $username;
        }
    }

    /** 跨域名进入总后台的一次性票据（120 秒有效） */
    public static function makeTicket(int $agentId, int $ttl = 120): string
    {
        $exp = time() + $ttl;
        return $agentId . '.' . $exp . '.' . self::ticketSign($agentId, $exp);
    }

    /** 校验票据，返回代理 ID（失败返回 0） */
    public static function verifyTicket(string $ticket): int
    {
        $parts = explode('.', $ticket);
        if (count($parts) !== 3) {
            return 0;
        }
        $agentId = (int) $parts[0];
        $exp     = (int) $parts[1];
        $sig     = (string) $parts[2];
        if ($agentId <= 0 || $exp < time() || $sig === '') {
            return 0;
        }
        return hash_equals(self::ticketSign($agentId, $exp), $sig) ? $agentId : 0;
    }

    private static function ticketSign(int $agentId, int $exp): string
    {
        return substr(hash_hmac(
            'sha256',
            'agent-ticket|' . $agentId . '|' . $exp,
            (string) config('buildadmin.token.key')
        ), 0, 32);
    }

    /** 写入代理数据范围 Cookie（iframe 页面用它识别代理身份） */
    public static function setScopeCookie(int $agentId): void
    {
        $sig    = self::scopeSignature($agentId);
        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        header(
            'Set-Cookie: agent_scope=' . $agentId . '.' . $sig . '; Path=/; Max-Age=604800; HttpOnly; SameSite=Lax'
            . ($secure ? '; Secure' : ''),
            false
        );
    }

    public static function clearScopeCookie(): void
    {
        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        header(
            'Set-Cookie: agent_scope=; Path=/; Max-Age=0; HttpOnly; SameSite=Lax'
            . ($secure ? '; Secure' : ''),
            false
        );
    }

    /**
     * 代理直接从 /admin/ 登录时补写数据范围 Cookie，并让本次请求立即生效
     * （scopeTeamIds 有请求内缓存，必须先写入缓存再继续执行）
     */
    public static function primeScope(int $agentId): void
    {
        self::setScopeCookie($agentId);
        self::$scopeIds = self::teamIds($agentId);
    }

    /** 从 Cookie 解析出团队代理 ID（非代理返回空数组） */
    public static function scopeTeamIds(): array
    {
        if (self::$scopeIds !== null) {
            return self::$scopeIds;
        }
        $agentId = self::scopeAgentId();
        return self::$scopeIds = ($agentId > 0 ? self::teamIds($agentId) : []);
    }

    /** 从签名 Cookie 解析出当前代理 ID（非代理 / 已停用返回 0） */
    public static function scopeAgentId(): int
    {
        $raw = (string) ($_COOKIE['agent_scope'] ?? '');
        if ($raw === '') {
            return 0;
        }
        $parts   = explode('.', $raw, 2);
        $agentId = (int) ($parts[0] ?? 0);
        $sig     = (string) ($parts[1] ?? '');
        if ($agentId <= 0 || $sig === '' || !hash_equals(self::scopeSignature($agentId), $sig)) {
            return 0;
        }
        try {
            $row = Db::connect('live_mysql')->table('lp_agent')->where('id', $agentId)->find();
            if (!$row || (int) $row['status'] !== 1) {
                return 0;
            }
        } catch (\Throwable) {
            return 0;
        }
        return $agentId;
    }

    private static function scopeSignature(int $agentId): string
    {
        return substr(hash_hmac(
            'sha256',
            'agent-scope|' . $agentId,
            (string) config('buildadmin.token.key')
        ), 0, 32);
    }

    /** 给后台列表查询追加数据范围（按表名） */
    public static function queryScope(string $table, string $alias, array $ids): ?array
    {
        $table = strtolower($table);
        $ids   = self::normalizeIds($ids);
        if ($ids === []) {
            return null;
        }
        if ($table === 'lp_user') {
            return [$alias . 'reg_agent_id', 'in', $ids];
        }
        if ($table === 'lp_promo_link' || $table === 'lp_promo_pixel') {
            return [$alias . 'agent_id', 'in', $ids];
        }
        return null;
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

    /** 组装短推广链接：https://域名/?px=像素ID&l=链接ID（名称/渠道由服务端按 l 反解） */
    public static function buildLink(string $domain, int $linkId, int $pixelId): string
    {
        $qs = [];
        if ($pixelId > 0) {
            $qs['px'] = (string) $pixelId;
        }
        $qs['l'] = (string) $linkId;
        return 'https://' . $domain . '/?' . http_build_query($qs);
    }

    // ================= 内部 =================

    /** @return int[] 去重后的正整数 ID */
    private static function normalizeIds(int|array $agentIds): array
    {
        $out = [];
        foreach ((array) $agentIds as $id) {
            $id = (int) $id;
            if ($id > 0 && !in_array($id, $out, true)) {
                $out[] = $id;
            }
        }
        return $out;
    }

    /** @param int[] $ids @return array{0:string,1:array} */
    private static function userWhere(array $ids, string $from, string $to): array
    {
        $ids = self::normalizeIds($ids);
        if ($ids === []) {
            return [' AND 1=0', []];
        }
        $placeholders = [];
        $bind         = [];
        foreach ($ids as $i => $id) {
            $placeholders[]   = ':ag' . $i;
            $bind['ag' . $i] = $id;
        }
        $where = ' AND u.reg_agent_id IN (' . implode(',', $placeholders) . ')';
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
