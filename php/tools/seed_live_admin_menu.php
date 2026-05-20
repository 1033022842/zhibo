<?php

declare(strict_types=1);

use think\App;
use app\common\library\Menu;

require dirname(__DIR__) . '/vendor/autoload.php';

$app = new App(dirname(__DIR__) . DIRECTORY_SEPARATOR);
$app->initialize();
$app->boot();

$menu = [
    [
        'type' => 'menu_dir',
        'title' => '直播运营',
        'name' => 'live',
        'path' => 'live',
        'icon' => 'fa fa-video-camera',
        'component' => 'Layout',
        'menu_type' => 'tab',
        'url' => '',
        'extend' => 'none',
        'remark' => '直播后台运营菜单',
        'weigh' => 120,
        'children' => [
            [
                'type' => 'menu',
                'title' => '人设管理',
                'name' => 'live/persona',
                'path' => 'live/persona',
                'icon' => 'fa fa-user-circle',
                'component' => '/src/views/backend/live/persona/index.vue',
                'menu_type' => 'tab',
                'url' => '',
                'extend' => 'none',
                'remark' => '直播人设配置',
                'weigh' => 119,
                'children' => [
                    ['type' => 'button', 'title' => '查看', 'name' => 'live/persona/index', 'path' => '', 'icon' => '', 'component' => '', 'menu_type' => 'tab', 'url' => '', 'extend' => 'none', 'remark' => '', 'weigh' => 10],
                    ['type' => 'button', 'title' => '新增', 'name' => 'live/persona/add', 'path' => '', 'icon' => '', 'component' => '', 'menu_type' => 'tab', 'url' => '', 'extend' => 'none', 'remark' => '', 'weigh' => 9],
                    ['type' => 'button', 'title' => '编辑', 'name' => 'live/persona/edit', 'path' => '', 'icon' => '', 'component' => '', 'menu_type' => 'tab', 'url' => '', 'extend' => 'none', 'remark' => '', 'weigh' => 8],
                    ['type' => 'button', 'title' => '删除', 'name' => 'live/persona/del', 'path' => '', 'icon' => '', 'component' => '', 'menu_type' => 'tab', 'url' => '', 'extend' => 'none', 'remark' => '', 'weigh' => 7],
                    ['type' => 'button', 'title' => '选择', 'name' => 'live/persona/select', 'path' => '', 'icon' => '', 'component' => '', 'menu_type' => 'tab', 'url' => '', 'extend' => 'none', 'remark' => '', 'weigh' => 6],
                ],
            ],
            [
                'type' => 'menu',
                'title' => '素材管理',
                'name' => 'live/mediaAsset',
                'path' => 'live/mediaAsset',
                'icon' => 'fa fa-film',
                'component' => '/src/views/backend/live/mediaAsset/index.vue',
                'menu_type' => 'tab',
                'url' => '',
                'extend' => 'none',
                'remark' => '直播素材池管理',
                'weigh' => 118,
                'children' => [
                    ['type' => 'button', 'title' => '查看', 'name' => 'live/mediaAsset/index', 'path' => '', 'icon' => '', 'component' => '', 'menu_type' => 'tab', 'url' => '', 'extend' => 'none', 'remark' => '', 'weigh' => 10],
                    ['type' => 'button', 'title' => '新增', 'name' => 'live/mediaAsset/add', 'path' => '', 'icon' => '', 'component' => '', 'menu_type' => 'tab', 'url' => '', 'extend' => 'none', 'remark' => '', 'weigh' => 9],
                    ['type' => 'button', 'title' => '编辑', 'name' => 'live/mediaAsset/edit', 'path' => '', 'icon' => '', 'component' => '', 'menu_type' => 'tab', 'url' => '', 'extend' => 'none', 'remark' => '', 'weigh' => 8],
                    ['type' => 'button', 'title' => '删除', 'name' => 'live/mediaAsset/del', 'path' => '', 'icon' => '', 'component' => '', 'menu_type' => 'tab', 'url' => '', 'extend' => 'none', 'remark' => '', 'weigh' => 7],
                    ['type' => 'button', 'title' => '选择', 'name' => 'live/mediaAsset/select', 'path' => '', 'icon' => '', 'component' => '', 'menu_type' => 'tab', 'url' => '', 'extend' => 'none', 'remark' => '', 'weigh' => 6],
                ],
            ],
            [
                'type' => 'menu',
                'title' => '房间管理',
                'name' => 'live/room',
                'path' => 'live/room',
                'icon' => 'fa fa-television',
                'component' => '/src/views/backend/live/room/index.vue',
                'menu_type' => 'tab',
                'url' => '',
                'extend' => 'none',
                'remark' => '直播房间与播单绑定',
                'weigh' => 117,
                'children' => [
                    ['type' => 'button', 'title' => '查看', 'name' => 'live/room/index', 'path' => '', 'icon' => '', 'component' => '', 'menu_type' => 'tab', 'url' => '', 'extend' => 'none', 'remark' => '', 'weigh' => 10],
                    ['type' => 'button', 'title' => '新增', 'name' => 'live/room/add', 'path' => '', 'icon' => '', 'component' => '', 'menu_type' => 'tab', 'url' => '', 'extend' => 'none', 'remark' => '', 'weigh' => 9],
                    ['type' => 'button', 'title' => '编辑', 'name' => 'live/room/edit', 'path' => '', 'icon' => '', 'component' => '', 'menu_type' => 'tab', 'url' => '', 'extend' => 'none', 'remark' => '', 'weigh' => 8],
                    ['type' => 'button', 'title' => '删除', 'name' => 'live/room/del', 'path' => '', 'icon' => '', 'component' => '', 'menu_type' => 'tab', 'url' => '', 'extend' => 'none', 'remark' => '', 'weigh' => 7],
                    ['type' => 'button', 'title' => '选择', 'name' => 'live/room/select', 'path' => '', 'icon' => '', 'component' => '', 'menu_type' => 'tab', 'url' => '', 'extend' => 'none', 'remark' => '', 'weigh' => 6],
                ],
            ],
        ],
    ],
];

try {
    Menu::create($menu, 0, 'cover', 'backend');
    echo "Live admin menus seeded.\n";
} catch (\Throwable $e) {
    echo 'Seed failed: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
