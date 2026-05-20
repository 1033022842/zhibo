<?php
declare(strict_types=1);

namespace app\websocket\protocol;

final class MessageBuilder
{
    public static function encode(string $type, string $traceId = '', array $data = [], string $msg = 'ok', string $code = '00000'): string
    {
        return json_encode([
            'type' => $type,
            'trace_id' => $traceId,
            'code' => $code,
            'msg' => $msg,
            'data' => $data,
            'ts' => time(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function error(string $traceId, string $msg, string $code = 'WS0001', array $data = []): string
    {
        return self::encode(ServerMessageType::ERROR, $traceId, $data, $msg, $code);
    }
}
