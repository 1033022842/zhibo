<?php
declare(strict_types=1);

namespace app\live\service;

use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use app\live\model\CrowdfundingProject;
use app\live\model\CrowdfundingPledge;
use think\facade\Db;
use think\facade\Log;

final class CrowdfundingService
{
    /**
     * 发起众筹项目
     */
    public function initiate(int $userId, array $data): CrowdfundingProject
    {
        Db::startTrans();
        try {
            $project = new CrowdfundingProject();
            $project->user_id      = $userId;
            $project->title        = $data['title'];
            $project->persona_name = $data['persona_name'];
            $project->description  = $data['description'] ?? '';
            $project->tags         = $data['tags'] ?? '';
            $project->style        = $data['style'] ?? '';
            $project->gender       = $data['gender'] ?? '';
            $project->age_range    = $data['age_range'] ?? '';
            $project->language     = $data['language'] ?? '';
            $project->personality  = $data['personality'] ?? '';
            $project->voice_style  = $data['voice_style'] ?? '';
            $project->deliverables = $data['deliverables'] ?? '';
            $project->is_adult     = (int) ($data['is_adult'] ?? 0);
            $project->highlights   = $data['highlights'] ?? '';
            $project->reference_url = $data['reference_url'] ?? '';
            $project->cover_url    = $data['cover_url'] ?? '';
            $project->target_amount = $data['target_amount'];
            $project->deadline     = $data['deadline'];
            $project->status       = CrowdfundingProject::STATUS_ACTIVE;
            $project->created_at   = date('Y-m-d H:i:s');
            $project->updated_at   = date('Y-m-d H:i:s');
            $project->save();

            Db::commit();
            return $project;
        } catch (BusinessException $e) {
            Db::rollback();
            throw $e;
        } catch (\Exception $e) {
            Db::rollback();
            throw new BusinessException(ResultCode::SERVER_ERROR, '发起众筹失败: ' . $e->getMessage());
        }
    }

    /**
     * 支持众筹项目（冻结钻石）
     */
    public function pledge(int $userId, int $projectId, float $amount): void
    {
        if ($amount <= 0) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '支持金额必须大于0');
        }

        $project = CrowdfundingProject::find($projectId);
        if (!$project) {
            throw new BusinessException(ResultCode::CROWDFUNDING_NOT_FOUND);
        }
        if ($project->status !== CrowdfundingProject::STATUS_ACTIVE) {
            throw new BusinessException(ResultCode::CROWDFUNDING_NOT_ACTIVE);
        }
        if (strtotime($project->deadline) < time()) {
            throw new BusinessException(ResultCode::CROWDFUNDING_EXPIRED);
        }
        if ((int)$project->user_id === $userId) {
            throw new BusinessException(ResultCode::CROWDFUNDING_CANNOT_PLEDGE_SELF);
        }

        // 检查是否已经支持过（去重，允许重复支持）
        // 不做去重，每次支持独立记录

        Db::startTrans();
        try {
            // 1. 扣减支持者钻石
            $this->debitDiamond($userId, $amount, 'crowdfunding_pledge', $projectId);

            // 2. 创建支持记录（冻结状态）
            $pledge = new CrowdfundingPledge();
            $pledge->project_id = $projectId;
            $pledge->user_id    = $userId;
            $pledge->amount     = $amount;
            $pledge->status     = CrowdfundingPledge::STATUS_FROZEN;
            $pledge->created_at = date('Y-m-d H:i:s');
            $pledge->updated_at = date('Y-m-d H:i:s');
            $pledge->save();

            // 3. 更新项目已筹金额和支持人数
            $project->raised_amount = bcadd((string)$project->raised_amount, (string)$amount, 2);
            $project->supporter_count = $project->supporter_count + 1;
            $project->updated_at = date('Y-m-d H:i:s');
            $project->save();

            Db::commit();
        } catch (BusinessException $e) {
            Db::rollback();
            throw $e;
        } catch (\Exception $e) {
            Db::rollback();
            throw new BusinessException(ResultCode::SERVER_ERROR, '支持失败: ' . $e->getMessage());
        }
    }

    /**
     * 到期判定：扫描所有到期项目，自动结算
     * @return array{success: int, failed: int}
     */
    public function settleExpiredProjects(): array
    {
        // 查找截止时间已过但仍进行中的项目
        $projects = CrowdfundingProject::where('status', CrowdfundingProject::STATUS_ACTIVE)
            ->where('deadline', '<=', date('Y-m-d H:i:s'))
            ->select();

        $successCount = 0;
        $failCount = 0;

        foreach ($projects as $project) {
            try {
                $reached = bccomp((string)$project->raised_amount, (string)$project->target_amount, 2) >= 0;

                if ($reached) {
                    $this->settleSuccess($project);
                    $successCount++;
                } else {
                    $this->settleFailed($project);
                    $failCount++;
                }
            } catch (\Exception $e) {
                Log::error("Crowdfunding settle error: project_id={$project->id}, " . $e->getMessage());
            }
        }

        return ['success' => $successCount, 'failed' => $failCount];
    }

    /**
     * 众筹成功：解冻资金划转给商家
     */
    private function settleSuccess(CrowdfundingProject $project): void
    {
        // 平台抽成比例（后台可配，默认 5%）
        $rate = 0.05;
        try {
            $cfg = WalletService::config('crowdfunding_commission_rate', '0.05');
            if (is_numeric($cfg)) {
                $rate = max(0.0, min(0.5, (float)$cfg));
            }
        } catch (\Throwable) {}

        Db::startTrans();
        try {
            $pledges = CrowdfundingPledge::where('project_id', $project->id)
                ->where('status', CrowdfundingPledge::STATUS_FROZEN)
                ->select();

            foreach ($pledges as $pledge) {
                // 分账：商家得 (1-抽成)，平台虚拟账户得抽成
                $amount = (float)$pledge->amount;
                $platformShare = round($amount * $rate, 2);
                $ownerShare = round($amount - $platformShare, 2);
                if ($ownerShare > 0) {
                    $this->creditDiamond((int)$project->user_id, $ownerShare, 'crowdfunding_unlock', (int)$pledge->id);
                }
                if ($platformShare > 0) {
                    $this->creditDiamond(0, $platformShare, 'crowdfunding_commission', (int)$pledge->id);
                }

                $pledge->status = CrowdfundingPledge::STATUS_UNLOCKED;
                $pledge->updated_at = date('Y-m-d H:i:s');
                $pledge->save();
            }

            $project->status = CrowdfundingProject::STATUS_SUCCESS;
            $project->updated_at = date('Y-m-d H:i:s');
            $project->save();

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            Log::error("Crowdfunding settleSuccess error: project_id={$project->id}, " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 众筹失败：退款给所有支持者
     */
    private function settleFailed(CrowdfundingProject $project): void
    {
        Db::startTrans();
        try {
            $pledges = CrowdfundingPledge::where('project_id', $project->id)
                ->where('status', CrowdfundingPledge::STATUS_FROZEN)
                ->select();

            $now = date('Y-m-d H:i:s');
            foreach ($pledges as $pledge) {
                // 原路退回给支持者
                $this->creditDiamond((int)$pledge->user_id, (float)$pledge->amount, 'crowdfunding_refund', (int)$pledge->id);

                $pledge->status = CrowdfundingPledge::STATUS_REFUNDED;
                $pledge->refunded_at = $now;
                $pledge->updated_at = $now;
                $pledge->save();
            }

            $project->status = CrowdfundingProject::STATUS_FAILED;
            $project->updated_at = $now;
            $project->save();

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            Log::error("Crowdfunding settleFailed error: project_id={$project->id}, " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 关联角色（众筹成功后商家手动创建角色并关联）
     */
    public function linkPersona(int $userId, int $projectId, int $personaId): void
    {
        $project = CrowdfundingProject::find($projectId);
        if (!$project) {
            throw new BusinessException(ResultCode::CROWDFUNDING_NOT_FOUND);
        }
        if ((int)$project->user_id !== $userId) {
            throw new BusinessException(ResultCode::CROWDFUNDING_NOT_OWNER);
        }
        if ($project->status !== CrowdfundingProject::STATUS_SUCCESS) {
            throw new BusinessException(ResultCode::CROWDFUNDING_NOT_SUCCESS);
        }
        if ($project->persona_id) {
            throw new BusinessException(ResultCode::CROWDFUNDING_ALREADY_LINKED);
        }

        $project->persona_id = $personaId;
        $project->updated_at = date('Y-m-d H:i:s');
        $project->save();
    }

    /**
     * 后台删除众筹项目：冻结支持先退款，再删除支持记录与项目
     */
    public function deleteProject(int $projectId): void
    {
        $project = CrowdfundingProject::find($projectId);
        if (!$project) {
            throw new BusinessException(ResultCode::CROWDFUNDING_NOT_FOUND);
        }

        Db::startTrans();
        try {
            // 冻结中的支持原路退款给支持者
            $frozen = CrowdfundingPledge::where('project_id', $projectId)
                ->where('status', CrowdfundingPledge::STATUS_FROZEN)
                ->select();
            foreach ($frozen as $pledge) {
                $this->creditDiamond((int)$pledge->user_id, (float)$pledge->amount, 'crowdfunding_refund', (int)$pledge->id);
            }

            // 删除全部支持记录与项目
            CrowdfundingPledge::where('project_id', $projectId)->delete();
            $project->delete();

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            throw new BusinessException(ResultCode::SERVER_ERROR, '删除失败: ' . $e->getMessage());
        }
    }

    /**
     * 用户发起的众筹列表
     */
    public function myProjects(int $userId): array
    {
        $list = CrowdfundingProject::where('user_id', $userId)
            ->order('id', 'desc')
            ->select()
            ->toArray();

        return array_map([$this, 'formatProject'], $list);
    }

    /**
     * 用户支持的众筹列表
     */
    public function myPledges(int $userId): array
    {
        $pledges = CrowdfundingPledge::where('user_id', $userId)
            ->order('id', 'desc')
            ->select()
            ->toArray();

        if (empty($pledges)) {
            return [];
        }

        $projectIds = array_unique(array_column($pledges, 'project_id'));
        $projects = CrowdfundingProject::whereIn('id', $projectIds)
            ->column('title,cover_url,status,deadline,target_amount,raised_amount,supporter_count', 'id');

        return array_map(function ($p) use ($projects) {
            $proj = $projects[$p['project_id']] ?? [];
            return [
                'id'          => (int)$p['id'],
                'project_id'  => (int)$p['project_id'],
                'amount'      => (float)$p['amount'],
                'status'      => (int)$p['status'],
                'created_at'  => $p['created_at'],
                'project'     => $proj ? $this->formatProjectSimple($proj, (int)$p['project_id']) : null,
            ];
        }, $pledges);
    }

    /**
     * 众筹项目列表（所有进行中的）
     */
    public function listActive(int $page = 1, int $pageSize = 15): array
    {
        $query = CrowdfundingProject::where('status', CrowdfundingProject::STATUS_ACTIVE)
            ->where('deadline', '>', date('Y-m-d H:i:s'))
            ->order('id', 'desc');

        $total = $query->count();
        $list  = $query->page($page, $pageSize)->select()->toArray();

        return [
            'list'  => array_map([$this, 'formatProject'], $list),
            'total' => $total,
        ];
    }

    /**
     * 众筹项目列表（全部状态：进行中在前，其次已成功、已失败，无需登录）
     */
    public function listAll(int $page = 1, int $pageSize = 15): array
    {
        $query = CrowdfundingProject::order('status', 'asc')
            ->order('id', 'desc');

        $total = $query->count();
        $list  = $query->page($page, $pageSize)->select()->toArray();

        return [
            'list'  => array_map([$this, 'formatProject'], $list),
            'total' => $total,
        ];
    }

    /**
     * 众筹项目详情
     */
    public function detail(int $projectId): ?array
    {
        $project = CrowdfundingProject::find($projectId);
        if (!$project) {
            return null;
        }

        $result = $this->formatProject($project->toArray());

        // 附加进度百分比
        $target = (float)$project->target_amount;
        $raised = (float)$project->raised_amount;
        $result['progress_percent'] = $target > 0
            ? min(100, round(($raised / $target) * 100, 1))
            : 0;

        return $result;
    }

    /**
     * 项目是否存在进行中
     */
    public function hasActiveProject(int $userId): bool
    {
        return CrowdfundingProject::where('user_id', $userId)
            ->where('status', CrowdfundingProject::STATUS_ACTIVE)
            ->find() !== null;
    }

    // ==================== 私有方法 ====================

    private function formatProject(array $p): array
    {
        return [
            'id'              => (int)$p['id'],
            'user_id'         => (int)$p['user_id'],
            'title'           => $p['title'],
            'persona_name'    => $p['persona_name'],
            'description'     => $p['description'],
            'tags'            => $this->splitList((string)($p['tags'] ?? '')),
            'style'           => (string)($p['style'] ?? ''),
            'gender'          => (string)($p['gender'] ?? ''),
            'age_range'       => (string)($p['age_range'] ?? ''),
            'language'        => (string)($p['language'] ?? ''),
            'personality'     => $this->splitList((string)($p['personality'] ?? '')),
            'voice_style'     => (string)($p['voice_style'] ?? ''),
            'deliverables'    => $this->splitList((string)($p['deliverables'] ?? '')),
            'is_adult'        => (int)($p['is_adult'] ?? 0),
            'highlights'      => (string)($p['highlights'] ?? ''),
            'reference_url'   => (string)($p['reference_url'] ?? ''),
            'cover_url'       => $p['cover_url'],
            'target_amount'   => (float)$p['target_amount'],
            'raised_amount'   => (float)$p['raised_amount'],
            'supporter_count' => (int)$p['supporter_count'],
            'deadline'        => $p['deadline'],
            'status'          => (int)$p['status'],
            'persona_id'      => $p['persona_id'] ? (int)$p['persona_id'] : null,
            'created_at'      => $p['created_at'],
        ];
    }

    /**
     * 逗号分隔字符串 -> 数组
     */
    private function splitList(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }
        return array_values(array_filter(array_map('trim', explode(',', $value)), static fn($s) => $s !== ''));
    }

    private function formatProjectSimple(array $p, int $id): array
    {
        return [
            'id'              => $id,
            'title'           => $p['title'] ?? '',
            'cover_url'       => $p['cover_url'] ?? '',
            'status'          => (int)($p['status'] ?? 0),
            'deadline'        => $p['deadline'] ?? '',
            'target_amount'   => (float)($p['target_amount'] ?? 0),
            'raised_amount'   => (float)($p['raised_amount'] ?? 0),
            'supporter_count' => (int)($p['supporter_count'] ?? 0),
        ];
    }

    /**
     * 扣减用户钻石（支出）
     */
    private function debitDiamond(int $userId, float $amount, string $bizType, int $bizId): void
    {
        $wallet = Db::connect('live_mysql')
            ->table('lp_wallet_account')
            ->where('user_id', $userId)
            ->lock(true)
            ->find();

        if (!$wallet) {
            // 自动创建钱包（余额为0，后续余额检查会报错）
            Db::connect('live_mysql')->table('lp_wallet_account')->insert([
                'user_id'         => $userId,
                'diamond_balance' => 0.00,
                'status'          => 1,
                'updated_at'      => date('Y-m-d H:i:s'),
            ]);
            $balanceBefore = 0.00;
        } else {
            if ((int)$wallet['status'] !== 1) {
                throw new BusinessException(ResultCode::WALLET_FROZEN);
            }
            $balanceBefore = (float)$wallet['diamond_balance'];
        }

        if (bccomp((string)$balanceBefore, (string)$amount, 2) < 0) {
            throw new BusinessException(ResultCode::BALANCE_NOT_ENOUGH, '钻石余额不足，请先充值');
        }

        $balanceAfter = bcsub((string)$balanceBefore, (string)$amount, 2);

        // 更新余额
        Db::connect('live_mysql')->table('lp_wallet_account')
            ->where('user_id', $userId)
            ->update([
                'diamond_balance' => $balanceAfter,
                'updated_at'      => date('Y-m-d H:i:s'),
            ]);

        // 记录流水
        Db::connect('live_mysql')->table('lp_wallet_ledger')->insert([
            'user_id'        => $userId,
            'biz_type'       => $bizType,
            'direction'      => 2, // 支出
            'asset_type'     => 'diamond',
            'amount'         => $amount,
            'balance_before' => $balanceBefore,
            'balance_after'  => $balanceAfter,
            'biz_id'         => $bizId,
            'remark'         => '众筹支持冻结',
            'created_at'     => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 增加用户钻石（收入）
     */
    private function creditDiamond(int $userId, float $amount, string $bizType, int $bizId): void
    {
        $wallet = Db::connect('live_mysql')
            ->table('lp_wallet_account')
            ->where('user_id', $userId)
            ->lock(true)
            ->find();

        if (!$wallet) {
            // 如果商家没有钱包，创建
            Db::connect('live_mysql')->table('lp_wallet_account')->insert([
                'user_id'         => $userId,
                'diamond_balance' => 0.00,
                'status'          => 1,
                'updated_at'      => date('Y-m-d H:i:s'),
            ]);
            $balanceBefore = 0.00;
        } else {
            $balanceBefore = (float)$wallet['diamond_balance'];
        }

        $balanceAfter = bcadd((string)$balanceBefore, (string)$amount, 2);

        Db::connect('live_mysql')->table('lp_wallet_account')
            ->where('user_id', $userId)
            ->update([
                'diamond_balance' => $balanceAfter,
                'updated_at'      => date('Y-m-d H:i:s'),
            ]);

        Db::connect('live_mysql')->table('lp_wallet_ledger')->insert([
            'user_id'        => $userId,
            'biz_type'       => $bizType,
            'direction'      => 1, // 收入
            'asset_type'     => 'diamond',
            'amount'         => $amount,
            'balance_before' => $balanceBefore,
            'balance_after'  => $balanceAfter,
            'biz_id'         => $bizId,
            'remark'         => $bizType === 'crowdfunding_unlock' ? '众筹成功资金划转' : '众筹失败退款',
            'created_at'     => date('Y-m-d H:i:s'),
        ]);
    }
}
