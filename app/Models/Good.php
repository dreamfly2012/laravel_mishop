<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Good extends Model
{
    public function cate()
    {
        return $this->belongsTo('App\Models\Cate');
    }
    public function skus()
    {
        return $this->hasMany('App\Models\Sku');
    }

    public function comments()
    {
        return $this->hasMany('App\Models\Comment');
    }
}
