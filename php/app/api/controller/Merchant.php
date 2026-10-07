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
     * 经营类目选项
     */
    public function shopTypes()
    {
        $types = [
            ['value' => 'food', 'label' => 'Food'],
            ['value' => 'clothing', 'label' => 'Clothing'],
            ['value' => 'digital', 'label' => 'Electronics'],
            ['value' => 'beauty', 'label' => 'Beauty'],
            ['value' => 'home', 'label' => 'Home & Living'],
            ['value' => 'sports', 'label' => 'Sports'],
            ['value' => 'baby', 'label' => 'Baby & Kids'],
            ['value' => 'pet', 'label' => 'Pets'],
            ['value' => 'book', 'label' => 'Books'],
            ['value' => 'other', 'label' => 'Other'],
        ];
        return $this->jsonSuccess($types);
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
        return $this->jsonSuccess(null, 'Verification submitted. Please wait for review.');
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
                'status_text' => 'Not verified',
            ]);
        }

        $statusMap = [
            0 => 'Under review',
            1 => 'Approved',
            2 => 'Rejected',
        ];

        return $this->jsonSuccess([
            'has_cert'      => true,
            'cert_status'   => (int)$cert['status'],
            'status_text'   => $statusMap[$cert['status']] ?? 'Unknown',
            'shop_name'     => $cert['shop_name'] ?? '',
            'email'         => $cert['email'] ?? '',
            'reject_reason' => $cert['reject_reason'] ?? '',
            'created_at'    => $cert['created_at'] ?? '',
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
            0 => 'Under review',
            1 => 'Approved',
            2 => 'Rejected',
        ];
        $cert['status_text'] = $statusMap[$cert['status']] ?? 'Unknown';

        return $this->jsonSuccess($cert);
    }

    /**
     * 上传认证材料图片
     */
    public function upload()
    {
        $file = $this->request->file('file');
        if (!$file) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'Please select a file to upload');
        }

        $upload = new \app\common\library\Upload($file);
        $upload->setTopic('certification');
        $userId = $this->getAuthUserId();
        $attachment = $upload->upload(null, 0, $userId);
        // 返回相对路径（/storage/...）：避免拼接请求域名导致跨域协议锁定与 Mixed Content
        $url = $attachment['url'];
        if (str_starts_with($url, 'http')) {
            $parsed = parse_url($url);
            $url = $parsed['path'] ?? $url;
        }
        return $this->jsonSuccess(['url' => $url]);
    }
}
