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
            $cert->user_id          = $userId;
            $cert->real_name        = $data['real_name'];
            $cert->id_card_no       = $data['id_card_no'];
            $cert->phone            = $data['phone'];
            $cert->email            = $data['email'];
            $cert->shop_name        = $data['shop_name'];
            $cert->shop_type        = $data['shop_type'];
            $cert->shop_description = $data['shop_description'] ?? '';
            $cert->id_card_front    = $data['id_card_front'];
            $cert->id_card_back     = $data['id_card_back'];
            $cert->business_license = $data['business_license'];
            $cert->status           = 0;
            $cert->reject_reason    = '';
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

        // 身份证号脱敏：保留前3后4
        if (!empty($arr['id_card_no'])) {
            $len = strlen($arr['id_card_no']);
            if ($len > 7) {
                $arr['id_card_no'] = substr($arr['id_card_no'], 0, 3)
                    . str_repeat('*', $len - 7)
                    . substr($arr['id_card_no'], -4);
            }
        }

        // 手机号脱敏：保留前3后4
        if (!empty($arr['phone'])) {
            $len = strlen($arr['phone']);
            if ($len > 7) {
                $arr['phone'] = substr($arr['phone'], 0, 3)
                    . str_repeat('*', $len - 7)
                    . substr($arr['phone'], -4);
            }
        }

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
