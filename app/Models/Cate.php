<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cate extends Model
{
    //类与商品为1对多关系
    public function goods()
    {

        return $this->hasMany('App\Models\Good');
    }
    //类与sku为远层1对多关系
    public function Skus()
    {
        return $this->hasManyThrough('App\Models\Sku','App\Models\Good');
    }
    
}
