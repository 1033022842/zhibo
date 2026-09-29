<?php
declare(strict_types=1);

namespace app\api\controller;

use think\App;
use app\BaseController;
use app\live\service\InviteService;

/**
 * 邀请好友（Affiliate Program / Inviter 页）
 */
final class Invite extends BaseController
{
    private InviteService $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new InviteService();
    }

    /**
     * 我的邀请码 + 收益统计
     */
    public function index()
    {
        return $this->jsonSuccess($this->service->overview($this->getAuthUserId()));
    }

    /**
     * 自定义邀请码
     */
    public function saveCode()
    {
        $slug = (string) $this->request->post('slug', '');

        return $this->jsonSuccess($this->service->saveCode($this->getAuthUserId(), $slug), 'Saved');
    }
}
