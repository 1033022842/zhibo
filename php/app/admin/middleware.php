<?php
return [
    // IP 白名单（应用层）：默认开关关闭，不改变现有行为；开启后拦非白名单 IP
    \app\admin\middleware\AdminIpWhitelist::class,
    \app\common\middleware\AllowCrossDomain::class,
    // 素材独立存储：入参把签名地址还原成 /storage/xxx（默认关闭，零影响）
    \app\common\middleware\MediaInputNormalizer::class,
    \app\common\middleware\AdminLog::class,
    \think\middleware\LoadLangPack::class,
    // 出参把 /storage/ 换成签名地址（默认关闭，零影响）
    \app\common\middleware\MediaUrlSigner::class,
];
