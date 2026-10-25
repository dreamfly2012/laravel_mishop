<?php

namespace App\Http\Controllers\Home;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Good;
use App\Models\Sku;

class CartController extends Controller
{
    //存储返回的数据信息
    public  $data = [];
    /**
     * session('cart') 是一个多维数组 键名 对应的是sku-id
     * sku-id对应的键值是一个数组 0=>代表sku-id
     * sku-id对应的键值是一个数组 0=>代表购物车商品数量
     * sku-id对应的键值是一个数组 0=>代表商品的good-id
     */
    public function index()
    {
        //获取用户id
        // session(['uid'=>1]);
        //判断用户是否登录
        $uid = session('uid');

        $listItem = '';
        $totalPrice = 0;

        if(!empty($uid)){//已经登录
            if(!empty(session('cart'))){
                //判断session中是否存在cart 存在就存放在数据库中
                foreach(session('cart') as $k=>$v){
                    
                    //查看购物车数据中是否存在该商品
                    $cart = Cart::where('user_id',$uid)->where('sku_id',$k)->first();
                    //处理不存在该商品的情况
                    if(empty($cart)){
                        //查询sku表信息
                        $sku = Sku::find($k);
                        $info = new Cart;
                        $info->user_id = $uid;
                        $info->sku_id =  $v[0];
                        $info->goods_id = $sku->good_id;
                        $info->goods_name = $sku->title;
                        $info->goods_attr = $sku->color;
                        $info->goods_num = $v[1];
                        if(!$info->save()){
                            return response('数据库出错', 500);
                        }
                    }else{
                        $cart->goods_num = $cart->goods_num + (int)($v[1]);
                        if(!$cart->save()){
                            return response('数据库出错', 500);
                        }
                    }
                }
            }
            //清空session中的购物车信息
            session(['cart'=>null]);
            //获取数据库中购物车的信息
            // $carts = Cart::where('user_id',$uid)->get();
            $carts = \DB::table('carts as a')
                    ->select(\DB::raw('a.id as cid,a.sku_id as sid,a.goods_id as gid,a.goods_num as num ,b.img as img,b.title as title,b.price as price,b.attr as attr'))
                    ->where('a.user_id',$uid)
                    ->leftJoin('skus as b','a.sku_id','=','b.id')
                    ->get();
            
            //获取购物车中商品的id
 
            foreach($carts as $k=>$v){
                $listItem .= $v->sid.',';
                $totalPrice += $v->num*$v->price;
            }
            $listItem = trim($listItem,',');
             
        }else{//未登录
            //判断购物车session中是否存在信息
            $carts = [];

            if(count(session('cart') ?? [])>0){
                //查询商品的信息                
                foreach(session('cart') as $k=>$v){
                    $sku = Sku::where('id',$v[0])->first();
                    $carts[] = $sku;
                }
                //获取购物车中商品的id
                foreach($carts as $k=>$v){
                    $listItem .= $v->id.',';
                    
                    $totalPrice += session('cart')[$v->id][1]* (int)($v->price);

                }
                
                $listItem = trim($listItem,',');
                
            }

        }

        
        
        //获取商品推荐
        $goods = $this->gainRecomGoods();
        
        return view('home.cart',[
                'carts'=>$carts,
                'goods'=>$goods,
                'listItem'=>$listItem,
                'totalPrice'=>$totalPrice,
            ]);
    }
    
    /**
     * 获取推荐商品
     */
    private function gainRecomGoods()
    {
        //获取评论最多商品id?????

        $goods = Good::take(10)->get();

        return $goods;
    }

    /**
     * 获取商品信息
     */
    public function getAddproduct(Request $request)
    {
        //获取用户id
        $uid = session('uid');

        // 新地址 /cart/add/{id}; 旧地址 /cart/addproduct?id=xx 仍可用
        $skuId = $request->route('id') ?? $request->input('id');

        //查找商品
        $sku = Sku::select('stock','id','price')->find($skuId);
        // 数量校验: 必须为 1~库存 的整数
        $num = (int)$request->input('num');
        if($num < 1) $num = 1;
        //判断是超过库存
        if($sku->stock < $num){
            $this->data['status'] = 1;//超过最大库存
            $this->data['msg'] = '已超过最大库存量'.$sku->stock.'件';
            $this->data['total'] = $sku->price;//价格小计
            $this->data['price'] = $sku->price;//当前价格
            $this->data['stock'] = $sku->stock;//当前价格
            return response()->json($this->data);
        }
        //判断是否是未登录状态
        if(empty($uid)){
            //增加购物车商品数量

            $request->session()->forget('cart.'.($sku->id));
      
            $request->session()->push('cart.'.($sku->id), $sku->id);//0 代表 id
            $request->session()->push('cart.'.($sku->id), $request->input('num'));//1 代表 num
            $request->session()->push('cart.'.($sku->id), $sku->good_id);//2 代表 gid good_id
           
            //保存数据更改            
            \session()->save();
            
                
            //判断 该商品是否被选中 选中就更新总价
            $skus = $request->input('listItem');
            // 
            $alltotal = 0;
            if($skus&&in_array($skuId,$skus)){
                
                return $this->totalPriceBySession($skus,$alltotal,$sku,$request);

            }else if(empty($request->input('listItem'))){//未全选状态处理
                $this->data['nofollow'] = 'none';
                $this->data['alltotal'] = number_format(0,2);
            }else{
                return $this->totalPriceBySession($skus,$alltotal,$sku,$request);
            }

                //成功返回的信息
                $this->data['status'] = 0;
                $this->data['total'] = number_format($sku->price*$request->input('num'),2);//价格小计
                $this->data['price'] = number_format($sku->price,2);//当前价格
                $this->data['msg'] = 'ok';
                return response()->json($this->data);
           
        }


        //跟新购物车表 (不存在则新建, 避免空指针)
        $cart = Cart::where('user_id',$uid)->where('sku_id',$skuId)->first();
        if(empty($cart)){
            $cart = new Cart;
            $cart->user_id = $uid;
            $cart->sku_id = $skuId;
            $cart->goods_id = $sku->good_id;
            $cart->goods_name = $sku->title;
            $cart->goods_attr = $sku->color;
            $cart->goods_num = 0;
        }
        $cart->goods_num = $num;
        if($cart->save()){
            //判断 该商品是否被选中 选中就更新总价
            $skus = $request->input('listItem');
            $alltotal = 0;
            if($skus&&in_array($skuId,$skus)){
                //计算总价
                return $this->totalPriceByDB($uid,$skus,$alltotal,$sku,$cart,$request);

            }else if(empty($request->input('listItem'))){//未全选状态处理
                $this->data['nofollow'] = 'none';;
                $this->data['alltotal'] = number_format(0,2);
            }else{
                //计算总价
                return $this->totalPriceByDB($uid,$skus,$alltotal,$sku,$cart,$request);
            }

            $this->data['status'] = 0;
            $this->data['total'] = number_format($sku->price*$request->input('num'),2);//价格小计
            $this->data['price'] = number_format($sku->price,2);//当前价格
            $this->data['msg'] = 'ok';
            return response()->json($this->data);
        }else{
            $this->data['status'] = 4;
            $this->data['price'] = number_format($sku->price,2);//当前价格
            $this->data['total'] = number_format($sku->price*$cart->goods_num,2);//价格小计
            $this->data['msg'] = '数据库出错';
            return response()->json($this->data);
        }

    }

    /**
     * 通过session获取购物车选择商品总价
     */
    private function totalPriceBySession($skus,$alltotal,$sku,$request)
    {
        foreach($skus as $k=>$v){
              
            $sku_2 = Sku::where('id',$v)->first();

            
            if(!empty($sku_2)){

                $alltotal += session('cart')[$v][1]*$sku_2->price;
               
            }else{
                $this->data['status'] = 2;
                $this->data['msg'] = '查不到商品信息';
                $this->data['price'] = $sku->price;//当前价格单价
                $this->data['total'] = $sku->price*$request->input('num');
                return response()->json($this->data);
            }
            
        }
        
        //计算总价
        if(!empty($alltotal)&&is_numeric($alltotal)){
            $this->data['status'] = 0;
            $this->data['msg'] = 'ok';
            $this->data['price'] = number_format($sku->price,2);//当前价格
            $this->data['total'] = number_format($sku->price*$request->input('num'),2);//价格小计
            $this->data['alltotal'] = number_format($alltotal,2);
            return response()->json($this->data);
        }else{
            $this->data['status'] = 3;
            $this->data['msg'] = '未知错误';
            $this->data['price'] = number_format($sku->price,2);//当前价格
            $this->data['total'] = number_format($sku->price*$request->input('num'),2);//价格小计
            $this->data['alltotal'] = '未知错误,请刷新页面';
            return response()->json($this->data);
        }
    }

    /**
     * 通过数据库获取购物车选择商品总价
     */
    private function totalPriceByDB($uid,$skus,$alltotal,$sku,$cart,$request)
    {

        foreach($skus as $k=>$v){
            $cart2 = Cart::where('user_id',$uid)->where('sku_id',$v)->first();
            if(!empty($cart2)){
                $alltotal += $cart2->goods_num*$cart2->sku->price;
            }else{
                $this->data['status'] = 2;
                $this->data['msg'] = '查不到商品信息';
                $this->data['price'] = number_format($sku->price,2);//当前价格单价
                $this->data['total'] = number_format($sku->price*$request->input('num'),2);
                return response()->json($this->data);
            }
            
        }
        //计算总价
        if(!empty($alltotal)&&is_numeric($alltotal)){
            $this->data['status'] = 0;
            $this->data['msg'] = 'ok';
            $this->data['price'] = number_format($cart->sku->price,2);//当前价格
            $this->data['total'] = number_format($sku->price*$request->input('num'),2);//价格小计
            $this->data['alltotal'] = number_format($alltotal,2);
            return response()->json($this->data);
        }else{
            $this->data['status'] = 3;
            $this->data['msg'] = '未知错误';
            $this->data['price'] = number_format($cart->sku->price,2);//当前价格
            $this->data['total'] = number_format($sku->price*$request->input('num'),2);//价格小计
            $this->data['alltotal'] = '未知错误,请刷新页面';
            return response()->json($this->data);
        }

    }
    public function getAllTotal(Request $request)
    {
        $uid = session('uid');
        $skus = $request->input('listItem');
        $alltotal = 0;
        if(empty($uid)){

            foreach($skus as $k=>$v){
          
                $sku_2 = Sku::where('id',$v)->first();

                
                if(!empty($sku_2)){

                    $alltotal += session('cart')[$v][1]*$sku_2->price;
                   
                }else{
                    $this->data['status'] = 2;
                    $this->data['msg'] = '查不到商品信息';
                    return response()->json($this->data);
                }
                
            }
            
            //计算总价
            if(!empty($alltotal)&&is_numeric($alltotal)){
                $this->data['status'] = 0;
                $this->data['msg'] = 'ok';
                $this->data['price'] = number_format($sku->price,2);//当前价格
                $this->data['total'] = number_format($sku->price*$request->input('num'),2);//价格小计
                $this->data['alltotal'] = number_format($alltotal,2);
                return response()->json($this->data);
            }else{
                $this->data['status'] = 3;
                $this->data['msg'] = '未知错误';
                $this->data['price'] = number_format($sku->price,2);//当前价格
                $this->data['total'] = number_format($sku->price*$request->input('num'),2);//价格小计
                $this->data['alltotal'] = '未知错误,请刷新页面';
                return response()->json($this->data);
            }
        }
        //登录状态下查询
        foreach($skus as $k=>$v){
            $cart2 = Cart::where('user_id',$uid)->where('sku_id',$v)->first();
            if(!empty($cart2)){
                $alltotal += $cart2->goods_num*$cart2->sku->price;
            }else{
                $this->data['status'] = 2;
                $this->data['msg'] = '查不到商品信息';
                $this->data['price'] = number_format($sku->price,2);//当前价格单价
                $this->data['total'] = number_format($sku->price*$request->input('num'),2);
                return response()->json($this->data);
            }
            
        }
        //计算总价
        if(!empty($alltotal)&&is_numeric($alltotal)){
            $this->data['status'] = 0;
            $this->data['msg'] = 'ok';
            $this->data['price'] = number_format($cart->sku->price,2);//当前价格
            $this->data['total'] = number_format($sku->price*$request->input('num'),2);//价格小计
            $this->data['alltotal'] = number_format($alltotal,2);
            return response()->json($this->data);
        }else{
            $this->data['status'] = 3;
            $this->data['msg'] = '未知错误';
            $this->data['price'] = number_format($cart->sku->price,2);//当前价格
            $this->data['total'] = number_format($sku->price*$request->input('num'),2);//价格小计
            $this->data['alltotal'] = '未知错误,请刷新页面';
            return response()->json($this->data);
        }

    }

    public function getDelcart(Request $request)
    {
        // 新地址 /cart/delete/{id}; 旧地址 /cart/delcart?id=xx 仍可用
        $skuId = $request->route('id') ?? $request->input('id');

        //删除指定项listItem
        $listItem = $request->input('listItem');
        if(in_array($skuId,$listItem)){
            $key = array_search($skuId,$listItem);
            unset($listItem[$key]);

        }
        $carts = Sku::whereIn('id',$listItem)->get();
        $alltotal = 0;
        foreach($carts as $k=>$v){
            $alltotal += $v->price * session('cart.'.$v->id)[1];
        }

        if(!empty(session('cart')))
        {

            $request->session()->forget('cart.'.$skuId);
            // session(['cart'.$skuId=>null]);
            \session()->save();
            $this->data['status'] = 0;
            $this->data['msg'] = 'ok';
            $this->data['alltotal'] = $alltotal;
            return response()->json($this->data);
        }

        // $skuid = $skuId;
        // $cart = Cart::where('user_id',session('uid'))->where('sku_id',$skuid)->first();
        $cart = Cart::find($skuId);
       
        if(empty($cart)){
            $this->data['status'] = 1;
            $this->data['msg'] = '查无该产品';
            return response()->json($this->data);
        }
        if($cart->delete()){
            $this->data['status'] = 0;
            $this->data['msg'] = 'ok';
            $this->data['alltotal'] = $alltotal;
            return response()->json($this->data);
        }else{
            $this->data['status'] = 2;
            $this->data['msg'] = '删除失败';
            return response()->json($this->data);
        }
    }

    /**
     * 当前购物车里的商品总件数 (游客查 session, 已登录查 carts 表)
     * 供顶栏购物车角标使用
     */
    public static function cartCount()
    {
        $uid = session('uid');
        if (!empty($uid)) {
            return (int)Cart::where('user_id', $uid)->sum('goods_num');
        }
        $count = 0;
        foreach ((array)session('cart') as $item) {
            $count += (int)($item[1] ?? 0);
        }
        return $count;
    }

    /**
     * 加入购物车操作
     * 参数: good_id 必传; num 可选默认 1;
     *       sku_price/sku_color/sku_attr 详情页传, 用于精确定位 SKU;
     *       列表页只传 good_id, 自动取该商品第一个 SKU
     */
    public function ajaxAddCart(Request $request)
    {

        //判断是否登录
        $uid = session('uid');
        $goodId = (int)$request->input('good_id');
        $num = (int)$request->input('num') ?: 1;

        if ($goodId <= 0) {
            return response()->json(['status' => 2, 'msg' => '缺少商品参数']);
        }

        //定位 SKU:
        // - 详情页表单始终提交 sku_price/sku_color/sku_attr (含空串), 三者选全才允许加购;
        //   选全后精确匹配, 匹配不到说明规格已下架;
        // - 列表页/搜索页无规格选择, 只传 good_id, 自动取该商品第一个 SKU。
        $isDetail = $request->hasAny(['sku_price', 'sku_color', 'sku_attr']);

        if ($isDetail) {
            $wheres = [
                'price' => trim((string)$request->input('sku_price')),
                'color' => trim((string)$request->input('sku_color')),
                'attr'  => trim((string)$request->input('sku_attr')),
            ];
            if (in_array('', $wheres, true)) {
                return response()->json(['status' => 3, 'msg' => '请先选择商品的版本/颜色']);
            }
            $sku = Sku::where('good_id', $goodId)
                ->where('price', $wheres['price'])
                ->where('color', $wheres['color'])
                ->where('attr', $wheres['attr'])
                ->first();
            if (empty($sku)) {
                return response()->json(['status' => 3, 'msg' => '该商品规格已下架, 请刷新页面重新选择']);
            }
        } else {
            $sku = Sku::where('good_id', $goodId)->orderBy('id', 'asc')->first();
            if (empty($sku)) {
                return response()->json(['status' => 3, 'msg' => '查无该商品信息']);
            }
        }

        //购物车里该 SKU 已有数量 (游客在 session, 已登录在数据库)
        $inCart = 0;
        $cart = null;
        if (!empty($uid)) {
            $cart = Cart::where('user_id', $uid)->where('sku_id', $sku->id)->first();
            if (!empty($cart)) {
                $inCart = (int)$cart->goods_num;
            }
        } else {
            $sessionCart = (array)session('cart');
            $inCart = isset($sessionCart[$sku->id][1]) ? (int)$sessionCart[$sku->id][1] : 0;
        }

        //库存校验: 已有数量 + 本次数量不能超过库存
        if ($inCart + $num > (int)$sku->stock) {
            return response()->json([
                'status' => 1,
                'msg'    => '已超过最大库存量' . $sku->stock . '件',
                'count'  => self::cartCount(),
            ]);
        }

        //未登录情况将购物车信息加入到session中
        if (empty($uid)) {
            session([
                'cart.' . $sku->id => [$sku->id, $inCart + $num, $sku->good_id],//0 代表 id, 1 代表 num, 2 代表 gid
            ]);
            //保存修改过的session值
            session()->save();
            return response()->json([
                'status' => 0,
                'msg'    => 'ok',
                'count'  => self::cartCount(),
            ]);
        }

        //已经登录 将数据插入到数据库中
        if (empty($cart)) {
            $cart = new Cart;
            $cart->user_id = $uid;
            $cart->sku_id = $sku->id;
            $cart->goods_id = $sku->good_id;
            $cart->goods_name = $sku->title;
            $cart->goods_attr = $sku->color;
        }
        $cart->goods_num = $inCart + $num;
        if ($cart->save()) {
            return response()->json([
                'status' => 0,
                'msg'    => 'ok',
                'count'  => self::cartCount(),
            ]);
        }
        return response()->json([
            'status' => 1,
            'msg'    => '操作失败,请重试',
            'count'  => self::cartCount(),
        ]);
    }
}
