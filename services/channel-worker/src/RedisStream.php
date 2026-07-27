<?php
declare(strict_types=1);

namespace ChannelWorker;

/**
 * Redis Stream 消费器
 * 消费 stream:room:switch 中的关键词插播指令
 */
final class RedisStream
{
    private const STREAM_KEY = 'stream:room:switch';
    private const GROUP = 'channel-worker';
    private const CONSUMER = 'worker-1';

    private ?\Redis $redis = null;

    public function __construct(private readonly array $config)
    {
    }

    public function connect(): void
    {
        $this->redis = new \Redis();
        $host = $this->config['host'] ?? '127.0.0.1';
        $port = (int)($this->config['port'] ?? 6379);
        $password = $this->config['password'] ?? '';
        $db = (int)($this->config['select'] ?? 0);

        $this->redis->connect($host, $port);
        if ($password !== '') {
            $this->redis->auth($password);
        }
        $this->redis->select($db);

        // 初始化消费者组
        try {
            $this->redis->xGroup('CREATE', self::STREAM_KEY, self::GROUP, '0', true);
        } catch (\RedisException $e) {
            // 组已存在时忽略
            if (!str_contains($e->getMessage(), 'BUSYGROUP')) {
                throw $e;
            }
        }
    }

    /**
     * 拉取属于指定 roomId 的关键词指令
     * @return array|null {keyword: string, message_id: string}
     */
    public function consumeKeyword(int $roomId): ?array
    {
        if (!$this->redis) {
            return null;
        }

        try {
            // 从待消费队列读取
            $msgs = $this->redis->xReadGroup(
                self::GROUP,
                self::CONSUMER,
                [self::STREAM_KEY => '>'],
                1, // count
                0  // block ms (0 = 立即返回)
            );

            if (!$msgs || !isset($msgs[self::STREAM_KEY])) {
                return null;
            }

            foreach ($msgs[self::STREAM_KEY] as $msg) {
                $data = json_decode($msg['data'] ?? '{}', true);
                if (!$data) continue;

                $msgRoomId = (int)($data['room_id'] ?? 0);
                if ($msgRoomId !== $roomId) {
                    continue;
                }

                $commandType = $data['command_type'] ?? '';
                $params = $data['params'] ?? [];

                // 处理关键词指令
                if ($commandType === 'keyword') {
                    $keyword = $params['keyword'] ?? '';
                    if ($keyword !== '') {
                        // 确认消息
                        $this->redis->xAck(self::STREAM_KEY, self::GROUP, [$msg['id']]);
                        return [
                            'keyword' => $keyword,
                            'message_id' => $msg['id'],
                        ];
                    }
                }

                // 其他指令类型暂不处理，但确认消费
                $this->redis->xAck(self::STREAM_KEY, self::GROUP, [$msg['id']]);
            }
        } catch (\Throwable $e) {
            fwrite(STDERR, "[RedisStream] error: {$e->getMessage()}\n");
        }

        return null;
    }

    public function close(): void
    {
        $this->redis?->close();
    }
}
