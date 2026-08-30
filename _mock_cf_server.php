<?php
// 本地验证用：静态服务 ai-girl-malaysia.com 并模拟众筹 API
$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);

header('Content-Type: application/json; charset=utf-8');

if ($path === '/api/v1/crowdfunding/list') {
    $page = intval($_GET['page'] ?? 1);
    $pageSize = intval($_GET['page_size'] ?? 10);
    $all = [
        ['id'=>1,'user_id'=>10,'title'=>'Malaysia Sweetheart Aina','persona_name'=>'Aina','description'=>'温柔大马姑娘，会粤语、马来语和中文，喜欢煲剧和美食。','cover_url'=>'./Girls_files/image1-510c9159fd51d7802ef2de3bc1e6edcbd5c5dc0a87ef225a5871143c46eaba02.webp','target_amount'=>10000,'raised_amount'=>7350,'supporter_count'=>86,'deadline'=>date('Y-m-d H:i:s', time()+86400*6),'status'=>1,'persona_id'=>null,'created_at'=>'2026-08-01 10:00:00'],
        ['id'=>2,'user_id'=>11,'title'=>'赛博朋克少女 Nova','persona_name'=>'Nova','description'=>'未来感十足的赛博朋克风 AI 少女，霓虹夜城的故事由你书写。','cover_url'=>'','target_amount'=>5000,'raised_amount'=>5000,'supporter_count'=>42,'deadline'=>date('Y-m-d H:i:s', time()+3600*30),'status'=>1,'persona_id'=>null,'created_at'=>'2026-08-05 09:00:00'],
        ['id'=>3,'user_id'=>12,'title'=>'元气偶像 Miko','persona_name'=>'Miko','description'=>'','cover_url'=>'./Girls_files/image1-510c9159fd51d7802ef2de3bc1e6edcbd5c5dc0a87ef225a5871143c46eaba02.webp','target_amount'=>20000,'raised_amount'=>1200,'supporter_count'=>9,'deadline'=>date('Y-m-d H:i:s', time()+3600*5),'status'=>1,'persona_id'=>null,'created_at'=>'2026-08-10 14:00:00'],
    ];
    $list = array_slice($all, ($page-1)*$pageSize, $pageSize);
    echo json_encode(['code'=>'00000','msg'=>'成功','data'=>['list'=>$list,'total'=>count($all)]]);
    exit;
}

if ($path === '/api/v1/crowdfunding/balance') {
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (strpos($auth, 'Bearer test-token') !== 0) {
        echo json_encode(['code'=>'A0001','msg'=>'登录已过期','data'=>null]);
        exit;
    }
    echo json_encode(['code'=>'00000','msg'=>'成功','data'=>['balance'=>2333.5,'status'=>1]]);
    exit;
}

if ($path === '/api/v1/crowdfunding/pledge') {
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (strpos($auth, 'Bearer test-token') !== 0) {
        echo json_encode(['code'=>'A0001','msg'=>'登录已过期','data'=>null]);
        exit;
    }
    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    $amount = floatval($body['amount'] ?? 0);
    if ($amount > 2333.5) {
        echo json_encode(['code'=>'G0302','msg'=>'钻石余额不足，请先充值','data'=>null]);
        exit;
    }
    echo json_encode(['code'=>'00000','msg'=>'支持成功','data'=>null]);
    exit;
}

// 其他路径按静态文件返回
return false;
