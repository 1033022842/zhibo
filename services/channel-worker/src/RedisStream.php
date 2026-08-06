<?php
declare(strict_types=1);

namespace ChannelWorker;

/**
 * Redis 关键词消费器（基于 List，兼容 Redis 3.x）
 * 消费 list:keyword:room:{roomId} 中的礼物关键词指令
 */
final class RedisStream
{
    private ?\Redis $redis = null;
    private float $lastPing = 0;

    public function __construct(private readonly array $config)
    {
    }

    /**
     * 连接 Redis（带异常）
     * @throws \RuntimeException
     */
    public function connect(): void
    {
        $this->connectInternal();
        if ($this->redis === null) {
            throw new \RuntimeException('Redis connection failed');
        }
    }

    private function connectInternal(): void
    {
        $host = $this->config['host'] ?? '127.0.0.1';
        $port = (int)($this->config['port'] ?? 6379);
        $password = $this->config['password'] ?? '';
        $db = (int)($this->config['select'] ?? 0);

        $redis = new \Redis();
        try {
            // 使用持久连接，更稳定
            $connected = @$redis->pconnect($host, $port, 3.0, 'channel-worker');
            if (!$connected) {
                $redis = null;
                fwrite(STDERR, "[RedisStream] pconnect failed: host=$host port=$port\n");
                $this->redis = null;
                return;
            }
            if ($password !== '') {
                @$redis->auth($password);
            }
            @$redis->select($db);
            @$redis->setOption(\Redis::OPT_READ_TIMEOUT, 3.0);

            $pong = @$redis->ping();
            if ($pong !== true && $pong !== '+PONG') {
                $redis = null;
                fwrite(STDERR, "[RedisStream] ping failed\n");
            }
        } catch (\Throwable $e) {
            $redis = null;
            fwrite(STDERR, "[RedisStream] connect exception: {$e->getMessage()}\n");
        }

        $this->redis = $redis;
        $this->lastPing = microtime(true);
    }

    /**
     * 拉取属于指定 roomId 的关键词指令（非阻塞）
     * @return array|null {keyword: string}
     */
    public function consumeKeyword(int $roomId): ?array
    {
        if (!$this->redis) {
            $this->connectInternal();
            if (!$this->redis) {
                $this->debugLog('kw_redis_null', 'redis is NULL');
                return null;
            }
        }

        // 每 5 秒 ping 一次检测连接是否存活，断开则重连
        if (microtime(true) - $this->lastPing > 5.0) {
            $this->lastPing = microtime(true);
            if (!$this->isAlive()) {
                $this->redis = null;
                $this->connectInternal();
                return null;
            }
        }

        $listKey = 'stream:room:switch:' . $roomId;
        $payload = @$this->redis->lPop($listKey);

        // lPop 返回 false 可能是空队列或连接断开
        if ($payload === false) {
            $err = @$this->redis->getLastError();
            if ($err !== null && $err !== false) {
                fwrite(STDERR, "[RedisStream] lPop error: {$err}, reconnecting\n");
                $this->debugLog('kw_lpop_err', $err);
                $this->redis = null;
            }
            return null;
        }

        $this->debugLog('kw_lpop_raw', 'type=' . gettype($payload) . ' len=' . strlen((string)$payload) . ' raw=' . substr(var_export($payload, true), 0, 300));

        if (!is_string($payload)) {
            $this->debugLog('kw_lpop_notstr', 'type=' . gettype($payload));
            return null;
        }

        $data = json_decode($payload, true);
        $jsonErr = json_last_error_msg();
        if (!is_array($data)) {
            $this->debugLog('kw_json_fail', 'json_error=' . $jsonErr . ' payload=' . substr($payload, 0, 300));
            return null;
        }

        $params = $data['params'] ?? [];
        $keyword = $params['keyword'] ?? '';
        if ($keyword === '') {
            $this->debugLog('kw_no_keyword', 'params=' . json_encode($params, JSON_UNESCAPED_UNICODE) . ' data_keys=' . implode(',', array_keys($data)));
            return null;
        }

        $this->debugLog('kw_consume_ok', 'keyword=' . $keyword);
        return ['keyword' => $keyword];
    }

    private function debugLog(string $tag, string $msg): void
    {
        $line = date('Y-m-d H:i:s') . " [redis_{$tag}] $msg" . PHP_EOL;
        @file_put_contents(dirname(__DIR__) . '/runtime/debug.log', $line, FILE_APPEND);
    }

    private function isAlive(): bool
    {
        if (!$this->redis) return false;
        try {
            $pong = @$this->redis->ping();
            return $pong === true || $pong === '+PONG';
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function close(): void
    {
        $this->redis?->close();
    }
}
