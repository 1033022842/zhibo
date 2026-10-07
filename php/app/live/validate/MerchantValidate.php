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
        'real_name.require'         => 'Full name is required',
        'real_name.max'             => 'Full name must be at most 50 characters',
        'id_card_no.require'        => 'ID number is required',
        'id_card_no.max'            => 'ID number must be at most 18 characters',
        'id_card_no.alphaNum'       => 'Invalid ID number format',
        'phone.require'             => 'Phone number is required',
        'phone.mobile'              => 'Invalid phone number format',
        'phone.max'                 => 'Phone number must be at most 20 characters',
        'email.require'             => 'Email is required',
        'email.email'               => 'Invalid email format',
        'email.max'                 => 'Email must be at most 255 characters',
        'shop_name.require'         => 'Store name is required',
        'shop_name.max'             => 'Store name must be at most 100 characters',
        'shop_type.require'         => 'Business category is required',
        'shop_type.max'             => 'Business category must be at most 50 characters',
        'shop_description.max'      => 'Store description must be at most 500 characters',
        'id_card_front.require'     => 'ID card front is required',
        'id_card_front.max'         => 'ID card front image path is too long',
        'id_card_back.require'      => 'ID card back is required',
        'id_card_back.max'          => 'ID card back image path is too long',
        'business_license.require'  => 'Business license is required',
        'business_license.max'      => 'Business license image path is too long',
    ];

    protected $scene = [
        'submit' => ['real_name', 'id_card_no', 'phone', 'email', 'shop_name', 'shop_type', 'shop_description', 'id_card_front', 'id_card_back', 'business_license'],
    ];
}
