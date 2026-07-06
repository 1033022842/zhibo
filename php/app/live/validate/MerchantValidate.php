<?php
declare(strict_types=1);

namespace app\live\validate;

use think\Validate;

final class MerchantValidate extends Validate
{
    protected $rule = [
        'email'          => 'require|email|max:255',
        'id_card_front'  => 'require|max:512',
        'id_card_back'   => 'require|max:512',
    ];

    protected $message = [
        'email.require'         => '认证邮箱不能为空',
        'email.email'           => '邮箱格式不正确',
        'email.max'             => '邮箱最多255个字符',
        'id_card_front.require' => '身份证正面不能为空',
        'id_card_front.max'     => '身份证正面图片路径过长',
        'id_card_back.require'  => '身份证背面不能为空',
        'id_card_back.max'      => '身份证背面图片路径过长',
    ];

    protected $scene = [
        'submit' => ['email', 'id_card_front', 'id_card_back'],
    ];
}
