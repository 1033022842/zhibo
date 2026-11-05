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
     * GET 资金流水分页 ?page=&page_size=&type=&flow=income|expense
     */
    public function ledger()
    {
        $userId = $this->getAuthUserId();
        $page = (int) $this->request->param('page', 1);
        $pageSize = min(50, (int) $this->request->param('page_size', 20));
        $type = trim((string) $this->request->param('type', ''));
        $flow = trim((string) $this->request->param('flow', ''));

        $query = Db::connect('live_mysql')->table('lp_wallet_ledger')
            ->where('user_id', $userId)
            ->order('id', 'desc');
        if ($type !== '') {
            $query->where('biz_type', $type);
        }
        if ($flow === 'income') {
            $query->where('direction', 1);
        } elseif ($flow === 'expense') {
            $query->where('direction', 2);
        }

        $total = $query->count();
        $list = $query->page($page, $pageSize)->select()->toArray();

        $typeMap = [
            'recharge'             => 'Token top-up',
            'gift'                 => 'Gift sent',
            'gift_income'          => 'Gift received',
            'gift_commission'      => 'Platform commission',
            'gift_refund'          => 'Gift refund',
            'short_unlock'         => 'Short video unlocked',
            'short_income'         => 'Short video income',
            'affection_buy'        => 'Affection purchased',
            'affection_income'     => 'Affection income',
            'media_unlock'         => 'Video unlocked',
            'media_income'         => 'Video unlock income',
            'chat_overage'         => 'Chat top-up',
            'chat_income'          => 'Chat income',
            'call_buy'             => 'Voice call',
            'call_income'          => 'Voice call income',
            'crowdfunding_pledge'  => 'Crowdfunding pledge',
            'crowdfunding_unlock'  => 'Crowdfunding transfer',
            'crowdfunding_refund'  => 'Crowdfunding refund',
            'crowdfunding_commission' => 'Crowdfunding commission',
            'vip_buy'              => 'Membership purchase',
            'vip_refund'           => 'Membership refund',
            'vip_daily'            => 'Daily member reward',
            'withdraw'             => 'Withdrawal',
            'withdraw_refund'      => 'Withdrawal refund',
            'adjust'               => 'Adjustment',
            'video_gen'            => 'AI video generation',
            'video_gen_refund'     => 'AI video refund',
            'character_save'       => 'AI character saved',
            'character_regen'      => 'AI portrait regenerated',
            'chat_gift'            => 'Chat gift',
            'private_unlock'       => 'Private content unlocked',
            'shop_buy'             => 'Shop purchase',
            'invite_commission'    => 'Referral commission',
            'invite_recharge'      => 'Referral recharge bonus',
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
