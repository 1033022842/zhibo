<?php
declare(strict_types=1);

namespace app\api\controller;

use app\BaseController;
use app\room\service\RoomService;
use think\App;

final class Room extends BaseController
{
    private RoomService $roomService;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->roomService = new RoomService();
    }

    public function feedLive()
    {
        $cursor = $this->request->get('cursor', '');
        $limit = (int) $this->request->get('limit', 10);
        $result = $this->roomService->feedLive($cursor, $limit, $this->request->domain());

        return $this->jsonCursor($result['list'], $result['cursor'], $result['has_more']);
    }

    public function detail(int $id)
    {
        $result = $this->roomService->detail($id, $this->request->domain());
        return $this->jsonSuccess($result);
    }
}
