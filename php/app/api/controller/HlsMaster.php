<?php
declare(strict_types=1);

namespace app\api\controller;

use think\App;

final class HlsMaster extends \app\BaseController
{
    public function __construct(App $app)
    {
        parent::__construct($app);
    }

    public function master($id)
    {
        $id = (int) $id;
        $baseUrl = rtrim($this->request->domain(), '/');

        $lines = [
            '#EXTM3U',
            '#EXT-X-VERSION:7',
            '#EXT-X-MEDIA:TYPE=AUDIO,GROUP-ID="audio",NAME="audio",DEFAULT=YES,AUTOSELECT=YES,URI="' . $baseUrl . '/hls/room/' . $id . '/audio2_stream.m3u8"',
            '#EXT-X-STREAM-INF:BANDWIDTH=2000000,CODECS="avc1.64001F,mp4a.40.2",AUDIO="audio",RESOLUTION=1280x720',
            $baseUrl . '/hls/room/' . $id . '/video1_stream.m3u8',
        ];

        return response(implode("\n", $lines) . "\n", 200, ['Content-Type' => 'application/vnd.apple.mpegurl']);
    }
}
