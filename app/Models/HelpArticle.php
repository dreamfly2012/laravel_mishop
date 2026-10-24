<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HelpArticle extends Model
{
    //
    public function helpCate()
    {
        return $this->belongsTo('App\Models\HelpCate');
    } 
}
