<?php

declare(strict_types=1);

namespace app\live\service;

use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use think\facade\Cache;

/** Per-browser trial across all characters; a secondary daily network cap limits automated churn. */
final class GuestChatService
{
    public const LIMIT = 5;
    private const NETWORK_LIMIT = 50;
    private const TRIAL_TTL = 30 * 86400;
    private const HISTORY_TTL = 86400;
    private const LOCK_TTL = 240;

    public static function validId(string $id): bool
    {
        return preg_match('/\A[a-f0-9]{32}\z/D', $id) === 1;
    }

    private function keys(string $id, string $ip): array
    {
        $prefix = (string) config('cache.stores.redis.prefix', 'live:') . 'guest-chat:v1:';
        return [
            $prefix . 'browser:' . hash('sha256', $id),
            $prefix . 'network:' . hash('sha256', $ip . '|' . gmdate('Y-m-d')),
        ];
    }

    public function remaining(string $id, string $ip): int
    {
        [$session, $network] = $this->keys($id, $ip);
        $redis = Cache::store('redis')->handler();
        return (int) $redis->get($network) >= self::NETWORK_LIMIT
            ? 0 : max(0, self::LIMIT - (int) $redis->get($session));
    }

    public function turn(string $id, string $ip, string $character, string $message, callable $reply): array
    {
        $redis = Cache::store('redis')->handler();
        [$session, $network] = $this->keys($id, $ip);
        $lock = $session . ':lock';
        $historyKey = $session . ':history:' . $character;
        $lease = bin2hex(random_bytes(16));
        // Atomically reserve before doing any upstream work, including across simultaneous tabs.
        $reserved = $redis->eval(<<<'LUA'
local used = tonumber(redis.call('GET', KEYS[1]) or '0')
if used >= tonumber(ARGV[1]) then return -1 end
if redis.call('EXISTS', KEYS[3]) == 1 then return -2 end
local network = tonumber(redis.call('GET', KEYS[2]) or '0')
if network >= tonumber(ARGV[2]) then return -3 end
redis.call('SET', KEYS[3], ARGV[5], 'EX', ARGV[6])
redis.call('INCR', KEYS[1])
redis.call('EXPIRE', KEYS[1], ARGV[3])
if redis.call('INCR', KEYS[2]) == 1 then redis.call('EXPIRE', KEYS[2], ARGV[4]) end
return used + 1
LUA, [$session, $network, $lock, self::LIMIT, self::NETWORK_LIMIT, self::TRIAL_TTL, 86400, $lease, self::LOCK_TTL], 3);
        if ($reserved === -1 || $reserved === -3) {
            throw new BusinessException(ResultCode::GUEST_CHAT_LIMIT, 'Your free trial is finished. Sign in to keep chatting.');
        }
        if ($reserved === -2) {
            throw new BusinessException(ResultCode::RATE_LIMIT, 'Please wait for your current reply.');
        }
        if (!is_int($reserved) || $reserved < 1 || $reserved > self::LIMIT) {
            throw new BusinessException(ResultCode::SERVER_ERROR, 'Guest chat is temporarily unavailable.');
        }

        $complete = false;
        try {
            $history = json_decode((string) $redis->get($historyKey), true);
            if (!is_array($history)) {
                $history = [];
            }
            $text = $reply($history);
            if (!is_string($text) || trim($text) === '') {
                throw new BusinessException(ResultCode::SERVER_ERROR, 'No reply received. Please try again.');
            }
            $history[] = ['role' => 'user', 'content' => $message];
            $history[] = ['role' => 'assistant', 'content' => $text];
            $payload = json_encode(array_slice($history, -10), JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
            $complete = $redis->eval(<<<'LUA'
if redis.call('GET', KEYS[1]) ~= ARGV[1] then return 0 end
redis.call('SET', KEYS[2], ARGV[2], 'EX', ARGV[3])
redis.call('DEL', KEYS[1])
return 1
LUA, [$lock, $historyKey, $lease, $payload, self::HISTORY_TTL], 2) === 1;
            if (!$complete) {
                throw new BusinessException(ResultCode::SERVER_ERROR, 'Guest chat timed out. Please try again.');
            }
            return ['reply' => $text, 'guest' => ['limit' => self::LIMIT, 'remaining' => self::LIMIT - $reserved]];
        } finally {
            if (!$complete) {
                try {
                    // Refund transient upstream failures without refunding or unlocking a newer request.
                    $redis->eval(<<<'LUA'
if redis.call('GET', KEYS[2]) ~= ARGV[1] then return 0 end
if tonumber(redis.call('GET', KEYS[1]) or '0') > 0 then redis.call('DECR', KEYS[1]) end
redis.call('DEL', KEYS[2])
return 1
LUA, [$session, $lock, $lease], 2);
                } catch (\Throwable $ignored) {
                    // Preserve the original upstream error; lease expires automatically.
                }
            }
        }
    }
}
