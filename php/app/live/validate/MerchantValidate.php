<?php
declare(strict_types=1);

namespace app\live\validate;

use think\Validate;

final class MerchantValidate extends Validate
{
    protected $rule = [
        'real_name'        => 'require|max:50',
        'id_card_no'       => 'require|max:18|alphaNum',
        'phone'            => 'require|mobile|max:20',
        'email'            => 'require|email|max:255',
        'shop_name'        => 'require|max:100',
        'shop_type'        => 'require|max:50',
        'shop_description' => 'max:500',
        'id_card_front'    => 'require|max:512',
        'id_card_back'     => 'require|max:512',
        'business_license' => 'require|max:512',
    ];

    protected $message = [
        'real_name.require'         => '真实姓名不能为空',
        'real_name.max'             => '真实姓名最多50个字符',
        'id_card_no.require'        => '身份证号不能为空',
        'id_card_no.max'            => '身份证号最多18个字符',
        'id_card_no.alphaNum'       => '身份证号格式不正确',
        'phone.require'             => '手机号不能为空',
        'phone.mobile'              => '手机号格式不正确',
        'phone.max'                 => '手机号最多20个字符',
        'email.require'             => '认证邮箱不能为空',
        'email.email'               => '邮箱格式不正确',
        'email.max'                 => '邮箱最多255个字符',
        'shop_name.require'         => '店铺名称不能为空',
        'shop_name.max'             => '店铺名称最多100个字符',
        'shop_type.require'         => '经营类目不能为空',
        'shop_type.max'             => '经营类目最多50个字符',
        'shop_description.max'      => '店铺简介最多500个字符',
        'id_card_front.require'     => '身份证正面不能为空',
        'id_card_front.max'         => '身份证正面图片路径过长',
        'id_card_back.require'      => '身份证背面不能为空',
        'id_card_back.max'          => '身份证背面图片路径过长',
        'business_license.require'  => '营业执照不能为空',
        'business_license.max'      => '营业执照图片路径过长',
    ];

    protected $scene = [
        'submit' => ['real_name', 'id_card_no', 'phone', 'email', 'shop_name', 'shop_type', 'shop_description', 'id_card_front', 'id_card_back', 'business_license'],
    ];
}
