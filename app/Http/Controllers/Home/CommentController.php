<?php

namespace App\Http\Controllers\Home;

use App\Models\Comment;
use App\Models\Sku;
use App\Models\Order;
use App\Models\OrderGoods;
use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Session;

class CommentController extends Controller
{
    public function comment(Request $request)
    {
        // 新地址 /comment/{id}?order_id=xx; 旧地址 /comment?id=xx 301 到新格式
        $id = $request->route('id');

        if (is_null($id) && $request->filled('id')) {
            $query = $request->filled('order_id') ? '?order_id='.(int)$request->input('order_id') : '';
            return redirect('/comment/'.(int)$request->input('id').$query, 301);
        }

        $sku = Sku::find($id);

        $order_id = $request->input('order_id');

        return view('home.comment',['sku'=>$sku,'order_id'=>$order_id]);
    }

    public function insert(Request $request)
    {
        $uid = session('uid');
        $data = $request->all();

        $score = (float)($data['score'] ?? 0);
        $score = max(0, min(10, $score));
        $content = trim((string)($data['content'] ?? ''));
        $goodId = (int)($data['good_id'] ?? 0);
        $orderId = (int)($data['order_id'] ?? 0);

        if($content === '' || $goodId <= 0 || $orderId <= 0){
            return back()->with('error','评价参数不完整');
        }
        if(mb_strlen($content) > 1000){
            return back()->with('error','评价内容过长');
        }

        // 订单必须属于当前用户且已收货/完成
        $order = Order::where('user_id',$uid)->whereIn('order_status',[3,7])->find($orderId);
        if(!$order){
            return back()->with('error','只能评价本人已收货的订单');
        }
        // 商品必须确实在该订单内
        $inOrder = OrderGoods::where('order_id',$orderId)
            ->whereIn('sku_id', function($q) use ($goodId){
                $q->select('id')->from('skus')->where('good_id',$goodId)->limit(1);
            })->exists();
        if(!$inOrder){
            return back()->with('error','该商品不在订单内');
        }
        // 同一订单同一商品只允许评价一次
        if(Comment::where('user_id',$uid)->where('good_id',$goodId)->where('useless',$orderId)->exists()){
            return back()->with('error','该订单商品已评价');
        }

        $comment = new Comment();
        $comment->star = $score;
        $comment->content = $content;
        $comment->good_id = $goodId;
        $comment->useless = $orderId;
        $comment->user_id = $uid;
        $comment->save();

        return redirect('/detail/'.$goodId.'#comment');
    }
}
