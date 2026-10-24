<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sku extends Model
{
    public function good()
    {
        return $this->belongsTo('App\Models\Good');
    }
}
