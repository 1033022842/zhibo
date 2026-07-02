<?php

declare(strict_types=1);

namespace app\admin\model\live;

use think\Model;

final class Persona extends Model
{
    protected $connection = 'live_mysql';
    protected $name = 'lp_persona';
    protected $pk = 'id';
    protected $autoWriteTimestamp = false;

    protected $json = ['source_fields'];
    protected $jsonAssoc = true;

    public function user()
    {
        return $this->belongsTo(LiveUser::class, 'user_id', 'id');
    }
}
