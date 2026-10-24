<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderGoods extends Model
{
    //
    public function order()
    {
        return $this->belongsTo('App\Models\Order');
    }
    public function sku()
    {
        return $this->hasOne('App\Models\Sku','id','sku_id');
    }
}
