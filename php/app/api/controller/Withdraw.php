<?php
declare(strict_types=1);

namespace app\api\controller;

use think\App;
use app\BaseController;
use app\common\web\ResultCode;
use app\live\service\WithdrawService;

/**
 * 提现（静态站 Financial 页调用）
 */
final class Withdraw extends BaseController
{
    protected array $middleware = [
        \app\live\middleware\Auth::class,
    ];

    private WithdrawService $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new WithdrawService();
    }

    /**
     * GET 收款账户
     */
    public function account()
    {
        return $this->jsonSuccess($this->service->account($this->getAuthUserId()));
    }

    /**
     * POST 绑定收款账户 {network, address}
     */
    public function saveAccount()
    {
        $network = trim((string) $this->request->post('network', 'TRC20'));
        $address = trim((string) $this->request->post('address', ''));
        if ($address === '') {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '地址不能为空');
        }
        $this->service->saveAccount($this->getAuthUserId(), $network, $address);
        return $this->jsonSuccess(null, '保存成功');
    }

    /**
     * GET 试算 ?diamonds=
     */
    public function preview()
    {
        $diamonds = (float) $this->request->param('diamonds', 0);
        if ($diamonds <= 0) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '钻石数量无效');
        }
        return $this->jsonSuccess($this->service->preview($this->getAuthUserId(), $diamonds));
    }

    /**
     * POST 申请提现 {diamonds}
     */
    public function apply()
    {
        $diamonds = (float) $this->request->post('diamonds', 0);
        if ($diamonds <= 0) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '钻石数量无效');
        }
        $result = $this->service->apply($this->getAuthUserId(), $diamonds);
        return $this->jsonSuccess($result, '提现申请已提交，人工打款1-3个工作日');
    }

    /**
     * GET 提现记录
     */
    public function records()
    {
        $page = (int) $this->request->param('page', 1);
        return $this->jsonSuccess($this->service->records($this->getAuthUserId(), $page));
    }
}
