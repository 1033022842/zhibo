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
 * - POST /api/v1/site/visit    → 落地访问记录（流量来源统计，同一会话去重）
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
        // 代理专属域名访问时，客服渠道用该代理自己的配置（未配置则回落总部）
        return $this->jsonSuccess($this->promo->publicConfig((string) $this->request->host()));
    }

    public function csClick()
    {
        $id = (int) $this->request->post('id', 0);
        return $this->jsonSuccess(['hit' => $this->promo->hit($id)]);
    }

    /**
     * 落地访问上报
     * 前端 tracking.js 每个标签页会话只发一次；重复 / 异常都不影响页面。
     */
    public function visit()
    {
        $payload = $this->request->post();
        if (!is_array($payload) || $payload === []) {
            $decoded = json_decode((string) $this->request->getContent(), true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }
        if (!is_array($payload)) {
            $payload = [];
        }

        // 只接收标量字段，避免恶意数组参数写库时报错
        $allowed = ['vid', 'sid', 'domain', 'landing', 'referer', 'ch', 'l', 'name', 'px',
            'utm_source', 'utm_medium', 'campaign', 'adset', 'ad', 'ua'];
        $clean = [];
        foreach ($allowed as $k) {
            $v = $payload[$k] ?? '';
            if (is_scalar($v)) {
                $clean[$k] = (string) $v;
            }
        }
        $payload = $clean;

        // 境外 CDN（Cloudflare 等）会在请求头带国家代码，有就用，没发就留空
        $country = (string) $this->request->header('cf-ipcountry', '');
        if ($country === '') {
            $country = (string) $this->request->header('x-country-code', '');
        }

        try {
            $ok = $this->promo->recordVisit(
                $payload,
                (string) $this->request->ip(),
                $country
            );
        } catch (\Throwable $e) {
            $ok = false;
        }

        return $this->jsonSuccess(['hit' => $ok]);
    }
}
