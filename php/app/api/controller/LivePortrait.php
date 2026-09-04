<?php
declare(strict_types=1);

namespace app\api\controller;

use app\BaseController;
use app\ai\service\LivePortraitService;
use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use think\App;

/**
 * LivePortrait 直播端接口（AI 电脑调用）
 *
 * 鉴权：套用 AiAuth 中间件（请求头 X-Api-Key + X-Worker-Id）
 *   GET /api/v1/liveportrait/config       — 立绘 + 动作模板库 + 公共流推流参数
 *   GET /api/v1/liveportrait/instructions — 实时动作指令（礼物关键词 → 动作模板）
 */
final class LivePortrait extends BaseController
{
    protected array $middleware = [
        \app\ai\middleware\AiAuth::class => ['only' => ['config', 'instructions']],
    ];

    private LivePortraitService $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new LivePortraitService();
    }

    public function config()
    {
        $roomId = (int)$this->request->get('room_id', 0);
        if ($roomId <= 0) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'room_id 不能为空');
        }

        try {
            return $this->jsonSuccess($this->service->config($roomId));
        } catch (BusinessException $e) {
            return $this->jsonFail($e->resultCode, $e->getMessage());
        }
    }

    public function instructions()
    {
        $roomId = (int)$this->request->get('room_id', 0);
        if ($roomId <= 0) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'room_id 不能为空');
        }

        return $this->jsonSuccess($this->service->instructions($roomId));
    }
}
