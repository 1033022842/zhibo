<?php
return [
    \app\common\middleware\AllowCrossDomain::class,
    \think\middleware\LoadLangPack::class,
    // 输出层：/storage/ 素材地址统一换成存储服务器签名地址（默认关闭，零影响）
    \app\common\middleware\MediaUrlSigner::class,
];
