<?php
declare(strict_types=1);

namespace app\live\service;

use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use app\live\service\chain\ChainCrypto;
use app\live\service\chain\EvmPayout;
use app\live\service\chain\SecretBox;
use app\live\service\chain\TronPayout;
use think\facade\Db;

/**
 * 链上打款统一入口
 *
 * 负责读取配置、选择链驱动、暴露余额/地址/打款/确认能力；
 * 提现单据的状态流转放在 WithdrawService，这里只管链上动作。
 *
 * 私钥只从 .env 读取（[PAYOUT] 段），不落库、不进代码仓库。
 */
final class ChainPayoutService
{
    public const MODE_AUTO   = 'auto';
    public const MODE_MANUAL = 'manual';

    /**
     * 打款模式：auto=审核后自动上链；manual=人工转账
     *
     * 取值优先级：后台配置(lp_platform_config.payout_mode) > .env [PAYOUT] MODE > manual
     */
    public function mode(): string
    {
        $value = trim($this->setting('payout_mode', (string) config('payout.mode', self::MODE_MANUAL)));
        return $value === self::MODE_AUTO ? self::MODE_AUTO : self::MODE_MANUAL;
    }

    public function isAutoEnabled(): bool
    {
        return $this->mode() === self::MODE_AUTO;
    }

    public function maxAttempts(): int
    {
        return max(1, (int) $this->setting('payout_max_attempts', (string) config('payout.max_attempts', 3)));
    }

    /**
     * 该网络是否具备自动打款能力（已配置热钱包私钥）
     */
    public function support(string $network): bool
    {
        try {
            $this->driver($network);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * 收款地址合法性检查（返回 '' 表示合法）
     *
     * 与驱动无关，因此在用户绑定收款地址时就能拦住错地址——
     * 地址错了链上转账必定失败，提前拦住比打款时才发现好得多。
     */
    public static function addressError(string $network, string $address): string
    {
        $address = trim($address);
        if ($address === '') {
            return '收款地址不能为空';
        }

        if ($network === 'TRC20') {
            $payload = ChainCrypto::base58CheckDecode($address);
            if ($payload === null) {
                return 'TRON 地址 base58 校验和不通过（地址被改动过），请核对后重填';
            }
            if (strlen($payload) !== 21 || $payload[0] !== "\x41") {
                return 'TRON 地址格式不对（应以 T 开头且共 34 位）';
            }
            return '';
        }

        if ($network === 'ERC20' || $network === 'BEP20') {
            if (!preg_match('/^0x[0-9a-fA-F]{40}$/', $address)) {
                return 'EVM 地址格式不对（应为 0x 开头的 42 位十六进制）';
            }
            if (preg_match('/^0x0{40}$/i', $address)) {
                return '不能使用全 0 占位地址';
            }
            return '';
        }

        return '不支持的提现网络：' . $network;
    }

    public function validateAddress(string $network, string $address): bool
    {
        return self::addressError($network, $address) === '';
    }

    public function hotAddress(string $network): string
    {
        return $this->driver($network)->address();
    }

    /**
     * 发起链上转账
     *
     * @return array{tx_hash:string, raw_tx:string}
     * @throws BusinessException 网络不支持 / 私钥未配置 / 链上异常
     */
    public function payout(string $network, string $to, string $amount): array
    {
        try {
            return $this->driver($network)->send($to, $amount);
        } catch (BusinessException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new BusinessException(ResultCode::SERVER_ERROR, '链上打款失败：' . $e->getMessage());
        }
    }

    /**
     * 查询交易确认状态
     *
     * @return array{state:string, message:string} state = pending|success|failed
     */
    public function confirm(string $network, string $txHash): array
    {
        try {
            return $this->driver($network)->txState($txHash);
        } catch (\Throwable $e) {
            // 查询失败按「未知」处理，绝不据此判定打款失败
            return ['state' => 'pending', 'message' => '链上查询异常：' . $e->getMessage()];
        }
    }

    /**
     * 各网络热钱包概况（运维巡检 / 后台展示用）
     *
     * @return array<int,array<string,mixed>>
     */
    public function overview(): array
    {
        $rows = [];
        foreach (array_keys((array) config('payout.networks')) as $network) {
            $row = [
                'network'       => $network,
                'label'         => $network,
                'gas_symbol'    => '',
                'configured'    => false,
                'hot_address'   => '',
                'token_balance' => '',
                'gas_balance'   => '',
                'error'         => '',
            ];
            // 单项失败不影响整表输出：诊断命令本身要能在半坏的状态下跑起来
            try {
                $cfg = $this->config($network);
                $row['label']      = (string) $cfg['label'];
                $row['gas_symbol'] = (string) $cfg['gas_symbol'];

                $driver = $this->driver($network);
                $row['configured']    = true;
                $row['hot_address']   = $driver->address();
                $row['token_balance'] = $driver->tokenBalance();
                $row['gas_balance']   = $driver->gasBalance();
            } catch (\Throwable $e) {
                $row['error'] = $e->getMessage();
            }
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * 合并配置：config/payout.php 为默认值，lp_platform_config 可运行期覆盖
     */
    public function config(string $network): array
    {
        $all = (array) config('payout.networks');
        $cfg = $all[$network] ?? null;
        if (!$cfg) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '不支持的提现网络：' . $network);
        }

        $overrides = [
            'rpc_config'       => ['rpc', 'string'],
            'fee_limit_config' => ['fee_limit', 'float'],
            'gas_limit_config' => ['gas_limit', 'int'],
            'gas_price_config' => ['gas_price_gwei', 'float'],
        ];
        foreach ($overrides as $cfgKey => [$target, $type]) {
            if (empty($cfg[$cfgKey])) {
                continue;
            }
            $value = trim(WalletService::config((string) $cfg[$cfgKey], ''));
            if ($value === '') {
                continue;
            }
            $cfg[$target] = match ($type) {
                'int'   => (int) $value,
                'float' => (float) $value,
                default => $value,
            };
        }

        // TronGrid API Key：后台配置优先，其次 .env
        $apiKey = !empty($cfg['api_key_config']) ? $this->setting((string) $cfg['api_key_config'], '') : '';
        if ($apiKey === '' && !empty($cfg['api_key_env'])) {
            $apiKey = trim((string) env($cfg['api_key_env'], ''));
        }
        $cfg['api_key'] = $apiKey;

        return $cfg;
    }

    /**
     * 热钱包私钥：后台配置（加密存库）优先，其次 .env
     *
     * @return string 明文私钥（已小写去 0x），未配置返回 ''
     */
    public function privateKey(string $network): string
    {
        $cfg = $this->config($network);

        $keyConfig = (string) ($cfg['key_config'] ?? '');
        if ($keyConfig !== '') {
            $stored = $this->setting($keyConfig, '');
            if ($stored !== '') {
                $plain = SecretBox::decrypt($stored);
                if ($plain === '') {
                    throw new BusinessException(
                        ResultCode::PARAM_ERROR,
                        $network . ' 后台保存的热钱包私钥解密失败（[PAYOUT] SECRET 是否被改过？），请在后台重新保存一次'
                    );
                }
                return ChainCrypto::normalizeHex($plain);
            }
        }

        return ChainCrypto::normalizeHex((string) env((string) $cfg['key_env'], ''));
    }

    /** 私钥来源：db(后台) / env / none，供后台页面展示 */
    public function keySource(string $network): string
    {
        $cfg = $this->config($network);
        $keyConfig = (string) ($cfg['key_config'] ?? '');
        if ($keyConfig !== '' && $this->setting($keyConfig, '') !== '') {
            return 'db';
        }
        return trim((string) env((string) $cfg['key_env'], '')) !== '' ? 'env' : 'none';
    }

    /**
     * 校验私钥是否合法（64 位十六进制）
     */
    public static function privateKeyError(string $privateKey): string
    {
        $key = ChainCrypto::normalizeHex($privateKey);
        if ($key === '') {
            return '私钥不能为空';
        }
        return preg_match('/^[0-9a-f]{64}$/', $key) ? '' : '私钥格式不正确：应为 64 位十六进制（不带 0x）';
    }

    /**
     * @throws BusinessException 私钥未配置或格式非法
     */
    public function driver(string $network): TronPayout|EvmPayout
    {
        $cfg = $this->config($network);

        $key = $this->privateKey($network);
        if ($key === '') {
            throw new BusinessException(ResultCode::PARAM_ERROR, $network . ' 未配置热钱包私钥，无法自动打款');
        }
        $err = self::privateKeyError($key);
        if ($err !== '') {
            throw new BusinessException(ResultCode::PARAM_ERROR, $network . ' ' . $err);
        }

        return $cfg['driver'] === 'tron'
            ? new TronPayout($cfg, $key)
            : new EvmPayout($cfg, $key);
    }

    /**
     * 读取平台配置（DB 优先，缺省回落到默认值）
     */
    public function setting(string $key, string $default = ''): string
    {
        return trim(WalletService::config($key, $default));
    }

    /**
     * 写平台配置（后台保存用；value 为空串时保存空值，不回落默认）
     */
    public function saveSetting(string $key, string $value, string $remark = ''): void
    {
        $db  = Db::connect('live_mysql');
        $now = date('Y-m-d H:i:s');
        $row = $db->table('lp_platform_config')->where('key', $key)->find();
        if ($row) {
            $db->table('lp_platform_config')->where('key', $key)->update(['value' => $value, 'updated_at' => $now]);
            return;
        }
        $db->table('lp_platform_config')->insert([
            'key' => $key, 'value' => $value, 'remark' => $remark, 'updated_at' => $now,
        ]);
    }
}
