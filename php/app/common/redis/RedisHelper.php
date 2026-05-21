<?php
declare(strict_types=1);

namespace app\common\redis;

use think\facade\Cache;

final class RedisHelper
{
    public static function roomState(int $roomId): string
    {
        return 'room:state:' . $roomId;
    }

    public static function roomOnline(int $roomId): string
    {
        return 'room:online:' . $roomId;
    }

    public static function roomLike(int $roomId): string
    {
        return 'room:like:' . $roomId;
    }

    public static function roomCurrentTask(int $roomId): string
    {
        return 'room:current:task:' . $roomId;
    }

    public static function roomInteractionCooldown(int $roomId): string
    {
        return 'room:interaction:cooldown:' . $roomId;
    }

    public static function streamGiftEvent(): string
    {
        return 'stream:gift:event';
    }

    public static function streamDanmuIngest(): string
    {
        return 'stream:danmu:ingest';
    }

    public static function streamInteractionGenerate(): string
    {
        return 'stream:interaction:generate';
    }

    public static function streamRoomSwitch(): string
    {
        return 'stream:room:switch';
    }

    public static function streamAiTask(): string
    {
        return 'stream:ai:tasks';
    }

    public static function queueInteractionReady(int $roomId): string
    {
        return 'queue:interaction:ready:' . $roomId;
    }

    public static function wsSession(int $connId): string
    {
        return 'ws:session:' . $connId;
    }

    public static function tokenBlacklist(string $token): string
    {
        return 'auth:token:blacklist:' . $token;
    }

    public static function incr(string $key, int $step = 1): int
    {
        return Cache::store('redis')->incr($key, $step);
    }

    public static function decr(string $key, int $step = 1): int
    {
        return Cache::store('redis')->decr($key, $step);
    }

    public static function get(string $key): mixed
    {
        return Cache::store('redis')->get($key);
    }

    public static function set(string $key, mixed $value, int $ttl = 0): bool
    {
        return Cache::store('redis')->set($key, $value, $ttl);
    }

    public static function delete(string $key): bool
    {
        return Cache::store('redis')->delete($key);
    }

    public static function incrOnline(int $roomId): int
    {
        return self::incr(self::roomOnline($roomId));
    }

    public static function decrOnline(int $roomId): int
    {
        return max(0, self::decr(self::roomOnline($roomId)));
    }
}
