<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    //
    public function orderGoods()
    {
        return $this->hasMany('App\Models\OrderGoods');
    }

    public function user()
    {
        return $this->belongsTo('App\Models\User');
    } 
}
