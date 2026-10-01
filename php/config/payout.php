<?php
/**
 * 提现链上打款（自动出金）配置
 *
 * 私钥只从 .env 读取，绝不落库、不进代码：
 *   [PAYOUT]
 *   MODE       = auto            ; auto=后台审核通过后自动链上打款；manual=人工转账后后台登记 TxHash
 *   TRC20_KEY  = <64位hex私钥>    ; 配置后 TRC20 才具备自动打款能力
 *   ERC20_KEY  = <64位hex私钥>
 *   BEP20_KEY  = <64位hex私钥>
 *   TRC20_API_KEY = <TronGrid API Key，可选>
 *
 * 运行期可调参数放 lp_platform_config（后台/DB 直接改，无需改代码）：
 *   payout_trc20_fee_limit          单笔 TRC20 交易的 fee_limit（单位 TRX，默认 30）
 *   payout_erc20_rpc / payout_bep20_rpc         自定义 RPC 节点
 *   payout_erc20_gas_limit / payout_bep20_gas_limit
 *   payout_erc20_gas_price_gwei / payout_bep20_gas_price_gwei   0 表示用节点建议价
 *
 * 依赖：kornrunner/secp256k1 + kornrunner/keccak（纯 PHP，需要 ext-gmp）
 */

return [
    // 打款模式：manual 为默认值，未配置热钱包私钥时保持人工打款，行为与历史版本一致
    'mode'         => (string) env('payout.mode', 'manual'),
    // 单笔提现最多自动尝试打款次数
    'max_attempts' => 3,

    'networks' => [
        // USDT-TRC20（波场）：手续费最低，主流选择
        'TRC20' => [
            'label'      => 'USDT-TRC20',
            'driver'     => 'tron',
            'contract'   => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
            'decimals'   => 6,
            'gas_symbol' => 'TRX',
            'api'        => 'https://api.trongrid.io',
            // 后台改动优先；这两个键是 .env 兜底用的
            'key_env'    => 'payout.trc20_key',
            'api_key_env' => 'payout.trc20_api_key',
            // 后台（lp_platform_config）里对应的键名
            'key_config'     => 'payout_trc20_key',
            'api_key_config' => 'payout_trc20_api_key',
            // fee_limit 单位 TRX，可用 lp_platform_config.payout_trc20_fee_limit 覆盖
            'fee_limit'  => 30,
            'fee_limit_config' => 'payout_trc20_fee_limit',
        ],

        // USDT-ERC20（以太坊）
        'ERC20' => [
            'label'      => 'USDT-ERC20',
            'driver'     => 'evm',
            'contract'   => '0xdAC17F958D2ee523a2206206994597C13D831ec7',
            'decimals'   => 6,
            'gas_symbol' => 'ETH',
            'chain_id'   => 1,
            // 公共节点，支持逗号分隔多个做降级；生产建议换成自建节点
            'rpc'        => 'https://ethereum-rpc.publicnode.com,https://1rpc.io/eth',
            'rpc_config' => 'payout_erc20_rpc',
            'key_env'    => 'payout.erc20_key',
            'key_config' => 'payout_erc20_key',
            'gas_limit'  => 65000,
            'gas_limit_config'   => 'payout_erc20_gas_limit',
            'gas_price_config'   => 'payout_erc20_gas_price_gwei',
        ],

        // USDT-BEP20（币安智能链）：注意 BSC 上 USDT 是 18 位小数
        'BEP20' => [
            'label'      => 'USDT-BEP20',
            'driver'     => 'evm',
            'contract'   => '0x55d398326f99059fF775485246999027B3197955',
            'decimals'   => 18,
            'gas_symbol' => 'BNB',
            'chain_id'   => 56,
            // 公共节点，支持逗号分隔多个做降级；生产建议换成自建节点
            'rpc'        => 'https://bsc-dataseed.bnbchain.org,https://1rpc.io/bnb',
            'rpc_config' => 'payout_bep20_rpc',
            'key_env'    => 'payout.bep20_key',
            'key_config' => 'payout_bep20_key',
            'gas_limit'  => 100000,
            'gas_limit_config'   => 'payout_bep20_gas_limit',
            'gas_price_config'   => 'payout_bep20_gas_price_gwei',
        ],
    ],
];
