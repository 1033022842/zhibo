<?php
declare(strict_types=1);

namespace app\live\service;

use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use app\live\model\MerchantCertification;
use think\facade\Db;

final class MerchantCertificationService
{
    /**
     * 提交商家认证
     */
    public function submit(int $userId, array $data): MerchantCertification
    {
        $existing = MerchantCertification::where('user_id', $userId)->find();
        if ($existing) {
            if ($existing->status == 1) {
                throw new BusinessException(ResultCode::CERTIFICATION_ALREADY_PASSED);
            }
            if ($existing->status == 0) {
                throw new BusinessException(ResultCode::CERTIFICATION_ALREADY_SUBMITTED);
            }
        }

        Db::startTrans();
        try {
            if ($existing) {
                // 重新提交（之前被拒绝的）
                $cert = $existing;
            } else {
                $cert = new MerchantCertification();
            }
            $cert->user_id       = $userId;
            $cert->email         = $data['email'];
            $cert->id_card_front = $data['id_card_front'];
            $cert->id_card_back  = $data['id_card_back'];
            $cert->status        = 0;
            $cert->reject_reason = '';
            $cert->save();

            Db::commit();
            return $cert;
        } catch (BusinessException $e) {
            Db::rollback();
            throw $e;
        } catch (\Exception $e) {
            Db::rollback();
            throw new BusinessException(ResultCode::SERVER_ERROR, '提交认证失败: ' . $e->getMessage());
        }
    }

    /**
     * 查询认证状态
     */
    public function status(int $userId): ?array
    {
        $cert = MerchantCertification::where('user_id', $userId)->find();
        if (!$cert) {
            return null;
        }
        return $cert->toArray();
    }

    /**
     * 获取认证详情（脱敏）
     */
    public function detail(int $userId): ?array
    {
        $cert = MerchantCertification::where('user_id', $userId)->find();
        if (!$cert) {
            return null;
        }
        $arr = $cert->toArray();

        // 邮箱脱敏
        if (!empty($arr['email'])) {
            $parts = explode('@', $arr['email']);
            $name = $parts[0];
            if (mb_strlen($name) > 2) {
                $name = mb_substr($name, 0, 2) . '***';
            } else {
                $name = mb_substr($name, 0, 1) . '***';
            }
            $arr['email'] = $name . '@' . ($parts[1] ?? '');
        }

        return $arr;
    }
}
