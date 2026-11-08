<?php
declare(strict_types=1);

namespace app\live\service;

use think\facade\Db;
use think\facade\Log;

/**
 * 行为埋点采集 + 用户登录日志
 *
 * - 前端 tracking.js 批量上报 → collect() 写 lp_track_event
 *   （pv=页面浏览行，duration=0；leave 回写同会话同页面最近一条 pv 的停留秒数）
 * - 登录统一收口 UserService::issueTokens → logLogin() 写 lp_user_login_log
 * 统计失败绝不影响业务：所有异常都吞掉。
 */
final class TrackService
{
    /** 单次上报事件数上限 */
    public const MAX_BATCH = 50;

    /** 单页停留秒数封顶（防挂后台一整天的脏数据） */
    private const MAX_DURATION = 172800;

    /** bot UA 正则（与 PromoService::recordVisit 保持一致） */
    private const BOT_UA_REGEX =
        '/googlebot|bingbot|yandex|baiduspider|ahrefs|semrush|mj12|dotbot|petalbot|bytespider|crawler|spider|slurp'
        . '|headless|lighthouse|pingdom|uptime|curl|wget|python-requests|go-http-client|facebookexternalhit|telegrambot'
        . '|(?:^|[^a-z])bot(?:[\/\s;)]|$)/i';

    public static function isBotUa(string $ua): bool
    {
        $ua = strtolower(substr(trim($ua), 0, 255));
        return $ua !== '' && preg_match(self::BOT_UA_REGEX, $ua) === 1;
    }

    /**
     * 批量落库行为事件
     *
     * @param array $events 每条：type=pv|event|leave + page/title/event/props/referrer/duration/user_id
     * @param array $context 批次上下文（vid/sid/domain），单条缺省时回退使用
     * @return int 实际写入条数
     */
    public static function collect(array $events, string $ip, string $ua, array $context = []): int
    {
        if (self::isBotUa($ua)) {
            return 0;
        }

        $ctxVid    = self::clip(trim((string) ($context['vid'] ?? '')), 64);
        $ctxSid    = self::clip(trim((string) ($context['sid'] ?? '')), 64);
        $ctxDomain = self::clip(trim((string) ($context['domain'] ?? '')), 120);

        $now    = date('Y-m-d H:i:s');
        $rows   = [];
        $leaves = [];
        foreach (array_slice($events, 0, self::MAX_BATCH) as $ev) {
            if (!is_array($ev)) {
                continue;
            }
            // 会话ID是行为链路的主键：单条没带就回退批次上下文，再没有才丢弃
            $sid = self::clip(trim((string) ($ev['sid'] ?? '')), 64);
            if ($sid === '') {
                $sid = $ctxSid;
            }
            if ($sid === '') {
                continue;
            }
            $vid = self::clip(trim((string) ($ev['vid'] ?? '')), 64);
            if ($vid === '') {
                $vid = $ctxVid;
            }
            $domain = self::clip(trim((string) ($ev['domain'] ?? '')), 120);
            if ($domain === '') {
                $domain = $ctxDomain;
            }

            $type = trim((string) ($ev['type'] ?? 'event'));
            if ($type === 'leave') {
                $leaves[] = [
                    'sid'      => $sid,
                    'vid'      => $vid,
                    'domain'   => $domain,
                    'page'     => self::clip(trim((string) ($ev['page'] ?? '')), 255),
                    'title'    => self::clip(trim((string) ($ev['title'] ?? '')), 128),
                    'duration' => min(max(0, (int) ($ev['duration'] ?? 0)), self::MAX_DURATION),
                    'user_id'  => max(0, (int) ($ev['user_id'] ?? 0)),
                ];
                continue;
            }

            $name = $type === 'pv' ? 'pageview' : self::clip(trim((string) ($ev['event'] ?? '')), 64);
            if ($name === '') {
                continue;
            }
            $rows[] = [
                'vid'        => $vid,
                'sid'        => $sid,
                'user_id'    => max(0, (int) ($ev['user_id'] ?? 0)),
                'domain'     => $domain,
                'page'       => self::clip(trim((string) ($ev['page'] ?? '')), 255),
                'title'      => self::clip(trim((string) ($ev['title'] ?? '')), 128),
                'event'      => $name,
                'props'      => self::clip(trim((string) ($ev['props'] ?? '')), 2000),
                'referrer'   => self::clip(trim((string) ($ev['referrer'] ?? '')), 255),
                'duration'   => 0,
                'ip'         => self::clip($ip, 45),
                'ua'         => self::clip(trim($ua), 255),
                'created_at' => $now,
            ];
        }
        if ($rows === [] && $leaves === []) {
            return 0;
        }

        $db = Db::connect('live_mysql');
        $written = 0;
        try {
            if ($rows !== []) {
                $written += (int) $db->table('lp_track_event')->insertAll($rows);
            }
            foreach ($leaves as $lv) {
                $written += self::applyLeave($db, $lv, $ip, $ua, $now);
            }
        } catch (\Throwable $e) {
            Log::warning('[track] collect 写入失败: ' . $e->getMessage());
        }
        return $written;
    }

    /**
     * 页面离开：把停留秒数回写到「同会话同页面」最近一条未结算(duration=0)的 pageview 行；
     * 找不到（pv 上报丢失）就补一条带时长的浏览行，保证报表口径完整。
     */
    private static function applyLeave($db, array $lv, string $ip, string $ua, string $now): int
    {
        $sid  = (string) $lv['sid'];
        $page = (string) $lv['page'];
        if ($sid === '' || $page === '') {
            return 0;
        }

        try {
            $updated = $db->table('lp_track_event')
                ->where('sid', $sid)
                ->where('page', $page)
                ->where('event', 'pageview')
                ->where('duration', 0)
                ->order('id', 'desc')
                ->limit(1)
                ->update(['duration' => (int) $lv['duration']]);
            if ((int) $updated > 0) {
                return 1;
            }

            // pv 行丢失：补一条已结算的浏览行
            $db->table('lp_track_event')->insert([
                'vid'        => (string) $lv['vid'],
                'sid'        => $sid,
                'user_id'    => (int) $lv['user_id'],
                'domain'     => (string) $lv['domain'],
                'page'       => $page,
                'title'      => (string) $lv['title'],
                'event'      => 'pageview',
                'props'      => '',
                'referrer'   => '',
                'duration'   => (int) $lv['duration'],
                'ip'         => self::clip($ip, 45),
                'ua'         => self::clip(trim($ua), 255),
                'created_at' => $now,
            ]);
            return 1;
        } catch (\Throwable $e) {
            Log::warning('[track] applyLeave 失败: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * 登录日志（成功/失败都记）。任何异常吞掉，绝不影响登录主流程。
     *
     * @param string $vid 埋点访客ID；为空时兜底从当前请求参数读取
     */
    public static function logLogin(
        int $userId,
        string $account,
        bool $success,
        string $failReason = '',
        bool $isRegister = false,
        string $vid = '',
        string $sid = ''
    ): void {
        try {
            $req = request();
            $ip  = $req ? (string) $req->ip() : '';
            $ua  = $req ? substr(trim((string) $req->header('user-agent', '')), 0, 255) : '';
            if ($vid === '') {
                $vid = self::clip(trim((string) ($req?->param('vid') ?? '')), 64);
            }
            if ($sid === '') {
                $sid = self::clip(trim((string) ($req?->param('sid') ?? '')), 64);
            }
            if ($account === '') {
                $account = trim((string) ($req?->param('account') ?? $req?->param('username') ?? $req?->param('email') ?? ''));
            }

            Db::connect('live_mysql')->table('lp_user_login_log')->insert([
                'user_id'     => max(0, $userId),
                'account'     => self::clip($account, 128),
                'success'     => $success ? 1 : 0,
                'is_register' => $isRegister ? 1 : 0,
                'fail_reason' => self::clip($failReason, 128),
                'vid'         => $vid,
                'sid'         => $sid,
                'ip'          => self::clip($ip, 45),
                'ua'          => $ua,
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[track] logLogin 失败: ' . $e->getMessage());
        }
    }

    private static function clip(string $v, int $max): string
    {
        return mb_substr($v, 0, $max);
    }
}
