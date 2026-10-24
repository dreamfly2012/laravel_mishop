<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Collect extends Model
{
    protected $table = 'collects';

    protected $fillable = ['user_id', 'good_id'];

    public function user()
    {
        return $this->belongsTo('App\Models\User');
    }

    public function good()
    {
        return $this->belongsTo('App\Models\Good');
    }
}
