@extends('layout.index')
@section('title','收银台')
@section('css')
    <link rel="shortcut icon" href="/cdn/s01.mifile.cn/favicon.ico" type="image/x-icon" />
    <link rel="icon" href="/cdn/s01.mifile.cn/favicon.ico" type="image/x-icon" />
    <link rel="stylesheet" href="/cdn/s01.mifile.cn/css/base.min.css" />
    <style>
        .cashier-wrap { max-width:720px; margin:40px auto; }
        .cashier-card { border:1px solid #e0e0e0; border-radius:8px; overflow:hidden; background:#fff; }
        .cashier-head { padding:24px 28px; background:#ff6700; color:#fff; }
        .cashier-head .tag { display:inline-block; background:rgba(255,255,255,.25); font-size:12px; padding:2px 8px; border-radius:3px; margin-bottom:8px; }
        .cashier-head h2 { margin:0; font-size:22px; }
        .cashier-head .hint { margin:6px 0 0; font-size:13px; opacity:.9; }
        .cashier-body { padding:20px 28px; }
        .cashier-body .row { display:flex; padding:10px 0; border-bottom:1px dashed #eee; font-size:15px; }
        .cashier-body .row .label { width:90px; color:#999; }
        .cashier-body .row.amount { border-bottom:0; margin-top:6px; }
        .cashier-body .row.amount .money { color:#ff6700; font-size:26px; font-weight:700; }
        .cashier-foot { padding:20px 28px; background:#fafafa; border-top:1px solid #eee; }
        .btn-pay { display:inline-block; background:#ff6700; color:#fff; border:0; padding:12px 40px; font-size:16px; border-radius:4px; cursor:pointer; }
        .btn-pay:hover { background:#ff4d00; }
        .btn-cancel { margin-left:18px; color:#666; text-decoration:none; }
        .btn-cancel:hover { color:#ff6700; }
    </style>
@endsection
@section('header')
    <div class="site-header site-mini-header">
        <div class="container">
            <div class="header-logo">
                <a class="logo " href="/" title="小米官网"></a>
            </div>
            <div class="header-title" id="J_miniHeaderTitle"><h2>收银台</h2></div>
        </div>
    </div>
@endsection
@section('content')
    <div class="page-main">
        <div class="container">
            <div class="cashier-wrap">
                <div class="cashier-card">
                    <div class="cashier-head">
                        <span class="tag">本地沙箱</span>
                        <h2>米商城 - 收银台</h2>
                        <p class="hint">这是本地模拟收银台，仅用于调试，不会真实扣款。</p>
                    </div>
                    <div class="cashier-body">
                        <div class="row"><span class="label">收款商户</span><span>米商城</span></div>
                        <div class="row"><span class="label">订单号</span><span>{{$order->order_num}}</span></div>
                        <div class="row"><span class="label">支付方式</span><span>{{$methodName}}</span></div>
                        <div class="row"><span class="label">商品</span><span>@foreach($goods as $g){{$g->goods_name}} @endforeach</span></div>
                        <div class="row amount"><span class="label">支付金额</span><span class="money">¥ {{ number_format((float)$order->total_price, 2, '.', '') }}</span></div>
                    </div>
                    <div class="cashier-foot">
                        <form action="/pay/notify/{{$order->id}}" method="post">
                            @csrf
                            <input type="hidden" name="amount" value="{{$order->total_price}}" />
                            <button type="submit" class="btn-pay">确认支付 ¥{{ number_format((float)$order->total_price, 2, '.', '') }}</button>
                            <a href="/order/pay/{{$order->id}}" class="btn-cancel">取消支付</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
