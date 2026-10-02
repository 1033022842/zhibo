<?php
declare(strict_types=1);

namespace app\live\service;

use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use app\live\model\CreatorSubmission;
use think\facade\Db;

final class CreatorService
{
    /**
     * 提交独立完成的角色（待后台审核）
     */
    public function submit(int $userId, array $data): CreatorSubmission
    {
        Db::startTrans();
        try {
            $item = new CreatorSubmission();
            $item->user_id       = $userId;
            $item->persona_name  = $data['persona_name'];
            $item->description   = $data['description'] ?? '';
            $item->tags          = $data['tags'] ?? '';
            $item->style         = $data['style'] ?? '';
            $item->gender        = $data['gender'] ?? '';
            $item->age_range     = $data['age_range'] ?? '';
            $item->language      = $data['language'] ?? '';
            $item->personality   = $data['personality'] ?? '';
            $item->voice_style   = $data['voice_style'] ?? '';
            $item->publish_type  = $data['publish_type'] ?? 'chat';
            $item->is_adult      = (int) ($data['is_adult'] ?? 0);
            $item->highlights    = $data['highlights'] ?? '';
            $item->reference_url = $data['reference_url'] ?? '';
            $item->cover_url     = $data['cover_url'] ?? '';
            $item->materials     = json_encode($data['materials'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $item->status        = CreatorSubmission::STATUS_PENDING;
            $item->created_at    = date('Y-m-d H:i:s');
            $item->updated_at    = date('Y-m-d H:i:s');
            $item->save();

            Db::commit();
            return $item;
        } catch (BusinessException $e) {
            Db::rollback();
            throw $e;
        } catch (\Exception $e) {
            Db::rollback();
            throw new BusinessException(ResultCode::SERVER_ERROR, '提交失败: ' . $e->getMessage());
        }
    }

    /**
     * 我的创作列表
     */
    public function myCreations(int $userId, int $limit = 100): array
    {
        $rows = CreatorSubmission::where('user_id', $userId)
            ->order('id', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();

        return array_map(static function (array $row): array {
            return [
                'id'            => (int) $row['id'],
                'persona_name'  => (string) $row['persona_name'],
                'description'   => (string) $row['description'],
                'publish_type'  => (string) $row['publish_type'],
                'status'        => (int) $row['status'],
                'is_adult'      => (int) $row['is_adult'],
                'tags'          => self::splitList((string) $row['tags']),
                'cover_url'     => (string) $row['cover_url'],
                'reject_reason' => (string) $row['reject_reason'],
                'created_at'    => (string) $row['created_at'],
            ];
        }, $rows);
    }

    /**
     * 我的创作详情（编辑回填）
     */
    public function detail(int $userId, int $id): array
    {
        $item = $this->findOwn($userId, $id);
        $row  = $item->toArray();

        $materials = json_decode((string) $item->materials, true);

        return [
            'id'            => (int) $row['id'],
            'persona_name'  => (string) $row['persona_name'],
            'description'   => (string) $row['description'],
            'tags'          => self::splitList((string) $row['tags']),
            'style'         => (string) $row['style'],
            'gender'        => (string) $row['gender'],
            'age_range'     => (string) $row['age_range'],
            'language'      => (string) $row['language'],
            'personality'   => self::splitList((string) $row['personality']),
            'voice_style'   => (string) $row['voice_style'],
            'publish_type'  => (string) $row['publish_type'],
            'is_adult'      => (int) $row['is_adult'],
            'highlights'    => (string) $row['highlights'],
            'reference_url' => (string) $row['reference_url'],
            'cover_url'     => (string) $row['cover_url'],
            'materials'     => is_array($materials) ? $materials : [],
            'status'        => (int) $row['status'],
            'reject_reason' => (string) $row['reject_reason'],
            'created_at'    => (string) $row['created_at'],
        ];
    }

    /**
     * 更新自己的创作（仅待审核 / 已驳回可改，改后重新进入待审核）
     */
    public function update(int $userId, int $id, array $data): CreatorSubmission
    {
        $item = $this->findOwn($userId, $id);
        if ((int) $item->status === CreatorSubmission::STATUS_ONLINE) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '已上线的作品暂不支持修改');
        }

        $item->persona_name  = $data['persona_name'];
        $item->description   = $data['description'] ?? '';
        $item->tags          = $data['tags'] ?? '';
        $item->style         = $data['style'] ?? '';
        $item->gender        = $data['gender'] ?? '';
        $item->age_range     = $data['age_range'] ?? '';
        $item->language      = $data['language'] ?? '';
        $item->personality   = $data['personality'] ?? '';
        $item->voice_style   = $data['voice_style'] ?? '';
        $item->publish_type  = $data['publish_type'] ?? 'chat';
        $item->is_adult      = (int) ($data['is_adult'] ?? 0);
        $item->highlights    = $data['highlights'] ?? '';
        $item->reference_url = $data['reference_url'] ?? '';
        $item->cover_url     = $data['cover_url'] ?? '';
        $item->materials     = json_encode($data['materials'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        // 修改后重新进入待审核
        $item->status        = CreatorSubmission::STATUS_PENDING;
        $item->reject_reason = '';
        $item->updated_at    = date('Y-m-d H:i:s');
        $item->save();

        return $item;
    }

    /**
     * 删除自己的创作
     */
    public function remove(int $userId, int $id): void
    {
        $item = $this->findOwn($userId, $id);
        $item->delete();
    }

    /**
     * 按 ID + 归属人查询自己的创作
     */
    private function findOwn(int $userId, int $id): CreatorSubmission
    {
        $item = CreatorSubmission::where('id', $id)->where('user_id', $userId)->find();
        if (!$item) {
            throw new BusinessException(ResultCode::RECORD_NOT_FOUND, '作品不存在');
        }
        return $item;
    }

    /**
     * 收入排行榜：按平台用户累计到手收益降序（公开数据，用于创作中心展示）
     * 数据源为钱包流水 gift_income（礼物分成后角色归属人实际入账的钻石）
     */
    public function leaderboard(int $limit = 10): array
    {
        $limit = max(1, min($limit, 50));

        $rows = Db::connect('live_mysql')
            ->table('lp_wallet_ledger l')
            ->join('lp_user u', 'u.id = l.user_id')
            ->where('l.biz_type', 'gift_income')
            ->where('l.direction', 1)
            ->where('l.user_id', '>', 0)
            ->field([
                'l.user_id',
                'u.user_no',
                'u.nickname',
                'u.avatar',
                'SUM(l.amount) AS income',
            ])
            ->group('l.user_id')
            ->order('income', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();

        $list = [];
        $rank = 1;
        foreach ($rows as $row) {
            $list[] = [
                'rank'     => $rank++,
                'user_id'  => (int) $row['user_id'],
                'user_no'  => (string) $row['user_no'],
                'nickname' => (string) $row['nickname'],
                'avatar'   => (string) ($row['avatar'] ?? ''),
                'income'   => (float) $row['income'],
            ];
        }
        return $list;
    }

    /**
     * 逗号分隔字符串 -> 数组
     */
    private static function splitList(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }
        return array_values(array_filter(array_map('trim', explode(',', $value)), static fn($s) => $s !== ''));
    }
}
