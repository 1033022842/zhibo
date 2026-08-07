<?php
declare(strict_types=1);

namespace app\api\controller;

use think\App;
use think\facade\Db;
use app\BaseController;
use app\common\web\ResultCode;
use app\live\service\CrowdfundingService;
use app\api\controller\Live as LiveController;

final class Crowdfunding extends BaseController
{
    protected array $middleware = [
        \app\live\middleware\Auth::class => ['only' => [
            'initiate', 'pledge', 'myProjects', 'myPledges', 'linkPersona', 'checkActive', 'balance'
        ]],
    ];

    private CrowdfundingService $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new CrowdfundingService();
    }

    /**
     * 发起众筹（需商家认证）
     */
    public function initiate()
    {
        $userId = $this->getAuthUserId();
        $this->checkMerchantCertified($userId);

        $params = $this->request->post();
        // 基础校验
        if (empty($params['title'])) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '请输入项目标题');
        }
        if (empty($params['persona_name'])) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '请输入角色名称');
        }
        if (empty($params['target_amount']) || (float)$params['target_amount'] <= 0) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '目标金额必须大于0');
        }
        if (empty($params['deadline'])) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '请选择截止时间');
        }
        if (strtotime($params['deadline']) <= time()) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '截止时间必须在当前时间之后');
        }

        $project = $this->service->initiate($userId, $params);
        return $this->jsonSuccess($project->toArray(), '众筹项目已发起');
    }

    /**
     * 支持众筹
     */
    public function pledge()
    {
        $userId = $this->getAuthUserId();
        $projectId = $this->request->post('project_id/d', 0);
        $amount = $this->request->post('amount/f', 0);

        if ($projectId <= 0) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '项目ID无效');
        }
        if ($amount <= 0) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '支持金额必须大于0');
        }

        $this->service->pledge($userId, $projectId, $amount);
        return $this->jsonSuccess(null, '支持成功');
    }

    /**
     * 众筹列表（进行中的，无需登录）
     */
    public function list()
    {
        $page = $this->request->param('page/d', 1);
        $pageSize = $this->request->param('page_size/d', 15);

        $result = $this->service->listActive($page, $pageSize);
        return $this->jsonSuccess($result);
    }

    /**
     * 众筹详情（无需登录）
     */
    public function detail()
    {
        $id = $this->request->param('id/d', 0);
        if ($id <= 0) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '项目ID无效');
        }

        $detail = $this->service->detail($id);
        if (!$detail) {
            return $this->jsonFail(ResultCode::CROWDFUNDING_NOT_FOUND);
        }

        return $this->jsonSuccess($detail);
    }

    /**
     * 我的发起（需登录+商家认证）
     */
    public function myProjects()
    {
        $userId = $this->getAuthUserId();
        $list = $this->service->myProjects($userId);
        return $this->jsonSuccess($list);
    }

    /**
     * 我的支持（需登录）
     */
    public function myPledges()
    {
        $userId = $this->getAuthUserId();
        $list = $this->service->myPledges($userId);
        return $this->jsonSuccess($list);
    }

    /**
     * 关联角色（需登录+商家认证）
     */
    public function linkPersona()
    {
        $userId = $this->getAuthUserId();
        $this->checkMerchantCertified($userId);

        $projectId = $this->request->post('project_id/d', 0);
        $personaId = $this->request->post('persona_id/d', 0);

        if ($projectId <= 0) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '项目ID无效');
        }
        if ($personaId <= 0) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '角色ID无效');
        }

        $this->service->linkPersona($userId, $projectId, $personaId);
        return $this->jsonSuccess(null, '关联成功');
    }

    /**
     * 检查当前用户是否有进行中的众筹
     */
    public function checkActive()
    {
        $userId = $this->getAuthUserId();
        $hasActive = $this->service->hasActiveProject($userId);
        return $this->jsonSuccess(['has_active' => $hasActive]);
    }

    /**
     * 测试：给当前用户加钻石
     */
    public function topup()
    {
        $userId = $this->getAuthUserId();
        $amount = $this->request->post('amount/f', 1000);

        if ($amount <= 0) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '金额必须大于0');
        }

        $wallet = Db::connect('live_mysql')
            ->table('lp_wallet_account')
            ->where('user_id', $userId)
            ->find();

        $balanceBefore = $wallet ? (float)$wallet['diamond_balance'] : 0.00;
        $balanceAfter = bcadd((string)$balanceBefore, (string)$amount, 2);

        if ($wallet) {
            Db::connect('live_mysql')->table('lp_wallet_account')
                ->where('user_id', $userId)
                ->update([
                    'diamond_balance' => $balanceAfter,
                    'updated_at'      => date('Y-m-d H:i:s'),
                ]);
        } else {
            Db::connect('live_mysql')->table('lp_wallet_account')->insert([
                'user_id'         => $userId,
                'diamond_balance' => $balanceAfter,
                'status'          => 1,
                'updated_at'      => date('Y-m-d H:i:s'),
            ]);
        }

        // 记录流水
        Db::connect('live_mysql')->table('lp_wallet_ledger')->insert([
            'user_id'        => $userId,
            'biz_type'       => 'adjust',
            'direction'      => 1,
            'asset_type'     => 'diamond',
            'amount'         => $amount,
            'balance_before' => $balanceBefore,
            'balance_after'  => $balanceAfter,
            'remark'         => '测试充值（众筹测试）',
            'created_at'     => date('Y-m-d H:i:s'),
        ]);

        return $this->jsonSuccess([
            'amount'         => $amount,
            'balance_before' => $balanceBefore,
            'balance_after'  => (float)$balanceAfter,
        ], "已添加 {$amount} 钻石");
    }

    /**
     * 查询当前用户钻石余额
     */
    public function balance()
    {
        $userId = $this->getAuthUserId();
        $wallet = Db::connect('live_mysql')
            ->table('lp_wallet_account')
            ->where('user_id', $userId)
            ->find();

        $balance = $wallet ? (float)$wallet['diamond_balance'] : 0.00;

        return $this->jsonSuccess([
            'balance' => $balance,
            'status'  => $wallet ? (int)$wallet['status'] : 1,
        ]);
    }

    /**
     * 商家认证校验
     */
    private function checkMerchantCertified(int $userId): void
    {
        $cert = \think\facade\Db::connect('live_mysql')
            ->table('lp_merchant_certification')
            ->where('user_id', $userId)
            ->find();

        if (!$cert || (int)$cert['status'] !== 1) {
            throw new \app\common\exception\BusinessException(
                \app\common\web\ResultCode::CERTIFICATION_NOT_FOUND,
                '请先通过商家认证后再发起众筹'
            );
        }
    }
}
