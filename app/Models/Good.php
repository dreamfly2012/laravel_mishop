<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class Good extends Model
{
    use Searchable;

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

    /**
     * 索引字段：商品标题、副标题、分类名、正文（去标签）。
     * Meilisearch 内置 CJK 处理，可做到中文部分匹配与 typo 容错。
     */
    public function toSearchableArray(): array
    {
        return [
            'title'        => $this->title,
            'sub_title'    => $this->sub_title,
            'category'     => optional($this->cate)->name,
            'content'      => strip_tags((string) $this->content),
        ];
    }

    /**
     * 本地无 Meilisearch 时，用 Scout database 驱动 / 下方 List_Search 的 DB 回退兜底。
     */
    public function searchableAs(): string
    {
        return 'goods';
    }
}
