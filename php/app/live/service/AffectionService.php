<?php

declare(strict_types=1);

namespace app\live\service;

use think\facade\Db;
use app\common\exception\BusinessException;
use app\common\web\ResultCode;

final class AffectionService
{
    public const MAX_AFFECTION = 100;

    /**
     * 查询好感度（不存在则创建）
     */
    public function get(int $userId, int $contentId): array
    {
        $row = $this->getOrCreate($userId, $contentId);
        $affection = (int) $row['affection'];
        return [
            'affection' => $affection,
            'unlocked'  => $affection >= self::MAX_AFFECTION,
            'max'       => self::MAX_AFFECTION,
        ];
    }

    /**
     * 每天首次对话 +1（一天最多 +1）
     */
    public function addDailyChat(int $userId, int $contentId): int
    {
        $today = date('Y-m-d');
        $row = $this->getOrCreate($userId, $contentId);

        if ($row['last_chat_date'] === $today) {
            return (int) $row['affection'];
        }

        $affection = min(self::MAX_AFFECTION, (int) $row['affection'] + 1);
        Db::connect('live_mysql')->table('lp_ai_affection')
            ->where('id', $row['id'])
            ->update([
                'affection'      => $affection,
                'last_chat_date' => $today,
                'updated_at'     => date('Y-m-d H:i:s'),
            ]);

        return $affection;
    }

    /**
     * 钻石购买好感度（1:1）
     */
    public function buy(int $userId, int $contentId, int $amount): array
    {
        if ($amount <= 0) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '购买数量必须大于0');
        }

        $row = $this->getOrCreate($userId, $contentId);
        if ((int) $row['affection'] >= self::MAX_AFFECTION) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '好感度已满');
        }

        // 1:1 兑换，最多买到 100，避免超出部分仍被扣钻石
        $remaining = self::MAX_AFFECTION - (int) $row['affection'];
        $amount = min($amount, $remaining);

        // 1:1 扣钻石
        $wallet = new WalletService();
        $debit  = $wallet->debit($userId, (float) $amount, 'affection_buy', $contentId, '购买角色好感度');

        $newAffection = (int) $row['affection'] + $amount;
        Db::connect('live_mysql')->table('lp_ai_affection')
            ->where('id', $row['id'])
            ->update([
                'affection'  => $newAffection,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        return [
            'affection' => $newAffection,
            'balance'   => $debit['balance_after'],
        ];
    }

    private function getOrCreate(int $userId, int $contentId): array
    {
        $db = Db::connect('live_mysql');
        $row = $db->table('lp_ai_affection')
            ->where('user_id', $userId)
            ->where('content_id', $contentId)
            ->find();

        if (!$row) {
            $now = date('Y-m-d H:i:s');
            $id = $db->table('lp_ai_affection')->insertGetId([
                'user_id'     => $userId,
                'content_id'  => $contentId,
                'affection'   => 0,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
            return [
                'id'             => $id,
                'affection'      => 0,
                'last_chat_date' => null,
            ];
        }

        return $row;
    }
}
