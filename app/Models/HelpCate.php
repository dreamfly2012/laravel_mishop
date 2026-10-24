<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HelpCate extends Model
{
    //
    public function helpArticle()
    {
        return $this->hasMany('App\Models\HelpArticle','help_cate_id','id');
    }
}
