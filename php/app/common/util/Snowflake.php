<?php
declare(strict_types=1);

namespace app\common\util;

final class Snowflake
{
    private const EPOCH = 1700000000000;
    private const WORKER_ID_BITS = 5;
    private const DATACENTER_ID_BITS = 5;
    private const SEQUENCE_BITS = 12;

    private int $sequence = 0;
    private int $lastTimestamp = -1;

    public function __construct(
        private readonly int $workerId = 1,
        private readonly int $datacenterId = 1
    ) {}

    public function nextId(): int
    {
        $timestamp = $this->currentMillis();

        if ($timestamp < $this->lastTimestamp) {
            throw new \RuntimeException('Clock moved backwards');
        }

        if ($timestamp === $this->lastTimestamp) {
            $maxSequence = (1 << self::SEQUENCE_BITS) - 1;
            $this->sequence = ($this->sequence + 1) & $maxSequence;
            if ($this->sequence === 0) {
                $timestamp = $this->waitNextMillis($this->lastTimestamp);
            }
        } else {
            $this->sequence = 0;
        }

        $this->lastTimestamp = $timestamp;

        return (($timestamp - self::EPOCH) << (self::WORKER_ID_BITS + self::DATACENTER_ID_BITS + self::SEQUENCE_BITS))
            | ($this->datacenterId << (self::WORKER_ID_BITS + self::SEQUENCE_BITS))
            | ($this->workerId << self::SEQUENCE_BITS)
            | $this->sequence;
    }

    private function currentMillis(): int
    {
        return (int)(microtime(true) * 1000);
    }

    private function waitNextMillis(int $lastTimestamp): int
    {
        $timestamp = $this->currentMillis();
        while ($timestamp <= $lastTimestamp) {
            $timestamp = $this->currentMillis();
        }
        return $timestamp;
    }
}
