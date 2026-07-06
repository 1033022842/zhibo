<?php
declare(strict_types=1);

namespace app\api\controller;

use think\App;
use app\BaseController;
use app\common\web\ResultCode;
use app\live\service\MerchantCertificationService;
use app\live\validate\MerchantValidate;

final class Merchant extends BaseController
{
    protected array $middleware = [
        \app\live\middleware\Auth::class => ['only' => ['submit', 'status', 'detail']],
    ];

    private MerchantCertificationService $certService;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->certService = new MerchantCertificationService();
    }

    /**
     * 提交商家认证
     */
    public function submit()
    {
        $userId = $this->getAuthUserId();
        $params = $this->request->post();
        $this->validate($params, MerchantValidate::class . '.submit');

        $this->certService->submit($userId, $params);
        return $this->jsonSuccess(null, '认证信息已提交，请等待审核');
    }

    /**
     * 查询认证状态
     */
    public function status()
    {
        $userId = $this->getAuthUserId();
        $cert = $this->certService->status($userId);

        if (!$cert) {
            return $this->jsonSuccess([
                'has_cert'    => false,
                'cert_status' => -1,
                'status_text' => '未认证',
            ]);
        }

        $statusMap = [
            0 => '审核中',
            1 => '已通过',
            2 => '已拒绝',
        ];

        return $this->jsonSuccess([
            'has_cert'      => true,
            'cert_status'   => (int)$cert['status'],
            'status_text'   => $statusMap[$cert['status']] ?? '未知',
            'email'         => $cert['email'],
            'reject_reason' => $cert['reject_reason'],
            'created_at'    => $cert['created_at'],
        ]);
    }

    /**
     * 获取认证详情（脱敏）
     */
    public function detail()
    {
        $userId = $this->getAuthUserId();
        $cert = $this->certService->detail($userId);

        if (!$cert) {
            return $this->jsonFail(ResultCode::CERTIFICATION_NOT_FOUND);
        }

        $statusMap = [
            0 => '审核中',
            1 => '已通过',
            2 => '已拒绝',
        ];
        $cert['status_text'] = $statusMap[$cert['status']] ?? '未知';

        return $this->jsonSuccess($cert);
    }

    /**
     * 上传认证材料图片
     */
    public function upload()
    {
        $file = $this->request->file('file');
        if (!$file) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '请选择要上传的文件');
        }

        $upload = new \app\common\library\Upload($file);
        $upload->setTopic('certification');
        $userId = $this->getAuthUserId();
        $attachment = $upload->upload(null, 0, $userId);
        $url = $attachment['url'];
        if (!str_starts_with($url, 'http')) {
            $url = rtrim($this->request->domain(), '/') . '/' . ltrim($url, '/');
        }
        return $this->jsonSuccess(['url' => $url]);
    }
}
