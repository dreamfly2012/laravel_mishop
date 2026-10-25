<?php

namespace App\Http\Controllers\Home;

use App\Models\Cate;
use App\Models\Good;
use App\Models\Sku;
use App\Models\Collect;
use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;

class ListController extends Controller
{

    public function detail(Request $request)
    {

        // 新地址 /detail/{id}; 兼容旧地址 /detail?id=xx 并 301 到新格式
        $goodId = $request->route('id');

        if (is_null($goodId) && $request->filled('id')) {
            return redirect('/detail/'.(int)$request->input('id'), 301);
        }

        $good = Good::find($goodId);

        if(!$good){
            return redirect('/');
        }
        $good->img = explode(',',$good->img);

        $skus = $good->skus ;

        $arr = [];
        foreach ($skus as $key => $value) {
            $arr[] = $value->attr;
        }

        $attr = array_unique($arr);

        $color = [];
        $info = [];

        $i = 0;
        foreach ($attr as $key => $value) {

            $info[$value] = Sku::where('attr',$value)->first()->info;

            foreach (Sku::where('attr',$value)->get() as $k => $v ){

                $color[$value][$k] = $v->color;
            }
            $i++;
        }

        $good->attr = $color;
        $good->info = $info;

        // 当前用户对该商品的收藏状态 (未登录视为未收藏)
        $uid = session('uid');
        $isCollected = $uid
            ? Collect::where('user_id', $uid)->where('good_id', $good->id)->exists()
            : false;

        return view('home.detail', ['good' => $good, 'title' => '详情', 'isCollected' => $isCollected]);
    }


    
    public function list_(Request $request)
    {
        // 新地址 /list/{id}; 兼容旧地址 /list?id=xx 并 301 到新格式
        $cateId = $request->route('id');

        if (is_null($cateId) && $request->filled('id')) {
            return redirect('/list/'.(int)$request->input('id'), 301);
        }

        //目标分类自身 + 其子类 下所有商品 (侧边栏点的是最末级分类)
        $cateIds = Cate::where('pid', $cateId)->get()->pluck('id')->all();
        if (!is_null($cateId)) {
            $cateIds[] = (int)$cateId;
        }

        // 排序：销量(sales_volume) / 价格(price) / 上架时间(new)；goods 表无销量字段，
        // 销量按「该商品下所有 SKU 在订单中的销量合计」实时计算。
        $sort  = (string) $request->input('sort', 'sales_volume');
        $order = strtoupper((string) $request->input('order', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $query = $cateIds
            ? Good::whereIn('cate_id', $cateIds)
            : Good::query();

        switch ($sort) {
            case 'price':
                $query->orderBy('price', $order);
                break;
            case 'new':
                $query->orderBy('created_at', $order);
                break;
            case 'sales_volume':
            default:
                $query->orderByRaw(
                    '(SELECT COALESCE(SUM(og.num), 0) FROM order_goods og JOIN skus s ON s.id = og.sku_id WHERE s.good_id = goods.id) '.$order
                );
                break;
        }

        $goods = $query->get();

        foreach ($goods as $key => $value) {
            $sub_title = sub_title();
            $value->sub_title =$sub_title;
        }

        // 当前用户已收藏的商品id集合 (未登录为空数组)
        $uid = session('uid');
        $collectedGoods = $uid
            ? Collect::where('user_id', $uid)->pluck('good_id')->all()
            : [];


        return view('home.list', ['goods' => $goods, 'title' => '小米商城', 'collectedGoods' => $collectedGoods, 'sort' => $sort, 'order' => $order]);
    }

    public function List_Search(Request $request)
    {
        // 搜索词保留 query (?kWord=)
        $kWord = (string) $request->input('kWord', '');

        $cates = Cate::where('pid',0)->get();
        $goods = Good::take('10')->orderBy('created_at','desc')->get();

        if ($kWord === '') {
            return view('home.list_search', [
                'cates' => $cates,
                'search_goods' => $goods,
                'goods' => $goods,
                'title' => '小米商城',
                'kWord' => '',
            ]);
        }

        $search_goods = $this->searchGoods($kWord);

        return view('home.list_search', [
            'cates' => $cates,
            'search_goods' => $search_goods,
            'goods' => $goods,
            'title' => '小米商城',
            'kWord' => $kWord,
        ]);
    }

    /**
     * 商品搜索：Meilisearch 等搜索引擎已配置且可用时走 Scout（中文部分匹配 + typo 容错）；
     * 否则 / 搜索引擎不可达时退回到数据库模糊查询，保证 demo 始终可用。
     */
    protected function searchGoods(string $kWord)
    {
        $driver = config('scout.driver');

        if (in_array($driver, ['meilisearch', 'algolia'], true)) {
            try {
                return Good::search($kWord)->get();
            } catch (Throwable $e) {
                // 搜索引擎不可达，退回数据库查询
            }
        }

        return Good::where('title', 'like', '%' . $kWord . '%')
            ->orWhere('sub_title', 'like', '%' . $kWord . '%')
            ->orWhereHas('cate', function ($q) use ($kWord) {
                $q->where('name', 'like', '%' . $kWord . '%');
            })
            ->get();
    }

    
}
