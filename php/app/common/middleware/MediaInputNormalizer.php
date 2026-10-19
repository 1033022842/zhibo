<?php
declare(strict_types=1);

namespace app\common\middleware;

use Closure;
use think\Request;
use think\Response;
use app\common\library\media\Media;

/**
 * 入参还原：签名地址 → 站内路径
 *
 * 后台编辑表单里显示的是签名 URL（因为输出被 MediaUrlSigner 改写了），
 * 用户保存时会把这段地址原样回传。本中间件在进入控制器前把它还原成
 * /storage/xxx，保证数据库里存的永远是稳定逻辑路径，不会因签名过期而失效。
 */
class MediaInputNormalizer
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Media::enabled()) {
            return $next($request);
        }

        $get = $request->get();
        if (is_array($get)) {
            $request->withGet($this->walk($get));
        }

        $post = $request->post();
        if (is_array($post)) {
            $request->withPost($this->walk($post));
        }

        return $next($request);
    }

    private function walk(array $data): array
    {
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $data[$k] = $this->walk($v);
            } elseif (is_string($v) && $v !== '' && Media::isStoragePath($v)) {
                $data[$k] = Media::toStoragePath($v);
            }
        }
        return $data;
    }
}
