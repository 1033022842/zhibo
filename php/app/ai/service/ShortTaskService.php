<?php
declare(strict_types=1);

namespace app\ai\service;

use app\ai\model\AiTask;
use app\common\enums\TaskStatus;
use app\live\service\GenCreditService;
use app\live\service\VipService;
use app\live\service\WalletService;
use think\facade\Db;
use think\facade\Log;

/**
 * 50 条短视频通道（aiShort）的任务回收
 *
 * 为什么单独一个类：AiTaskService::reapStuckCustomTasks() 只扫
 * custom_video / custom_image，不碰新通道。在共享类里加分支会牵连
 * 现有四站的定制生成逻辑，所以这里独立实现，由 AiTaskCron 追加调用。
 */
final class ShortTaskService
{
    public const TASK_VIDEO = 'ai_short_video';
    public const TASK_IMAGE = 'ai_short_image';

    /**
     * 回收卡死任务：先重试一次，再失败退款
     *
     * @return array<int,array{task_id:int,action:string,refunded?:bool}>
     */
    public function reapStuck(int $ttlMin = 90): array
    {
        $db = Db::connect('live_mysql');
        $cutoff = date('Y-m-d H:i:s', time() - max(10, $ttlMin) * 60);

        $rows = $db->table('lp_ai_task')
            ->whereIn('task_type', [self::TASK_VIDEO, self::TASK_IMAGE])
            ->whereIn('status', [TaskStatus::ACCEPTED->value, TaskStatus::PROCESSING->value])
            ->where(function ($q) use ($cutoff) {
                $q->where('accepted_at', '<=', $cutoff)
                    ->whereOr(function ($q2) use ($cutoff) {
                        $q2->whereNull('accepted_at')->where('deadline_at', '<=', $cutoff);
                    });
            })
            ->select()
            ->toArray();

        $out = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $content = json_decode((string) ($row['content'] ?? ''), true) ?: [];
            $paid = (float) ($content['paid_tokens'] ?? 0);
            $retry = (int) ($content['retry'] ?? 0);
            $now = date('Y-m-d H:i:s');

            if ($retry < 1) {
                // 第一次卡死：退单重排，让 worker 再跑一次
                $content['retry'] = $retry + 1;
                $db->table('lp_ai_task')->where('id', $id)->update([
                    'status'      => TaskStatus::PENDING->value,
                    'worker_id'   => '',
                    'accepted_at' => null,
                    'content'     => json_encode($content, JSON_UNESCAPED_UNICODE),
                    'deadline_at' => date('Y-m-d H:i:s', time() + max(10, $ttlMin) * 60),
                    'updated_at'  => $now,
                ]);
                $out[] = ['task_id' => $id, 'action' => 'retry'];
                continue;
            }

            // 第二次仍卡死：置失败并退款
            $db->table('lp_ai_task')->where('id', $id)->update([
                'status'     => TaskStatus::FAILED->value,
                'failed_at'  => $now,
                'updated_at' => $now,
            ]);
            $refunded = $this->refund((int) ($row['source_ref_id'] ?? 0), $paid);
            $out[] = ['task_id' => $id, 'action' => 'failed', 'refunded' => $refunded];
            Log::warning("[aiShort] reaped stuck task {$id} (ttl={$ttlMin}m), refunded=" . ($refunded ? 'yes' : 'no'));
        }
        return $out;
    }

    private function refund(int $userId, float $paidTokens): bool
    {
        if ($userId <= 0) {
            return false;
        }
        try {
            if ($paidTokens > 0.0) {
                (new WalletService())->credit($userId, $paidTokens, 'video_gen_refund', 0, 'AI short timeout refund');
                return true;
            }
            $ent = (new VipService())->entitlements($userId);
            (new GenCreditService())->refund($userId, max(1, (int) ($ent['image_daily_limit'] ?? 1)));
            return true;
        } catch (\Throwable $e) {
            Log::error("[aiShort] reap refund failed user={$userId}: {$e->getMessage()}");
            return false;
        }
    }
}
