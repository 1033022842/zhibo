<?php
declare(strict_types=1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use app\common\service\SrsService;

/**
 * 共用 CDN 模式：把远端（172）SRS 的在播状态同步到本机状态快照
 *
 * 背景：新服务器不再本地推流，直播内容与 172 共用同一份 CDN 流。
 * 但前台 feedLive 依赖 lp_room_state_snapshot.current_state='public_live'，
 * 该表原本由本机 SRS 的 publish/unpublish 钩子维护；停推流后钩子不再触发。
 * 本命令定时把 SRS（配置为远端 172）的在播房间映射到本地快照，保证前台显示正确。
 *
 * 用法：php think presence:sync
 */
class PresenceSync extends Command
{
    protected function configure()
    {
        $this->setName('presence:sync')->setDescription('同步远端 SRS 在播状态到本地快照');
    }

    protected function execute(Input $input, Output $output): int
    {
        $app = 'room';
        $srs = new SrsService();
        $activeIds = $srs->getActiveRoomIds($app);

        // 远端探测失败（空数组）时不动状态，避免网络抖动把在播房间误置离线
        if (empty($activeIds)) {
            $output->writeln('[presence:sync] 远端 SRS 无在播房间或探测失败，跳过（不改状态）');
            return 0;
        }

        $db = Db::connect('live_mysql');
        $now = date('Y-m-d H:i:s');

        // 在播 → public_live
        foreach ($activeIds as $rid) {
            $rid = (int) $rid;
            $exists = $db->table('lp_room_state_snapshot')->where('room_id', $rid)->find();
            if ($exists) {
                if ($exists['current_state'] !== 'public_live') {
                    $db->table('lp_room_state_snapshot')->where('room_id', $rid)->update([
                        'current_state' => 'public_live',
                        'current_mode'  => 'public',
                        'version'       => Db::raw('version + 1'),
                        'updated_at'    => $now,
                    ]);
                }
            } else {
                $db->table('lp_room_state_snapshot')->insert([
                    'room_id'       => $rid,
                    'current_state' => 'public_live',
                    'current_mode'  => 'public',
                    'version'       => 1,
                    'updated_at'    => $now,
                ]);
            }
        }

        // 不在播但本地标记为在播的 → offline
        $db->table('lp_room_state_snapshot')
            ->where('current_state', 'public_live')
            ->whereNotIn('room_id', $activeIds)
            ->update([
                'current_state' => 'offline',
                'version'       => Db::raw('version + 1'),
                'updated_at'    => $now,
            ]);

        $output->writeln('[presence:sync] 在播房间: ' . json_encode($activeIds));
        return 0;
    }
}
