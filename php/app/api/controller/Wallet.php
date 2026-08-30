<?php
declare(strict_types=1);

namespace app\api\controller;

use think\App;
use app\BaseController;
use think\facade\Db;

/**
 * 钱包（流水查询）
 */
final class Wallet extends BaseController
{
    protected array $middleware = [
        \app\live\middleware\Auth::class,
    ];

    /**
     * GET 资金流水分页 ?page=&page_size=&type=
     */
    public function ledger()
    {
        $userId = $this->getAuthUserId();
        $page = (int) $this->request->param('page', 1);
        $pageSize = min(50, (int) $this->request->param('page_size', 20));
        $type = trim((string) $this->request->param('type', ''));

        $query = Db::connect('live_mysql')->table('lp_wallet_ledger')
            ->where('user_id', $userId)
            ->order('id', 'desc');
        if ($type !== '') {
            $query->where('biz_type', $type);
        }

        $total = $query->count();
        $list = $query->page($page, $pageSize)->select()->toArray();

        $typeMap = [
            'recharge'             => '充值',
            'gift'                 => '送礼',
            'gift_income'          => '礼物收入',
            'gift_commission'      => '平台抽成',
            'gift_refund'          => '礼物退款',
            'crowdfunding_pledge'  => '众筹支持',
            'crowdfunding_unlock'  => '众筹划转',
            'crowdfunding_refund'  => '众筹退款',
            'crowdfunding_commission' => '众筹抽成',
            'vip_buy'              => '购买会员',
            'vip_refund'           => '会员退款',
            'vip_daily'            => '会员日领',
            'withdraw'             => '提现冻结',
            'withdraw_refund'      => '提现退回',
            'adjust'               => '调整',
        ];

        foreach ($list as &$r) {
            $r['type_text'] = $typeMap[(string)$r['biz_type']] ?? $r['biz_type'];
            $r['direction'] = (int)$r['direction']; // 1收入 2支出
            $r['amount'] = (float)$r['amount'];
            $r['balance_after'] = (float)$r['balance_after'];
        }

        // 汇总余额
        $wallet = Db::connect('live_mysql')->table('lp_wallet_account')
            ->where('user_id', $userId)->find();

        return $this->jsonSuccess([
            'list'    => $list,
            'total'   => $total,
            'balance' => $wallet ? (float)$wallet['diamond_balance'] : 0,
        ]);
    }
}
