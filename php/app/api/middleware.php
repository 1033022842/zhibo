<?php
return [
    \app\common\middleware\AllowCrossDomain::class,
    \think\middleware\LoadLangPack::class,
    // 入参还原：签名地址 → /storage/xxx（必须放在输出改写之前）
    //
    // 2026-10-22 事故：这个中间件原先只注册在 admin 应用，api 应用没有。
    // worker 生成完不再回传相对路径，而是回传 uploadVideo 返回的**已签名**地址，
    // 于是签名 URL 原样落进 lp_ai_task.video_url —— 签名 6 小时后过期，
    // 历史任务全部 410 裂图（图片/视频都中招）。
    // 补上之后 api 侧任何入参里的签名地址都会先被还原成稳定逻辑路径。
    \app\common\middleware\MediaInputNormalizer::class,
    // 输出层：/storage/ 素材地址统一换成存储服务器签名地址（默认关闭，零影响）
    \app\common\middleware\MediaUrlSigner::class,
];
