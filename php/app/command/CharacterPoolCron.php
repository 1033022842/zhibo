<?php
declare(strict_types=1);

namespace app\command;

use app\live\service\CharacterImageService;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;

/**
 * 角色形象图预生成池：补池 + 收尾（建议每分钟，挂在 /etc/cron.d/douyin）
 *
 * finalizer：pending 池行的任务已 failed → 删行释放 seq；任务已有成品但回调没到 → 直接置 ready
 * filler：   需求来源 = 近 30 天 lp_persona.source_fields（每条用户选择都是需求信号），
 *            按 hash 聚合后库存（ready+served+pending）< POOL_DEPTH 的补到 3 张；
 *            全局在制（pending pool_image 任务）≤ POOL_MAX_INFLIGHT，priority=0 不抢占用户单
 */
final class CharacterPoolCron extends Command
{
    protected function configure()
    {
        $this->setName('ai:pool:cron')
            ->setDescription('角色形象图预生成池：补池 + 收尾');
    }

    protected function execute(Input $input, Output $output)
    {
        $svc = new CharacterImageService();
        $db = Db::connect('live_mysql');
        $now = date('Y-m-d H:i:s');

        // ① finalizer：回调没到/失败 的 pending 池行收尾
        $rows = $db->table('lp_character_image_pool')
            ->where('status', 'pending')
            ->select()->toArray();
        $readyed = 0;
        $dropped = 0;
        foreach ($rows as $row) {
            $task = Db::connect('live_mysql')->table('lp_ai_task')
                ->where('id', (int) $row['task_id'])
                ->field('status, video_url')
                ->find();
            if (!$task) {
                $db->table('lp_character_image_pool')->where('id', (int) $row['id'])->delete();
                $dropped++;
                continue;
            }
            // 成品已落库但 complete 回调没到：直接置 ready
            if ((string) ($task['video_url'] ?? '') !== '') {
                $db->table('lp_character_image_pool')->where('id', (int) $row['id'])->update([
                    'status'     => 'ready',
                    'image_url'  => (string) $task['video_url'],
                    'updated_at' => $now,
                ]);
                $readyed++;
                continue;
            }
            if ((string) $task['status'] === 'failed') {
                $db->table('lp_character_image_pool')->where('id', (int) $row['id'])->delete();
                $dropped++;
            }
        }
        $output->writeln("finalizer: ready={$readyed} dropped={$dropped}");

        // ② filler：在制上限检查（pending 池行数 ≈ 在制池任务数）
        $inflight = (int) $db->table('lp_character_image_pool')->where('status', 'pending')->count();
        if ($inflight >= CharacterImageService::POOL_MAX_INFLIGHT) {
            $output->writeln("filler: inflight={$inflight} ≥ " . CharacterImageService::POOL_MAX_INFLIGHT . '，本轮不补');
            return;
        }

        // ③ 需求聚合：近 30 天用户选过的词条组合（每条 lp_persona.source_fields 都是一次需求）
        $since = date('Y-m-d H:i:s', time() - 30 * 86400);
        $rows = $db->table('lp_persona')
            ->where('created_at', '>=', $since)
            ->where('source_fields', '<>', '')
            ->order('id', 'desc')
            ->limit(2000)
            ->field('source_fields')
            ->select()->toArray();

        $demand = [];   // hash => ['category','fields','count']
        $skipped = 0;   // 不适用池的请求（Male / 自由外貌描述 / 新族裔 / butt_size）不进需求聚合
        foreach ($rows as $r) {
            $fields = json_decode((string) $r['source_fields'], true);
            if (!is_array($fields)) {
                continue;
            }
            // 池是旧口径共用库存：Male / 自由外貌描述 / 新族裔 / butt_size / age_range / chest
            // 这类请求领图时就已跳过池（poolEligible），补池也不能按它们生成库存；
            // 旧前置条件（type 与 race 必填）在 poolDemand 内保留。
            $slot = $svc->poolDemand($fields);
            if ($slot === null) {
                $skipped++;
                continue;
            }
            $hash = $slot['hash'];
            if (!isset($demand[$hash])) {
                $demand[$hash] = ['category' => $slot['category'], 'fields' => $fields, 'count' => 0];
            }
            $demand[$hash]['count']++;
        }
        if (!$demand) {
            $output->writeln('filler: 近 30 天无需求');
            return;
        }
        uasort($demand, fn(array $a, array $b) => $b['count'] <=> $a['count']);

        // ④ 按需求热度补池（每组合补到 POOL_DEPTH，seq 取未占用最小值）
        $enqueued = 0;
        foreach ($demand as $hash => $d) {
            if ($inflight >= CharacterImageService::POOL_MAX_INFLIGHT) {
                $output->writeln("filler: inflight={$inflight} 到顶，剩余组合下轮再补");
                break;
            }
            $exist = $db->table('lp_character_image_pool')
                ->where('fields_hash', $hash)
                ->column('seq');
            $stock = count($exist);   // ready+served+pending+failed 都占位（failed 会被 finalizer 删除释放）
            if ($stock >= CharacterImageService::POOL_DEPTH) {
                continue;
            }
            // 出图词条：age 替换为分箱代表年龄（与池 key 口径一致，恢复改动前的 ageBin(age) 口径）
            $bin = $svc->ageBin($d['fields']['age'] ?? 0);
            $binFields = $d['fields'];
            $binFields['age'] = $bin['rep'];

            $used = array_map('intval', $exist);
            for ($s = 1; $s <= CharacterImageService::POOL_DEPTH; $s++) {
                if ($stock >= CharacterImageService::POOL_DEPTH) {
                    break;
                }
                if (in_array($s, $used, true)) {
                    continue;
                }
                $taskId = $svc->enqueuePoolTask($hash, $binFields, $d['category'], $s);
                if ($taskId === null) {
                    break;
                }
                $used[] = $s;
                $stock++;
                $inflight++;
                $enqueued++;
            }
        }
        $output->writeln("filler: 补池 {$enqueued} 张（demand 组合 " . count($demand) . " 个，跳过不适用 " . $skipped . " 条）");
    }
}
