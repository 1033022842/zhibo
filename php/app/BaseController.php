<?php
declare(strict_types=1);

namespace app;

use think\App;
use think\Request;
use think\Validate;
use think\response\Json;
use think\exception\ValidateException;
use app\common\web\Result;
use app\common\web\ResultCode;

abstract class BaseController
{
    protected Request $request;

    protected bool $batchValidate = false;

    protected array $middleware = [];

    protected bool $requireAuth = false;

    public function __construct(protected App $app)
    {
        $this->request                 = $this->app->request;
        $this->request->controllerPath = str_replace('.', '/', $this->request->controller(true));

        $this->initialize();
    }

    protected function initialize(): void
    {
    }

    protected function validate(array $data, array|string $validate, array $message = [], bool $batch = false): bool|array|string
    {
        if (is_array($validate)) {
            $v = new Validate();
            $v->rule($validate);
        } else {
            if (strpos($validate, '.')) {
                [$validate, $scene] = explode('.', $validate);
            }
            $class = str_contains($validate, '\\') ? $validate : $this->app->parseClass('validate', $validate);
            $v     = new $class();
            if (!empty($scene)) {
                $v->scene($scene);
            }
        }

        $v->message($message);

        if ($batch || $this->batchValidate) {
            $v->batch();
        }

        return $v->failException()->check($data);
    }

    protected function jsonSuccess(mixed $data = null, string $msg = ''): Json
    {
        return json(Result::success($data, $msg));
    }

    protected function jsonPage(array $list, int $total): Json
    {
        return json(Result::page($list, $total));
    }

    protected function jsonCursor(array $list, ?string $cursor, bool $hasMore): Json
    {
        return json(Result::cursor($list, $cursor, $hasMore));
    }

    protected function jsonFail(ResultCode $code, string $msg = ''): Json
    {
        return json(Result::fail($code, $msg));
    }

    protected function getAuthUserId(): int
    {
        return $this->request->userId ?? 0;
    }
}
