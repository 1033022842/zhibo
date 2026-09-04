<?php

declare(strict_types=1);

namespace app\api\controller;

use app\BaseController;
use app\common\web\ResultCode;
use think\App;
use think\facade\Db;

/**
 * AI 电脑视频清单上报接口
 *
 * AI 电脑（channel-worker）启动时扫描本地视频目录，把文件清单上报到服务器，
 * 入库为 lp_media_asset（source=machine）。这样后台素材管理能看到每台机器
 * 有哪些视频，进而为房间配置默认播单。
 *
 * 鉴权：套用 AiAuth 中间件（请求头 X-Api-Key + X-Worker-Id）
 * URL：POST /api/machineAsset/report
 *
 * 关键词（keywords）不在本接口处理 —— 沿用数据库已有的关键词配置，
 * 后台可在素材管理页为上报的视频补充关键词。
 */
final class MachineAsset extends BaseController
{
    protected array $middleware = [
        \app\ai\middleware\AiAuth::class => ['only' => ['report']],
    ];

    /**
     * POST /api/machineAsset/report
     *
     * Body:
     *   room_id    int    房间ID（用于确定 persona 归属）
     *   videos     array  视频清单，每项：
     *     - file_name   string  文件名（如 "OK手势.mp4"，用作 title）
     *     - file_url    string  相对 MEDIA_BASE_DIR 的路径（如 "视频成品/OK手势.mp4"）
     *     - duration_ms int     时长毫秒（可为 0）
     *     - checksum    string  文件 sha1（用于去重，空则不去重按 file_url+machine 去重）
     *     - role        string  驱动角色（可选）：portrait=立绘 / motion=动作模板 / 空=成品视频
     *
     * 返回：新增数、跳过数（已存在）、错误信息
     */
    public function report(): \think\response\Json
    {
        $roomId = (int) $this->request->post('room_id', 0);
        $videos = $this->request->post('videos', []);
        $machineId = $this->request->aiWorkerId ?: ('room' . $roomId);

        if ($roomId <= 0) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'room_id 不能为空');
        }
        if (!is_array($videos) || empty($videos)) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'videos 不能为空');
        }

        // 1. 按 room_id 查 lp_room_binding 获取 persona（asset 按 persona 名称归属）
        $persona = $this->resolvePersonaByRoom($roomId);
        if ($persona === '') {
            return $this->jsonFail(ResultCode::PARAM_ERROR, "房间 {$roomId} 未配置 persona，无法归属素材");
        }

        $inserted = 0;
        $skipped = 0;
        $errors = [];

        foreach ($videos as $idx => $v) {
            $fileName = trim((string) ($v['file_name'] ?? ''));
            $fileUrl  = trim((string) ($v['file_url'] ?? ''));
            if ($fileName === '' || $fileUrl === '') {
                $errors[] = "[#{$idx}] file_name/file_url 为空，跳过";
                continue;
            }

            $checksum   = trim((string) ($v['checksum'] ?? ''));
            $durationMs = (int) ($v['duration_ms'] ?? 0);

            // 驱动角色：portrait=立绘 / motion=动作模板 / 空=成品视频（旧策略）
            $role      = trim((string) ($v['role'] ?? ''));
            $assetType = 'video';
            $assetRole = '';
            if ($role === 'portrait') {
                $assetType = 'image';
                $assetRole = 'portrait';
            } elseif ($role === 'motion') {
                $assetType = 'video';
                $assetRole = 'motion';
            }

            // 2. 去重：checksum 优先，否则按 machine_id + remote_path
            if ($checksum !== '') {
                $exists = Db::connect('live_mysql')->table('lp_media_asset')
                    ->where('checksum', $checksum)->where('source', 'machine')->find();
            } else {
                $exists = Db::connect('live_mysql')->table('lp_media_asset')
                    ->where('machine_id', $machineId)->where('remote_path', $fileUrl)->find();
            }
            if ($exists) {
                $skipped++;
                continue;
            }

            // 3. 入库
            try {
                $title = pathinfo($fileName, PATHINFO_FILENAME);
                $assetCode = 'm_' . $machineId . '_' . substr(md5($fileUrl . $checksum), 0, 10);

                Db::connect('live_mysql')->table('lp_media_asset')->insert([
                    'asset_code'  => $assetCode,
                    'asset_type'  => $assetType,
                    'asset_role'  => $assetRole,
                    'scene_type'  => 'public',
                    'keywords'    => '',           // 关键词不在此接口处理，后台补充
                    'persona'     => $persona,
                    'weight'      => 1,
                    'title'       => $title,
                    'file_url'    => $fileUrl,
                    'duration_ms' => $durationMs,
                    'checksum'    => $checksum,
                    'status'      => 1,
                    'source'      => 'machine',
                    'machine_id'  => $machineId,
                    'remote_path' => $fileUrl,
                    'created_at'  => date('Y-m-d H:i:s'),
                ]);
                $inserted++;
            } catch (\Throwable $e) {
                $errors[] = "[{$fileName}] 入库失败: " . $e->getMessage();
            }
        }

        return $this->jsonSuccess([
            'room_id'   => $roomId,
            'persona'   => $persona,
            'machine'   => $machineId,
            'inserted'  => $inserted,
            'skipped'   => $skipped,
            'errors'    => $errors,
        ], "上报完成：新增 {$inserted}，跳过 {$skipped}");
    }

    /**
     * 按 room_id 查 lp_room_binding.persona（asset 表用 persona 名称字符串归属）
     */
    private function resolvePersonaByRoom(int $roomId): string
    {
        $row = Db::connect('live_mysql')->table('lp_room_binding')
            ->where('room_id', $roomId)
            ->value('persona');
        return trim((string) ($row ?? ''));
    }
}
