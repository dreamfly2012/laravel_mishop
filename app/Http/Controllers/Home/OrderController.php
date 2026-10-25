<?php

namespace App\Http\Controllers\Home;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Home\AddressController;

use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderGoods;
use App\Models\Address;

class OrderController extends Controller
{
    //存储ajax信息
    private $data = [];
    private $allTotal = 0;
    private $num = 0;

    // 支持的支付方式（本地沙箱收银台用；真实接入时在此映射第三方渠道）
    private const PAY_METHODS = [
        'alipay' => '支付宝',
        'wechat' => '微信支付',
        'mipay'  => '小米钱包',
    ];
    /**
     * 显示订单确认页
     */
    public function confirm()
    {   
        //获取用户id
        $uid = session('uid');
        $address = AddressController::getAddressByUid($uid);
        //获取购物商品的skuid
        $listItem = session('listItem') ?: [];
 
        //获取购物车信息
        $carts = Cart::where('user_id',$uid)->whereIn('sku_id',$listItem)->get();
      
        //获取总件数 总价格
        $num = 0;
        $allTotal = 0;
        foreach($carts as $k=>$v){
            $num += $v->goods_num;
            $allTotal += $v->goods_num*$v->sku->price;
        }

        $pack_fee = 10;
        if($allTotal>99){
            $pack_fee = 0;
        }
        return view('home.order.confirm',[
                'address'=>$address,
                'carts'=>$carts,
                'num'=>$num,
                'allTotal'=>$allTotal,
                'pack_fee'=>$pack_fee,
            ]);

    }

    /**
     * ajax处理跳转到订单页 (购物车跳转)
     */
    public function postConfirm(Request $request)
    {
        $uid = session('uid');
        if(empty($uid)){
            $this->data['status'] = 2;
            $this->data['msg'] = '请登录';
            return response()->json($this->data);
        }
        $listItem = $request->input('listItem');

        //将订单商品信息存入到session中
        if(empty($listItem)){
            $this->data['status'] = 4;
            $this->data['msg'] = '购物车为空';
            return response()->json($this->data);
        }
        session(['listItem'=>$listItem]);
        \session()->save();

        //跳转去订单确认页面
        $this->data['status'] = 0;
        $this->data['msg'] = '/order/confirm';
        return response()->json($this->data);
    }
    /**
     * 生成订单 (确认订单跳转到订单支付页)
     * 下单 = 预扣库存 + 写订单 + 写订单商品 + 清购物车, 全部在一个事务里,
     * 任一步失败(含库存不足/并发超卖)整体回滚, 不会留下半个订单。
     */
    public function postCreate(Request $request)
    {
        $uid = session('uid');
        $listItem = session('listItem') ?: [];

        //获取购物车信息
        $carts = Cart::where('user_id',$uid)->whereIn('sku_id',$listItem)->get();

        if($carts->isEmpty()){
            $this->data['status'] = 4;
            $this->data['msg'] = '购物车为空';
            return response()->json($this->data);
        }

        // 金额一律按 sku 现价在服务端重算, 不信任前端提交的 totalPrice / packFee
        $totalPrice = 0;
        foreach($carts as $cart){
            if(!$cart->sku){
                $this->data['status'] = 1;
                $this->data['msg'] = '服务异常,请稍后再试';
                return response()->json($this->data);
            }
            $totalPrice += $cart->goods_num * $cart->sku->price;
        }
        $packFee = $totalPrice > 99 ? 0 : 10;

        //通过地址id添加下列信息 (地址必须属于当前用户, 提前校验避免开了事务再回滚)
        $address = Address::where('user_id',$uid)->find($request->input('addressId'));
        if(empty($address)){
            $this->data['status'] = 1;
            $this->data['msg'] = '服务异常,请稍后再试';
            return response()->json($this->data);
        }

        // 事务: 闭包内任一步抛异常自动整体回滚
        try{
            $orderId = \DB::transaction(function() use($carts,$uid,$packFee,$totalPrice,$address){

                // 1. 预扣库存: 条件 UPDATE (stock >= num) 保证并发下不超卖,
                //    影响行数为 0 即库存不足, 抛异常回滚整个订单
                foreach($carts as $v){
                    $num = (int)$v->goods_num;
                    // stock + 0 强制按数值比较 (库中 stock 是 varchar,
                    // 直接 >= 在 SQLite 下会退化成字符串比较)
                    $affected = \DB::table('skus')
                        ->where('id',$v->sku_id)
                        ->whereRaw('stock + 0 >= ?', [$num])
                        ->decrement('stock',$num);
                    if(!$affected){
                        throw new \RuntimeException('商品「'.$v->goods_name.'」库存不足,请调整数量');
                    }
                }

                // 2. 生成订单信息插入到数据库中
                $order = new Order;
                $order->user_id = $uid;
                $order->order_status = 0;//初始化订单
                $order->pay_status = 0;//未支付
                $order->order_num = $this->makeOrderNum();//订单编号
                $order->pack_fee = $packFee;//运费
                $order->pack_time = 0;
                $order->total_price = $totalPrice;//总价
                $order->consignee = $address->consignee;
                $order->province = $address->province;
                $order->city = $address->city;
                $order->district = $address->district;
                $order->address = $address->address;
                $order->phone = $address->tel;
                if(!$order->save()){
                    throw new \RuntimeException('订单创建失败,请稍后再试');
                }

                // 3. 将商品信息插入到order_goods数据库中
                foreach($carts as $v){
                    $orderGoods = new OrderGoods;
                    $orderGoods->order_id = $order->id;
                    $orderGoods->sku_id = $v->sku_id;
                    $orderGoods->price = $v->sku->price;
                    $orderGoods->num = $v->goods_num;
                    $orderGoods->goods_name = $v->goods_name;
                    $orderGoods->goods_attr = $v->goods_attr;
                    $orderGoods->color = $v->sku->color;
                    if(!$orderGoods->save()){
                        throw new \RuntimeException('订单商品写入失败,请稍后再试');
                    }
                }

                // 4. 清空购物车中已下单的条目
                \DB::table('carts')
                    ->where('user_id',$uid)
                    ->whereIn('sku_id',$carts->pluck('sku_id')->all())
                    ->delete();

                return $order->id;
            });
        }catch(\RuntimeException $e){
            // 库存不足等业务失败: 事务已回滚, 订单/扣减都不会留下
            $this->data['status'] = 5;
            $this->data['msg'] = $e->getMessage();
            return response()->json($this->data);
        }

        //清除session存储的订单sku id信息
        session(['listItem'=>null]);
        $this->data['status'] = 0;
        $this->data['msg'] = $orderId;
        return response()->json($this->data);
    }

    public function getPay(Request $request)
    {
        // 新地址 /order/pay/{id}; 旧地址 /order/pay?id=xx 301 到新格式
        $id = $request->route('id');

        if (is_null($id) && $request->filled('id')) {
            return redirect('/order/pay/'.(int)$request->input('id'), 301);
        }

        //查询订单数据库 (必须属于当前登录用户)
        $uid = session('uid');
        $order = Order::where('user_id',$uid)->where('order_status',0)->findOrFail($id);
        //查询
        $beginTime = strtotime($order->created_at);
        $endTime = $beginTime + (48*3600);
        $nowTime = time();
        $countTime = floor(($endTime-$nowTime)/3600).'小时'.floor(($endTime-$nowTime)/60%60).'分';

     
        $request->session()->put('order.id',$order->id);
        $request->session()->put('order.totalPrice',$order->total_price);

        return view('home.order.pay',[
                'order'=>$order,
                'countTime'=>$countTime,
            ]);
    }
    /**
     * 发起支付：选择支付方式后跳转到本地收银台（沙箱网关）。
     */
    public function getPayfor(Request $request)
    {
        $orderId = (int) $request->input('order_id');
        $method  = (string) $request->input('method');

        if (!array_key_exists($method, self::PAY_METHODS)) {
            return redirect('/order/pay/'.$orderId)->withErrors(['method' => '请选择有效的支付方式']);
        }

        // 订单必须属于当前登录用户且尚未支付
        Order::where('user_id', session('uid'))
            ->where('order_status', 0)
            ->findOrFail($orderId);

        // 真实支付网关此刻会带着订单号/金额/回调地址 302 跳转到第三方收银台；
        // 本地沙箱用自带收银台页面替代，方便调试且无需外部商户号。
        return redirect('/pay/gateway/'.$orderId.'?method='.$method);
    }

    /**
     * 本地收银台（沙箱网关）：模拟第三方支付的收银页面。
     * 真实环境下这里是支付宝/微信的收银台，用户付款后由网关回调 notify。
     */
    public function gateway(Request $request, $id)
    {
        $order = Order::where('user_id', session('uid'))
            ->where('order_status', 0)
            ->findOrFail($id);

        $method = (string) $request->input('method', 'alipay');
        if (!array_key_exists($method, self::PAY_METHODS)) {
            $method = 'alipay';
        }

        return view('home.order.gateway', [
            'order'      => $order,
            'method'     => $method,
            'methodName' => self::PAY_METHODS[$method],
            'goods'      => $order->orderGoods,
        ]);
    }

    /**
     * 支付结果回调（模拟网关 server-to-server 通知）。
     * 真实环境需校验网关签名/签名密钥；本地沙箱里收银台为第一方，
     * 这里改为校验「订单归属 + 金额未被篡改」后，用原子条件更新置为已支付。
     * 注意：网关回调可能重发/并发到达，「先查后写」会有竞态，
     * 所以用 UPDATE ... WHERE order_status=0 + 影响行数判断来保证只入账一次。
     */
    public function notify(Request $request, $id)
    {
        $order = Order::where('user_id', session('uid'))->findOrFail($id);

        // 防御：以库内金额为准，拒绝被篡改的金额（真实网关会回传并验签金额）
        $paidAmount = (float) $request->input('amount');
        if ($paidAmount > 0 && abs($paidAmount - (float) $order->total_price) > 0.001) {
            abort(400, '支付金额不一致');
        }

        // 原子条件更新: 仅「仍未支付」的订单会被置为已支付。
        // 并发/重复回调时只有一个请求能影响 1 行, 其余影响 0 行, 不会重复入账。
        $affected = Order::where('id', $order->id)
            ->where('order_status', 0)
            ->update(['order_status' => 1, 'pay_status' => 1]);

        if (!$affected && $order->pay_status != 1) {
            // 订单已取消/完成等非待支付状态, 不允许再支付
            abort(400, '订单状态异常,无法完成支付');
        }

        // 网关回调成功后跳回商户的同步返回地址（return url）
        return redirect('/order/change-status?order_id='.$order->id);
    }

    /**
     * 支付完成同步返回页（return url）。仅展示已支付订单的成功页，
     * 不再在此直接修改订单状态（那一步已由 notify 回调完成）。
     */
    public function changeStatus(Request $request)
    {
        $orderId = (int) $request->input('order_id');
        $order = Order::where('user_id', session('uid'))->findOrFail($orderId);

        if ($order->pay_status == 1) {
            session(['order' => null]);
            return view('home.order.success');
        }

        // 未支付（用户直接访问 / 取消支付）→ 回到支付页
        return redirect('/order/pay/'.$orderId);
    }

    /**
     * 生成唯一订单号 (同一秒内并发下单也尽量不碰撞)
     */
    protected function makeOrderNum()
    {
        do {
            $num = date('YmdHis').rand(100000,999999);
        } while (Order::where('order_num',$num)->exists());
        return $num;
    }

}
