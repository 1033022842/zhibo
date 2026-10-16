<?php
return [
    // IP 白名单（应用层）：默认开关关闭，不改变现有行为；开启后拦非白名单 IP
    \app\admin\middleware\AdminIpWhitelist::class,
    \app\common\middleware\AllowCrossDomain::class,
    \app\common\middleware\AdminLog::class,
    \think\middleware\LoadLangPack::class,
];
