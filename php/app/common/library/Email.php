<?php

namespace app\common\library;

use Throwable;
use think\facade\Lang;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * 邮件类
 * 继承PHPMailer并初始化好了站点系统配置中的邮件配置信息
 *
 * 【API 邮件（Resend）兜底】
 * 部分机房封锁出站 SMTP 端口（25/465/587 全部不可达，如 kiss 的 Shinjiru 机器），
 * SMTP 直连必然超时。此时在 .env 配置 [MAIL] DRIVER=resend + RESEND_API_KEY 即可走
 * HTTPS API 发信（443 端口不受封锁影响），调用方无需改动。
 *
 * .env 示例：
 *   [MAIL]
 *   DRIVER = resend
 *   RESEND_API_KEY = re_xxxxxxxx
 *   RESEND_FROM = Kisss <onboarding@resend.dev>   ; 或验证过域名的 noreply@kisss.ai
 *   RESEND_FROM_NAME = Kisss
 */
class Email extends PHPMailer
{
    /**
     * 是否已在管理后台配置好邮件服务
     * @var bool
     */
    public bool $configured = false;

    /** 使用 API 发信（非 SMTP） */
    private bool $apiMode = false;

    /** API 模式下的发件人 */
    private string $apiFrom = '';
    private string $apiFromName = '';

    /**
     * 默认配置
     * @var array
     */
    public array $options = [
        'charset' => 'utf-8', //编码格式
        'debug'   => true, //调式模式
        'lang'    => 'zh_cn',
    ];

    /**
     * 构造函数
     * @param array $options
     * @throws Throwable
     */
    public function __construct(array $options = [])
    {
        $this->options = array_merge($this->options, $options);

        parent::__construct($this->options['debug']);
        $langSet = Lang::getLangSet();
        if ($langSet == 'zh-cn' || !$langSet) $langSet = 'zh_cn';
        $this->options['lang'] = $this->options['lang'] ?: $langSet;

        $this->setLanguage($this->options['lang'], root_path() . 'vendor' . DIRECTORY_SEPARATOR . 'phpmailer' . DIRECTORY_SEPARATOR . 'phpmailer' . DIRECTORY_SEPARATOR . 'language' . DIRECTORY_SEPARATOR);
        $this->CharSet = $this->options['charset'];

        // ── API 邮件优先：配了 Key 就不走 SMTP（机房封端口时唯一可行方案）──
        if ($this->initApiMailer()) {
            return;
        }

        $sysMailConfig    = get_sys_config('', 'mail');
        $senderName       = trim((string)($sysMailConfig['smtp_sender_name'] ?? ''));
        unset($sysMailConfig['smtp_sender_name']); // 发件人名称是可选配置，不参与必填校验
        $this->configured = true;
        foreach ($sysMailConfig as $item) {
            if (!$item) {
                $this->configured = false;
            }
        }
        if ($this->configured) {
            $this->Host       = $sysMailConfig['smtp_server'];
            $this->SMTPAuth   = true;
            $this->Username   = $sysMailConfig['smtp_user'];
            $this->Password   = $sysMailConfig['smtp_pass'];
            $this->SMTPSecure = $sysMailConfig['smtp_verification'] == 'SSL' ? self::ENCRYPTION_SMTPS : self::ENCRYPTION_STARTTLS;
            $this->Port       = $sysMailConfig['smtp_port'];

            $this->setFrom($sysMailConfig['smtp_sender_mail'], $senderName ?: $sysMailConfig['smtp_user']);
        }
    }

    /**
     * 初始化 API 发信（目前支持 Resend）。返回 true 表示已切换为 API 模式。
     */
    private function initApiMailer(): bool
    {
        $driver = strtolower(trim((string) env('mail.driver', '')));
        $key    = trim((string) env('mail.resend_api_key', ''));
        if ($driver !== 'resend' || $key === '') {
            return false;
        }

        $from     = trim((string) env('mail.resend_from', ''));
        $fromName = trim((string) env('mail.resend_from_name', ''));
        if ($from === '') {
            return false;
        }
        // 支持 "Kisss <noreply@kisss.ai>" 形式
        if (preg_match('/^(.*?)<([^>]+)>$/', $from, $m)) {
            $fromName = $fromName !== '' ? $fromName : trim($m[1]);
            $from     = trim($m[2]);
        }
        if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $this->apiMode     = true;
        $this->apiFrom     = $from;
        $this->apiFromName = mb_substr($fromName, 0, 64);
        $this->configured  = true;
        // PHPMailer 的 From 也设上，便于读取方保持一致
        try {
            $this->setFrom($this->apiFrom, $this->apiFromName);
        } catch (Throwable) {
            // 仅用于展示，失败不影响 API 发送
        }
        return true;
    }

    /**
     * 发送：API 模式走 HTTPS，否则沿用 PHPMailer SMTP。
     */
    public function send(): bool
    {
        if (!$this->apiMode) {
            return parent::send();
        }

        $to = [];
        foreach ($this->getToAddresses() as $addr) {
            $to[] = $addr[0];
        }
        if (!$to) {
            $this->ErrorInfo = 'No recipient';
            return false;
        }

        $payload = [
            'from'    => $this->apiFromName !== ''
                ? sprintf('%s <%s>', $this->apiFromName, $this->apiFrom)
                : $this->apiFrom,
            'to'      => $to,
            'subject' => $this->Subject,
        ];
        $body = (string) $this->Body;
        if ($this->ContentType === 'text/html' || stripos($body, '<') !== false) {
            $payload['html'] = $body;
            if ((string) $this->AltBody !== '') {
                $payload['text'] = (string) $this->AltBody;
            }
        } else {
            $payload['text'] = $body !== '' ? $body : (string) $this->AltBody;
        }

        try {
            $client = new \GuzzleHttp\Client([
                'timeout'         => 15,
                'connect_timeout' => 8,
                'http_errors'     => false,
                'allow_redirects' => false,
            ]);
            $resp = $client->post('https://api.resend.com/emails', [
                'headers' => [
                    'Authorization' => 'Bearer ' . trim((string) env('mail.resend_api_key', '')),
                    'Content-Type'  => 'application/json',
                ],
                'json' => $payload,
            ]);
            $code = $resp->getStatusCode();
            if ($code >= 200 && $code < 300) {
                return true;
            }
            $this->ErrorInfo = 'Resend HTTP ' . $code . ': ' . substr((string) $resp->getBody(), 0, 300);
            // 不记录收件人邮箱明文
            \think\facade\Log::error('resend send failed: ' . $this->ErrorInfo);
            return false;
        } catch (Throwable $e) {
            $this->ErrorInfo = 'Resend exception: ' . $e->getMessage();
            \think\facade\Log::error('resend send exception: ' . $e->getMessage());
            return false;
        }
    }

    /** 是否为 API 模式（便于后台/自检判断） */
    public function isApiMode(): bool
    {
        return $this->apiMode;
    }

    public function setSubject($subject): void
    {
        // API 模式下服务端按 UTF-8 原样发送，不能再做 MIME 编码（否则收件人看到 =?utf-8?B?..?=）
        if ($this->apiMode) {
            $this->Subject = (string) $subject;
            return;
        }
        $this->Subject = "=?utf-8?B?" . base64_encode($subject) . "?=";
    }
}
