<?php
declare(strict_types=1);

namespace app\live\validate;

use think\Validate;

final class UserValidate extends Validate
{
    protected $rule = [
        'username' => 'require|alphaDash|length:4,50',
        'password' => 'require|length:6,20',
        'nickname' => 'require|max:50',
        'mobile'   => 'mobile',
        'email'    => 'email',
    ];

    protected $message = [
        'username.require'  => '用户名不能为空',
        'username.alphaDash' => '用户名只能包含字母数字下划线和横线',
        'username.length'   => '用户名长度为4-50个字符',
        'password.require'  => '密码不能为空',
        'password.length'   => '密码长度为6-20个字符',
        'nickname.require'  => '昵称不能为空',
        'nickname.max'      => '昵称最多50个字符',
        'mobile.mobile'     => '手机号格式错误',
        'email.email'       => '邮箱格式错误',
    ];

    protected $scene = [
        'register'    => ['username', 'password', 'nickname'],
        'login'       => ['username', 'password'],
        'updateProfile' => ['nickname'],
    ];
}
