<?php
declare(strict_types=1);

namespace app\api\controller;

use think\App;
use app\BaseController;
use app\live\service\PromoService;

/**
 * 站点公开配置（静态站每个页面都会拉一次）
 *
 * - GET  /api/v1/site/config   → 像素配置 + 客服渠道（白名单字段，60s 缓存）
 * - POST /api/v1/site/csClick  → 客服渠道点击计数
 */
final class Site extends BaseController
{
    private PromoService $promo;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->promo = new PromoService();
    }

    public function config()
    {
        // 顺带把这个域名收进「前端域名」列表，供后台配置像素推广域名时下拉选择
        try {
            $this->promo->rememberDomain((string) $this->request->host());
        } catch (\Throwable $e) {
            // 收录失败不影响配置下发
        }
        return $this->jsonSuccess($this->promo->publicConfig());
    }

    public function csClick()
    {
        $id = (int) $this->request->post('id', 0);
        return $this->jsonSuccess(['hit' => $this->promo->hit($id)]);
    }
}
