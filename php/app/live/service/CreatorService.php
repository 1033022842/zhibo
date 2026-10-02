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
     * 收入排行榜：全部角色按累计礼物收入降序（公开数据，用于创作中心展示）
     */
    public function leaderboard(int $limit = 10): array
    {
        $limit = max(1, min($limit, 50));

        $subQuery = Db::connect('live_mysql')
            ->table('lp_gift_order go')
            ->field([
                'go.room_id',
                'SUM(go.total_price) AS total_gift',
                'COUNT(DISTINCT go.user_id) AS donor_count',
            ])
            ->group('go.room_id')
            ->buildSql();

        $rows = Db::connect('live_mysql')
            ->table('lp_room r')
            ->join('lp_persona p', 'r.persona_id = p.id')
            ->join($subQuery . ' s', 's.room_id = r.id', 'LEFT')
            ->field([
                'p.id AS persona_id',
                'p.name AS persona_name',
                'p.cover_url',
                'r.id AS room_id',
                'r.room_no',
                'IFNULL(s.total_gift, 0) AS total_gift',
                'IFNULL(s.donor_count, 0) AS donor_count',
            ])
            ->order('total_gift', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();

        $list = [];
        $rank = 1;
        foreach ($rows as $row) {
            $list[] = [
                'rank'         => $rank++,
                'persona_id'   => (int) $row['persona_id'],
                'persona_name' => (string) $row['persona_name'],
                'cover_url'    => (string) $row['cover_url'],
                'room_id'      => (int) $row['room_id'],
                'room_no'      => (string) $row['room_no'],
                'total_gift'   => (float) $row['total_gift'],
                'donor_count'  => (int) $row['donor_count'],
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
