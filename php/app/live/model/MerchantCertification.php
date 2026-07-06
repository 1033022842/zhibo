<?php
declare(strict_types=1);

namespace app\live\model;

use think\Model;

class MerchantCertification extends Model
{
    protected $connection = 'live_mysql';
    protected $name = 'lp_merchant_certification';
    protected $pk   = 'id';
    protected $autoWriteTimestamp = false;

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
