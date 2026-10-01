<?php
declare(strict_types=1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;

/**
 * 提现打款配置自检（payout:info）
 *
 * 部署后先跑这个：能一眼看出私钥是否配好、热钱包地址是否正确、
 * USDT 与手续费币余额够不够，避免提现单进来才发现打不出去。
 *
 *   php think payout:info
 */
final class PayoutInfo extends Command
{
    protected function configure()
    {
        $this->setName('payout:info')->setDescription('Show withdrawal payout mode and hot wallet status');
    }

    protected function execute(Input $input, Output $output)
    {
        $service = new \app\live\service\ChainPayoutService();

        $output->writeln('mode = ' . $service->mode() . ($service->isAutoEnabled() ? ' (自动链上打款)' : ' (人工打款)'));
        $output->writeln(sprintf('%-8s %-8s %-36s %-16s %-16s %s', '网络', '可用', '热钱包地址', 'USDT 余额', '手续费余额', '备注'));

        foreach ($service->overview() as $row) {
            $output->writeln(sprintf(
                '%-8s %-8s %-36s %-16s %-16s %s',
                $row['network'],
                $row['configured'] ? 'yes' : 'no',
                $row['hot_address'] !== '' ? $row['hot_address'] : '-',
                $row['token_balance'] !== '' ? $row['token_balance'] : '-',
                $row['gas_balance'] !== '' ? $row['gas_balance'] . ' ' . $row['gas_symbol'] : '-',
                $row['error']
            ));
        }

        return 0;
    }
}
