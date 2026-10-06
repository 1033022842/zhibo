<?php
declare(strict_types=1);

namespace app\live\service;

use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use think\facade\Db;

/**
 * 法币支付通道（单个国家按本币收款）
 *
 * 后台 lp_fiat_channel 里配「结算币种 + 1 USD 兑多少本币 + 金额精度」，
 * 用户订阅 $15 下单时自动折算成本币（如 RM 70.53）；汇率在下单那一刻
 * 锁定并写进订单，之后运营改汇率不会影响已生成的订单。
 */
final class FiatChannelService
{
    /** 已启用的通道（前台可选用），按后台排序；汇率没配的不下发，避免用户点进去才发现不能用 */
    public static function channels(): array
    {
        $rows = Db::connect('live_mysql')->table('lp_fiat_channel')
            ->where('status', 1)
            ->where('usd_rate', '>', 0)
            ->order('sort', 'asc')
            ->order('id', 'asc')
            ->select()->toArray();

        return array_map([self::class, 'normalize'], $rows);
    }

    /**
     * 取一条启用中的通道，下架/不存在直接抛业务异常
     */
    public static function find(int $id): array
    {
        $row = Db::connect('live_mysql')->table('lp_fiat_channel')->where('id', $id)->find();
        if (!$row || (int) $row['status'] !== 1) {
            throw new BusinessException(ResultCode::RECORD_NOT_FOUND, '支付方式不存在或已下架');
        }
        return self::normalize($row);
    }

    /** 库里的行 → 前台结构（类型归一，避免 JSON 里出现 null） */
    private static function normalize(array $row): array
    {
        return [
            'id'         => (int) $row['id'],
            'name'       => (string) $row['name'],
            'gateway'    => (string) $row['gateway'],
            'currency'   => (string) $row['currency'],
            'symbol'     => (string) $row['currency_symbol'],
            'gref'       => (string) $row['gateway_ref'],
            'instructions' => (string) $row['pay_instructions'],
            'usd_rate'   => (float) $row['usd_rate'],
            'decimals'   => (int) $row['decimals'],
            'min_amount' => (float) $row['min_amount'],
            'diamond_rate' => (float) $row['diamond_rate'],
        ];
    }

    /**
     * USD → 本币：金额 = USD × 汇率，按通道精度四舍五入；低于通道最低金额直接拒绝
     *
     * @return array{currency:string,symbol:string,amount:string,usd:string,rate:float}
     */
    public static function convert(float $usd, array $channel): array
    {
        $rate = (float) $channel['usd_rate'];
        if ($rate <= 0) {
            throw new BusinessException(
                ResultCode::PARAM_ERROR,
                '后台未配置「' . $channel['name'] . '」的 USD 汇率，请联系客服'
            );
        }

        $decimals = max(0, min(8, (int) $channel['decimals']));
        $amount   = number_format(round($usd * $rate, $decimals), $decimals, '.', '');

        $min = (float) $channel['min_amount'];
        if ($min > 0 && (float) $amount < $min) {
            throw new BusinessException(
                ResultCode::PARAM_ERROR,
                '「' . $channel['name'] . '」最低支付 ' . self::format($channel, number_format($min, $decimals, '.', ''))
            );
        }

        return [
            'currency' => (string) $channel['currency'],
            'symbol'   => (string) $channel['symbol'],
            'amount'   => $amount,
            'usd'      => number_format($usd, 2, '.', ''),
            'rate'     => $rate,
        ];
    }

    /** 带币种符号的金额文案，如「RM 70.53」；没配符号就退回币种代码 */
    public static function format(array $channel, string $amount): string
    {
        $prefix = (string) $channel['symbol'] !== '' ? (string) $channel['symbol'] : (string) $channel['currency'];
        return $prefix === '' ? $amount : $prefix . ' ' . $amount;
    }
}
