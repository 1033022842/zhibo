<?php
declare(strict_types=1);

namespace app\admin\model\live;

use think\Model;

final class MerchantCertification extends Model
{
    protected $connection = 'live_mysql';
    protected $name = 'lp_merchant_certification';
    protected $pk = 'id';
    protected $autoWriteTimestamp = false;

    public function user()
    {
        return $this->belongsTo(\app\live\model\User::class, 'user_id');
    }
}
