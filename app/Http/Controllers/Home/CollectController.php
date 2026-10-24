<?php

namespace App\Http\Controllers\Home;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Models\Collect;
use App\Models\Good;

class CollectController extends Controller
{
    /**
     * 商品收藏 (原 ECShop 遗留 user.php?act=collect 的对应接口)
     * 未登录不挂 home 中间件, 由本方法返回 JSON 让前端提示跳登录。
     */
    public function getIndex(Request $request)
    {
        // 未登录: 提示去登录
        $uid = session('uid');
        if (!$uid) {
            return response()->json([
                'status'   => 1,
                'info'     => '尚未登录, 请先登录',
                'redirect' => '/login',
            ]);
        }

        $goodId = (int) $request->input('id');
        $good = Good::where('status', 1)->find($goodId);
        if (!$good) {
            return response()->json(['status' => 2, 'info' => '商品不存在']);
        }

        // 已收藏过则取消 (收藏/取消切换)
        $collect = Collect::where('user_id', $uid)->where('good_id', $goodId)->first();
        if ($collect) {
            $collect->delete();

            return response()->json(['status' => 0, 'info' => '已取消收藏']);
        }

        $collect = new Collect();
        $collect->user_id = $uid;
        $collect->good_id = $goodId;
        $collect->save();

        return response()->json(['status' => 0, 'info' => '收藏成功']);
    }
}
