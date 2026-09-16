<?php

declare(strict_types=1);

namespace app\admin\model\live;

use think\Model;

final class FaceSwapTemplate extends Model
{
    protected $connection = 'live_mysql';
    protected $name = 'lp_face_swap_template';
    protected $pk = 'id';
    protected $autoWriteTimestamp = false;
}
