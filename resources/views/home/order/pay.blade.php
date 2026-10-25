@extends('layout.index')
@section('title','去付款')
@section('css')
    <link rel="shortcut icon" href="/cdn/s01.mifile.cn/favicon.ico" type="image/x-icon" />
    <link rel="icon" href="/cdn/s01.mifile.cn/favicon.ico" type="image/x-icon" />
    <link rel="stylesheet" href="/cdn/s01.mifile.cn/css/base.min.css" />
    <link rel="stylesheet" type="text/css" href="/cdn/s01.mifile.cn/css/pay-confirm.min.css" />
    <style>
        .payment-list li { display:inline-block; margin:10px 12px 10px 0; }
        .pay-method { display:inline-flex; align-items:center; border:1px solid #e0e0e0; padding:12px 18px; cursor:pointer; border-radius:4px; }
        .pay-method input { margin-right:8px; }
        .pay-method:hover { border-color:#ff6700; }
        .pay-method.active { border-color:#ff6700; background:#fff7f2; }
        .btn-pay { display:inline-block; margin-top:16px; background:#ff6700; color:#fff; border:0; padding:12px 40px; font-size:16px; border-radius:4px; cursor:pointer; }
        .btn-pay:hover { background:#ff4d00; }
    </style>
@endsection
@section('header')
    <div class="site-header site-mini-header">
        <div class="container">
            <div class="header-logo">
                <a class="logo " href="/" title="小米官网"></a>
            </div>
            <div class="header-title" id="J_miniHeaderTitle"><h2>订单支付</h2></div>
        </div>
    </div>
@endsection
@section('content')
    <div class="page-main">
        <div class="container confirm-box">
            <div class="section section-order">
                <div class="order-info clearfix">
                    <div class="fl">
                        <h2 class="title">订单提交成功！去付款咯～</h2>
                        <p class="order-time" id="J_deliverDesc">现在支付，预计2-3天送达</p>
                        <p class="order-time">
                            请在
                            <span class="pay-time-tip">{{$countTime}}</span>
                            内完成支付，超时后将取消订单
                        </p>
                        <p class="post-info" id="J_postInfo">
                            收货信息：{{$order->consignee}} {{$order->phone}} &nbsp;&nbsp;
                            {{$order->province}}&nbsp;&nbsp;{{$order->city}}&nbsp;&nbsp;{{$order->district}}&nbsp;&nbsp;{{$order->address}}
                        </p>
                    </div>
                    <div class="fr">
                        <p class="total">
                            应付总额：
                            <span class="money"> <em>{{ number_format((float)$order->total_price, 2, '.', '') }}</em>
                                元
                            </span>
                        </p>
                        <a href="javascript:void(0);" class="show-detail" id="J_showDetail">
                            订单详情 <i class="iconfont">&#xe61c;</i>
                        </a>
                    </div>
                </div> <i class="iconfont icon-right">&#x221a;</i>
                <div class="order-detail">
                    <ul>
                        <li class="clearfix">
                            <div class="label">订单号：</div>
                            <div class="content">
                                <span class="order-num">{{$order->order_num}}</span>
                            </div>
                        </li>
                        <li class="clearfix">
                            <div class="label">收货信息：</div>
                            <div class="content">
                                收货信息：{{$order->consignee}} {{$order->phone}} &nbsp;&nbsp;
                                {{$order->province}}&nbsp;&nbsp;{{$order->city}}&nbsp;&nbsp;{{$order->district}}&nbsp;&nbsp;{{$order->address}}
                            </div>
                        </li>
                        <li class="clearfix">
                            <div class="label">商品名称：</div>
                            <div class="content">
                                @foreach($order->orderGoods as $k=>$v)
                                    {{$v->goods_name}} <br/>
                                @endforeach

                            </div>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="section section-payment">
                <div class="cash-title" id="J_cashTitle">选择以下支付方式付款</div>
                <form action="/order/payfor" method="get" id="J_payForm">
                    <input type="hidden" name="order_id" value="{{$order->id}}" />
                    <div class="payment-box">
                        <div class="payment-header clearfix">
                            <h3 class="title">支付平台</h3>
                        </div>
                        <div class="payment-body">
                            <ul class="clearfix payment-list J_paymentList">
                                <li>
                                    <label class="pay-method active">
                                        <input type="radio" name="method" value="alipay" checked />
                                        <span class="pay-name">支付宝</span>
                                    </label>
                                </li>
                                <li>
                                    <label class="pay-method">
                                        <input type="radio" name="method" value="wechat" />
                                        <span class="pay-name">微信支付</span>
                                    </label>
                                </li>
                                <li>
                                    <label class="pay-method">
                                        <input type="radio" name="method" value="mipay" />
                                        <span class="pay-name">小米钱包</span>
                                    </label>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <button type="submit" class="btn-pay">去支付</button>
                </form>
            </div>
        </div>
    </div>
@endsection
@section('js')

<script>
$(function(){
    $('#J_showDetail').on('click',function(){
        $('.order-detail').slideToggle();
    });
    $('.pay-method').on('click', function(){
        $('.pay-method').removeClass('active');
        $(this).addClass('active');
    });
})
</script>
@endsection
