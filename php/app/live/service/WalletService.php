<?php
declare(strict_types=1);

namespace app\live\service;

use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use think\facade\Db;

/**
 * 钻石钱包公共服务（行锁 + 双录流水）
 *
 * 所有资金变动（充值/送礼/众筹/会员/提现）统一走这里，
 * 保证 lp_wallet_account 与 lp_wallet_ledger 强一致。
 */
final class WalletService
{
    /** 平台虚拟账户 user_id（抽成归集） */
    public const PLATFORM_USER_ID = 0;

    /**
     * 读取余额
     */
    public function balance(int $userId): float
    {
        $wallet = Db::connect('live_mysql')->table('lp_wallet_account')
            ->where('user_id', $userId)->find();
        return $wallet ? (float)$wallet['diamond_balance'] : 0.0;
    }

    /**
     * 扣减钻石（支出）
     * @param string $bizType recharge/gift/crowdfunding_pledge/vip_buy/withdraw/adjust
     * @param string $remark 流水备注
     */
    public function debit(int $userId, float $amount, string $bizType, int $bizId = 0, string $remark = ''): array
    {
        if ($amount <= 0) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '金额必须大于0');
        }

        $db = Db::connect('live_mysql');
        $db->startTrans();
        try {
            $wallet = $db->table('lp_wallet_account')
                ->where('user_id', $userId)->lock(true)->find();

            if (!$wallet) {
                $db->table('lp_wallet_account')->insert([
                    'user_id' => $userId, 'diamond_balance' => 0.00, 'status' => 1,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $balanceBefore = 0.00;
            } else {
                if ((int)$wallet['status'] !== 1) {
                    throw new BusinessException(ResultCode::WALLET_FROZEN);
                }
                $balanceBefore = (float)$wallet['diamond_balance'];
            }

            if (bccomp((string)$balanceBefore, (string)$amount, 2) < 0) {
                throw new BusinessException(ResultCode::BALANCE_NOT_ENOUGH, '钻石余额不足');
            }

            $balanceAfter = bcsub((string)$balanceBefore, (string)$amount, 2);
            $db->table('lp_wallet_account')
                ->where('user_id', $userId)->update([
                    'diamond_balance' => $balanceAfter, 'updated_at' => date('Y-m-d H:i:s'),
                ]);

            $db->table('lp_wallet_ledger')->insert([
                'user_id' => $userId, 'biz_type' => $bizType, 'direction' => 2,
                'asset_type' => 'diamond', 'amount' => $amount,
                'balance_before' => $balanceBefore, 'balance_after' => $balanceAfter,
                'biz_id' => $bizId, 'remark' => $remark !== '' ? $remark : '钻石支出',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $db->commit();
            return ['balance_before' => $balanceBefore, 'balance_after' => (float)$balanceAfter];
        } catch (BusinessException $e) {
            $db->rollback();
            throw $e;
        } catch (\Exception $e) {
            $db->rollback();
            throw new BusinessException(ResultCode::SERVER_ERROR, '扣减钻石失败: ' . $e->getMessage());
        }
    }

    /**
     * 增加钻石（收入）
     */
    public function credit(int $userId, float $amount, string $bizType, int $bizId = 0, string $remark = ''): array
    {
        if ($amount <= 0) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '金额必须大于0');
        }

        $db = Db::connect('live_mysql');
        $db->startTrans();
        try {
            $wallet = $db->table('lp_wallet_account')
                ->where('user_id', $userId)->lock(true)->find();

            if (!$wallet) {
                $db->table('lp_wallet_account')->insert([
                    'user_id' => $userId, 'diamond_balance' => 0.00, 'status' => 1,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $balanceBefore = 0.00;
            } else {
                $balanceBefore = (float)$wallet['diamond_balance'];
            }

            $balanceAfter = bcadd((string)$balanceBefore, (string)$amount, 2);
            $db->table('lp_wallet_account')
                ->where('user_id', $userId)->update([
                    'diamond_balance' => $balanceAfter, 'updated_at' => date('Y-m-d H:i:s'),
                ]);

            $db->table('lp_wallet_ledger')->insert([
                'user_id' => $userId, 'biz_type' => $bizType, 'direction' => 1,
                'asset_type' => 'diamond', 'amount' => $amount,
                'balance_before' => $balanceBefore, 'balance_after' => $balanceAfter,
                'biz_id' => $bizId, 'remark' => $remark !== '' ? $remark : '钻石收入',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $db->commit();
            return ['balance_before' => $balanceBefore, 'balance_after' => (float)$balanceAfter];
        } catch (\Exception $e) {
            $db->rollback();
            throw new BusinessException(ResultCode::SERVER_ERROR, '增加钻石失败: ' . $e->getMessage());
        }
    }

    /**
     * 读取平台配置（带默认值）
     */
    public static function config(string $key, string $default = ''): string
    {
        $row = Db::connect('live_mysql')->table('lp_platform_config')
            ->where('`key`', $key)->value('`value`');
        $v = trim((string)($row ?? ''));
        return $v !== '' ? $v : $default;
    }
}
