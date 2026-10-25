<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    protected $fillable = [
        'position', 'title', 'image', 'icon', 'good_id', 'url', 'sort', 'status',
    ];

    public $timestamps = true;

    /**
     * 跳转地址：优先关联商品 /detail/{good_id}，其次自定义 url，都没有则回首页。
     */
    public function link(): string
    {
        if ($this->good_id) {
            return '/detail/'.(int) $this->good_id;
        }
        if ($this->url) {
            return $this->url;
        }
        return '/';
    }

    public function scopePosition($query, string $position)
    {
        return $query->where('position', $position);
    }
}
