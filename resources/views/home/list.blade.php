@extends('layout.index')
@section('title','小米官网')

@section('css')
    <link rel="shortcut icon" href="/favicon.ico" />
    <link href="/homes/common/css/style.css" rel="stylesheet" type="text/css" />
    <link href="/homes/common/css/category.css" rel="stylesheet" type="text/css" />
    <link rel="icon" href="/favicon.ico" type="image/x-icon" />
    <link rel="stylesheet" href="/homes/common/css/base.min.css" />
    <script type="text/javascript" src="/homes/common/js/jquery-1.9.1.min.js"></script>
    <script type="text/javascript" src="/homes/common/js/jquery.json.js"></script>
    <script type="text/javascript" src="/homes/common/js/common.js"></script>
    <script type="text/javascript" src="/homes/common/js/global.js"></script>
    <script type="text/javascript" src="/homes/common/js/easydialog.min.js"></script>
    <script type="text/javascript" src="/homes/common/js/compare.js"></script>
    <script type="text/javascript" src="/homes/common/js/transport_jquery.js"></script>
    <script type="text/javascript" src="/homes/common/js/utils.js"></script>
    <script type="text/javascript" src="/homes/common/js/jquery.SuperSlide.js"></script>
    <script type="text/javascript" src="/homes/common/js/xiaomi_common.js"></script>
@endsection






@section('content')
<script type="text/javascript" src="/homes/common/js/xiaomi_category.js"></script>

<div class="breadcrumbs">
    <div class="container">
        <a href="/">首页</a>
        <code>&gt;</code>
        <a href="/list/1">购买手机</a>
    </div>
</div>


<div class="content">
    <div class="container">
        @php $listBase = '/list'.(request()->route('id') ? '/'.request()->route('id') : ''); @endphp
        <div class="order-list-box clearfix">
            <form method="GET" name="listform">
                @php
                $so = [
                    'sales_volume' => ($sort === 'sales_volume') ? ($order === 'DESC' ? 'ASC' : 'DESC') : 'DESC',
                    'price'        => ($sort === 'price')        ? ($order === 'DESC' ? 'ASC' : 'DESC') : 'DESC',
                    'new'          => ($sort === 'new')          ? ($order === 'DESC' ? 'ASC' : 'DESC') : 'DESC',
                ];
                $ar = [
                    'sales_volume' => ($sort === 'sales_volume') ? ($order === 'DESC' ? '▼' : '▲') : '',
                    'price'        => ($sort === 'price')        ? ($order === 'DESC' ? '▼' : '▲') : '',
                    'new'          => ($sort === 'new')          ? ($order === 'DESC' ? '▼' : '▲') : '',
                ];
                @endphp
                <ul class="order-list">
                    <li class="first {{ $sort == 'sales_volume' ? 'active' : '' }}"><a title="销量" href="{{$listBase}}?sort=sales_volume&order={{$so['sales_volume']}}#goods_list" class="{{ $sort == 'sales_volume' ? 'curr' : '' }}" rel="nofollow"><span class="{{ $sort == 'sales_volume' ? 'search_DESC' : '' }}">销量</span>&nbsp;<i class="order-arrow" style="color:#ff6700">{{$ar['sales_volume']}}</i></a></li>
                    <li class="{{ $sort == 'price' ? 'active' : '' }}"><a title="价格" href="{{$listBase}}?sort=price&order={{$so['price']}}#goods_list" class="{{ $sort == 'price' ? 'curr' : '' }}" rel="nofollow"><span class="{{ $sort == 'price' ? 'search_DESC' : '' }}">价格</span>&nbsp;<i class="order-arrow" style="color:#ff6700">{{$ar['price']}}</i></a></li>
                    <li class="{{ $sort == 'new' ? 'active' : '' }}"><a title="上架时间" href="{{$listBase}}?sort=new&order={{$so['new']}}#goods_list" class="{{ $sort == 'new' ? 'curr' : '' }}" rel="nofollow"><span class="{{ $sort == 'new' ? 'search_DESC' : '' }}">上架时间</span>&nbsp;<i class="order-arrow" style="color:#ff6700">{{$ar['new']}}</i></a></li>
                    <input type="hidden" name="category" value="69" />
                    <input type="hidden" name="display" value="grid" id="display" />
                    <input type="hidden" name="brand" value="0" />
                    <input type="hidden" name="price_min" value="0" />
                    <input type="hidden" name="price_max" value="0" />
                    <input type="hidden" name="filter_attr" value="0" />
                    <input type="hidden" name="page" value="1" />
                    <input type="hidden" name="sort" value="sales_volume" />
                    <input type="hidden" name="order" value="DESC" />
                </ul>
            </form>

        </div>
        <form name="compareForm" action="{{$listBase}}" method="get" onsubmit="return compareGoods(this);">
            <div class="goods-list-box">


                {{--这里是需要遍历的单体--}}
                @foreach($goods as $good)
                    @if($good->skus->first())
                <div class="goods-item" style="width:292px;">
                    <div class="figure figure-img">
                        <a href="/detail/{{$good->id}}"><img src="{{$good->showImg}}" alt="红米Note2" class="goodsimg" /></a>
                    </div>
                    <p class="desc">{{$good->sub_title}}</p>
                    <h2 class="title"><a href="/detail/{{$good->id}}" title="红米Note2">{{$good->title}}</a></h2>
                    <p class="price"> 本店价<font class="shop_s">{{$good->price}}<em>元</em></font>
                        <del>
                            专柜价
                            <font class="market_s">{{$good->price+300}}<em>元</em></font>
                        </del>
                    </p>

                    <div class="thumbs J_attrImg" >
                        <div style="width:212px;margin:0 auto;" >
                            <ul class="thumb-list clearfix J_imgList">

                                <a><img src="{{$good->skus->first()->img}}" width="34" height="34" /></a>

                            </ul>
                        </div>
                    </div>

                    <div class="actions clearfix">
                        <a href="javascript:;" onclick="collect({{$good->id}}, this); return false;" class="btn-like J_likeGoods {{ in_array($good->id, $collectedGoods) ? 'liked' : '' }}"><i class="iconfont"></i> <span>{{ in_array($good->id, $collectedGoods) ? '已收藏' : '收藏' }}</span></a>
                        <a href="javascript:;" onclick="addCartFromList({{$good->id}}, this); return false;" class="btn-buy J_buyGoods"><span>购买</span> <i class="iconfont"></i></a>
                    </div>

                </div>
                    @endif
                @endforeach
                {{--这里是需要遍历的单体 结束--}}


            </div>
        </form>
        {{-- 分页条与加购成功弹窗属于页面内容, 必须留在 content 区段内 --}}
        <form name="selectPageForm" action="{{$listBase}}" method="get">
            <div class="clearfix">
                <div id="pager" class="pagebar">
                    <span class="f_l f6" style="margin-right:10px;">总计 <b>好多个</b> 个记录</span>
                </div>
            </div>
        </form>
    </div>
</div>
<div class="add_ok" id="cart_show">
    <div class="tip">
        <i class="iconfont"> </i>商品已成功加入购物车
    </div>
    <div class="go">
        <a href="javascript:easyDialog.close();" class="back">&lt;&lt;继续购物</a>
        <a href="/cart" class="btn">去结算</a>
    </div>
</div>
        @endsection

        @section('LDjs')
        <script type="Text/Javascript" language="JavaScript">
            <!--
            function selectPage(sel)
            {
                sel.form.submit();
            }
            //-->
        </script>
        <script type="text/javascript">
            window.onload = function()
            {
                Compare.init();
                fixpng();
            }
            var button_compare = '';
            var exist = "您已经选择了%s";
            var count_limit = "最多只能选择4个商品进行对比";
            var goods_type_different = "\"%s\"和已选择商品类型不同无法进行对比";
            var compare_no_goods = "您没有选定任何需要比较的商品或者比较的商品数少于 2 个。";
            var btn_buy = "购买";
            var is_cancel = "取消";
            var select_spe = "请选择商品属性";
        </script>
        <style type="text/css">
            /*加购成功后按钮短暂高亮反馈*/
            .btn-buy.btn-buy-added { background-color: #f70; border-color: #f70; }
        </style>
        <script type="text/javascript">
        //列表页购买按钮: 调用真实加购接口
        //原 ECShop addToCart() 走 flow.php?step=add_to_cart 在本站已无此接口, 只会 404
        //列表卡片无规格选择, 只传 good_id, 后端自动取该商品第一个 SKU
        function addCartFromList(goodsId, btn)
        {
            var $btn = $(btn);
            if ($btn.data('busy')) return;
            $btn.data('busy', 1);
            $.ajax({
                type: "get",
                dataType: 'json',
                url: '/cart/ajax-add',
                data: {good_id: goodsId, num: 1},
                error: function () {
                    alert('系统错误请稍后再试!');
                },
                success: function (result) {
                    if (result && result.status == 0) {
                        //顶栏购物车角标联动
                        syncCartNum(result.count);
                        //按钮短暂切换为已加入态
                        var $span = $btn.find('span').text('已加入');
                        $btn.addClass('btn-buy-added');
                        setTimeout(function () {
                            $span.text('购买');
                            $btn.removeClass('btn-buy-added');
                        }, 1200);
                        //样式化成功弹窗 (#cart_show)
                        easyDialog.open({
                            container: 'cart_show'
                        });
                    } else {
                        alert(result && result.msg ? result.msg : '加入购物车失败, 请稍后再试!');
                    }
                },
                complete: function () {
                    $btn.data('busy', 0);
                }
            });
        }
        </script>
@endsection


