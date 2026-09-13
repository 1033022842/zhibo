<?php
declare(strict_types=1);

namespace app\api\controller;

use think\App;
use app\BaseController;
use app\common\web\ResultCode;
use app\live\service\RechargeService;

final class Recharge extends BaseController
{
    protected array $middleware = [
        \app\live\middleware\Auth::class => ['only' => ['channels', 'submit', 'orders', 'status', 'upload']],
    ];

    private RechargeService $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new RechargeService();
    }

    /**
     * 可用充值渠道
     */
    public function channels()
    {
        $list = $this->service->channels();
        // 将相对路径的二维码 URL 转为绝对 URL
        $domain = rtrim($this->request->domain(), '/');
        foreach ($list as &$ch) {
            if (!empty($ch['qr_code_url']) && !str_starts_with($ch['qr_code_url'], 'http')) {
                $ch['qr_code_url'] = $domain . '/' . ltrim($ch['qr_code_url'], '/');
            }
        }
        return $this->jsonSuccess($list);
    }

    /**
     * 提交充值订单
     */
    public function submit()
    {
        $userId = $this->getAuthUserId();
        $channelId = $this->request->post('channel_id/d', 0);
        $amount = $this->request->post('amount/f', 0);
        $proofImage = $this->request->post('proof_image', '');

        if ($channelId <= 0) return $this->jsonFail(ResultCode::PARAM_ERROR, '请选择充值渠道');
        if ($amount <= 0) return $this->jsonFail(ResultCode::PARAM_ERROR, '请输入充值金额');

        $order = $this->service->submitOrder($userId, $channelId, $amount, $proofImage);
        return $this->jsonSuccess($order, '订单已提交，请等待审核');
    }

    /**
     * 充值记录
     */
    /**
     * 订单状态轮询（付款等待页 5s 一次）
     */
    public function status()
    {
        $userId = $this->getAuthUserId();
        $orderNo = (string) $this->request->param('order_no', '');
        if ($orderNo === '') {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '参数错误');
        }
        $order = \think\facade\Db::name('recharge_order')
            ->where('order_no', $orderNo)
            ->where('user_id', $userId)
            ->find();
        if (!$order) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '订单不存在');
        }
        return $this->jsonSuccess([
            'order_no'       => $order['order_no'],
            'status'         => (int) $order['status'],
            'pay_amount'     => $order['pay_amount'],
            'diamond_amount' => $order['diamond_amount'],
            'expire_at'      => $order['expire_at'] ?? '',
        ]);
    }

    public function orders()
    {
        $userId = $this->getAuthUserId();
        $page = $this->request->param('page/d', 1);
        $result = $this->service->orderList($userId, $page);
        return $this->jsonSuccess($result);
    }

    /**
     * 上传凭证图片
     */
    public function upload()
    {
        $file = $this->request->file('file');
        if (!$file) return $this->jsonFail(ResultCode::PARAM_ERROR, '请选择文件');
        $upload = new \app\common\library\Upload($file);
        $upload->setTopic('recharge');
        $userId = $this->getAuthUserId();
        $attachment = $upload->upload(null, 0, $userId);
        $url = $attachment['url'];
        if (!str_starts_with($url, 'http')) {
            $url = rtrim($this->request->domain(), '/') . '/' . ltrim($url, '/');
        }
        return $this->jsonSuccess(['url' => $url]);
    }
}
