<!doctype html>
<html lang="zh-CN" xml:lang="zh-CN">
<head>
    <meta http-equiv="X-UA-Compatible" content="IE=Edge" />
    <meta charset="UTF-8" />
    <title>@yield('title')</title>
    <meta name="description" content="小米商城-直营小米公司旗下所有产品，囊括小米手机、红米手机、小米电视、智能硬件、配件及小米生活周边，同时提供小米客户服务及售后支持。" />
    <meta name="keywords" content="小米,小米官网,小米手机,小米官网首页,小米商城" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php($cartCount = \App\Http\Controllers\Home\CartController::cartCount())
    {{-- jQuery 放在 head 最前: 各页面的插件脚本(1.9.1 / base.min.js / SuperSlide)都紧跟其后加载,
         避免底部再加载一份 jQuery 覆盖实例, 导致先前绑定的 $.cookie / $.evalJSON 等插件丢失 --}}
    <script src="/homes/common/myjs/jquery.min.js"></script>
    {{-- 页面里残留的 onclick="_msq.push([...])" 埋点: 小米统计脚本已移除, 留空实现避免点击时抛 ReferenceError --}}
    <script>window._msq = window._msq || { push: function () {} };</script>
    @section('css')
    <link rel="icon" href="/favicon.ico" type="image/x-icon" />
    <link rel="stylesheet" href="/homes/common/css/base.min.css" />
    @show
    @section('myCss')
    @show
    <link rel="stylesheet" href="/homes/common/css/theme.css" />
    <link rel="stylesheet" href="/homes/common/css/responsive.css" />
</head>
<body>
<?php /* 移动端导航栏 + 抽屉 (桌面隐藏, ≤767 显示; 汉堡 ≤1024 顶栏显示) */
    try {
        $mCates = \App\Models\Cate::where('pid', 0)->where('status', 1)->orderBy('id')->get();
    } catch (\Throwable $mCateEx) {
        $mCates = collect();
    }
?>
<div class="m-bar">
    <div class="m-bar-inner">
        <a class="m-logo" href="/">小米商城</a>
        <div class="m-actions">
            <a href="/cart" aria-label="购物车"><i class="iconfont">&#xe60c;</i>@if($cartCount > 0)<span class="m-cart-num">{{ $cartCount }}</span>@endif</a>
            <button class="m-burger J_mBurger" type="button" aria-label="菜单"><span></span><span></span><span></span></button>
        </div>
    </div>
    <form class="m-search" action="/list-search" method="get">
        <input type="text" name="kWord" placeholder="搜索商品" autocomplete="off" />
        <button type="submit" aria-label="搜索"></button>
    </form>
</div>
<div class="m-drawer" id="J_mobileDrawer">
    <div class="m-drawer-hd-bar">
        <button class="m-drawer-back J_mClose" type="button">✕ 关闭</button>
    </div>
    <div class="m-drawer-user">
        @if(!session('uid'))
        <a class="m-primary" href="/login">登录 / 注册</a>
        @else
        <a class="m-primary" href="/user/order">我的订单</a>
        <a href="/user/comment">评价晒单</a>
        <a href="/user/logout">退出登录</a>
        @endif
    </div>
    <div class="m-drawer-title">全部商品分类</div>
    @foreach($mCates as $mCate)
    <div class="m-cate-item">
        <a class="hd" href="/list/{{$mCate->id}}">{{$mCate->name}}</a>
        <div class="m-cate-sub">
            <ul>
                @php($mChildren = \App\Models\Cate::where('pid', $mCate->id)->where('status', 1)->orderBy('id')->get())
                @foreach($mChildren as $mChild)
                <li>
                    <a href="/list/{{$mChild->id}}">
                        @if($mChild->img)<img src="{{$mChild->img}}" alt="" />@endif
                        <span>{{ $mChild->name }}</span>
                    </a>
                </li>
                @endforeach
            </ul>
        </div>
    </div>
    @endforeach
</div>
<div class="m-mask"></div>
@section('header')
    <div class="site-topbar">
        <div class="container">
            <div class="topbar-nav">
                <a rel="nofollow" href="/" >小米商城</a>

            </div>
            <button class="m-burger J_mBurger" type="button" aria-label="菜单"><span></span><span></span><span></span></button>
            <div class="topbar-cart" id="J_miniCartTrigger">
                <a rel="nofollow" class="cart-mini" id="J_miniCartBtn" href="/cart"> <i class="iconfont">&#xe60c;</i>
                    购物车
                    <span class="cart-mini-num J_cartNum">@if($cartCount > 0)（{{ $cartCount }}）@endif</span>
                </a>
                {{--<div class="cart-menu" id="J_miniCartMenu">--}}
                    {{--<div class="loading">--}}
                        {{--<div class="loader"></div>--}}
                    {{--</div>--}}
                {{--</div>--}}
            </div>
             <?php if(!session('uid')){?>
            <div class="topbar-info" id="J_userInfo">
                <a  rel="nofollow" class="link" href="/login" data-needlogin="true">登录</a>
                <span class="sep">|</span>
                <a  rel="nofollow" class="link" href="/login/register" >注册</a>
            </div>
            <?php 
                }else{ 

                    $user = \App\Http\Controllers\Home\UserController::gainUsername();
            ?>
            <div class="topbar-info" id="J_userInfo">
                <span class="user">
                    <a rel="nofollow" class="user-name" href="/user/comment" target="_blank">
                        <span class="name">{{$user->username}}</span> <i class="iconfont"></i>
                    </a>
                    <ul class="user-menu" style="display: none;">

                        <li>
                            <a rel="nofollow" href="/user/comment" target="_blank">评价晒单</a>
                        </li>
                        <!-- <li>
                            <a rel="nofollow" href="http://order.mi.com/user/favorite" target="_blank">我的喜欢</a>
                        </li> -->
                        <li>
                            <a rel="nofollow" href="/user/logout">退出登录</a>
                        </li>
                    </ul>
                </span>
                <span class="sep">|</span>
                <a rel="nofollow" class="link link-order" href="/user/order" target="_blank">我的订单</a>
            </div>
            <?php } ?>
        </div>
    </div>
    <div class="site-header">
        <div class="container">
            <div class="header-logo">
                <a class="logo ir" href="/" title="小米官网">小米官网</a>
            </div>
            <div class="header-nav">
                <ul class="nav-list J_navMainList clearfix">
                    <li id="J_navCategory" class="nav-category">
                        <a class="link-category" href="/list">
                            <span class="text">全部商品分类</span>
                        </a>
                        <!-- 分类导航 start -->
                    </li>

                </ul>
            </div>
            <div class="header-search">
                <form id="J_searchForm" class="search-form clearfix" action="/list-search" method="get">
                    <label for="search" class="hide">站内搜索</label>
                    <input class="search-text" type="search" id="search" name="kWord" autocomplete="off" />
                    <input type="submit" class="search-btn iconfont" value="&#xe616;" />
                    <div class="search-hot-words">
                        <a href="/list-search?kWord=小米手机5">小米手机5</a>
                    </div>
                </form>
            </div>
        </div>
        <div id="J_navMenu" class="header-nav-menu" style="display: none;"><div class="container"></div></div>
    </div>
@show
@section('content')

@show
    <div class="site-footer">
        <div class="container">
            <div class="footer-service">
                <ul class="list-service clearfix">
                    <li>
                        <a rel="nofollow" href="/" target="_blank">
                            <i class="iconfont">&#xe634;</i>
                            1小时快修服务
                        </a>
                    </li>
                    <li>
                        <a rel="nofollow" href="/" target="_blank">
                            <i class="iconfont">&#xe635;</i>
                            7天无理由退货
                        </a>
                    </li>
                    <li>
                        <a rel="nofollow" href="/" target="_blank">
                            <i class="iconfont">&#xe636;</i>
                            15天免费换货
                        </a>
                    </li>
                    <li>
                        <a rel="nofollow" href="/" target="_blank">
                            <i class="iconfont">&#xe638;</i>
                            满150元包邮
                        </a>
                    </li>
                    <li>
                        <a rel="nofollow" href="/" target="_blank">
                            <i class="iconfont">&#xe637;</i>
                            520余家售后网点
                        </a>
                    </li>
                </ul>
            </div>
            <div class="footer-links clearfix">
                <?php
                    $help = \App\Http\Controllers\Home\HelpController::getHelp();
                ?>
                @foreach($help as $key=>$val)
                <dl class="col-links col-links-first">
                    <dt>{{$val->name}}</dt>
                    @foreach($val->helpArticle as $k=>$v)
                    @if($k<4)
                    <dd>
                        <a rel="nofollow" href="/help/article/{{$v->id}}"   target="_blank">{{$v->title}}</a>
                    </dd>
                    @endif
                    @endforeach
                    
                </dl>
                @endforeach
 

                <div class="col-contact">
                    <p class="phone">400-100-5678</p>
                    <p>
                        <span class="J_serviceTime-normal">周一至周日 8:00-18:00</span>
                        <span class="J_serviceTime-holiday" style="display:none;">2月7日至13日服务时间 9:00-18:00</span>
                        <br>（仅收市话费）</p>
                    <a rel="nofollow" class="btn btn-line-primary btn-small" href="/" target="_blank">
                        <i class="iconfont">&#xe600;</i>
                        24小时在线客服
                    </a>
                </div>
            </div>
        </div>
    </div>
    <div class="site-info">
        <div class="container">
            <div class="logo ir">小米官网</div>
            <div class="info-text">

                <p>
                    &copy;
                    <a href="/" target="_blank" title="mi.com">mi.com</a>
                    京ICP证00001号 京ICP备0000000号
                    <a rel="nofollow"  href="http://www.beian.gov.cn/portal/registerSystemInfo?recordcode=11010802020134" target="_blank">京公网安备0000000000000000号</a>
                    <a rel="nofollow"  href="/cdn/c1.mifile.cn/f/i/2013/cn/jingwangwen.jpg" target="_blank" rel="nofollow">京网文[2014]0059-0009号</a>

                    <br>违法和不良信息举报电话：185-0130-1238，本网站所列数据，除特殊说明，所有数据均出自我司实验室测试</p>
            </div>
            <div class="info-links">
                <a  rel="nofollow" href="//search.szfw.org/cert/l/CX20120926001783002010" target="_blank">
                    <img  rel="nofollow" src="/cdn/s01.mifile.cn/i/v-logo-2.png" alt="诚信网站" />
                </a>
                <a  rel="nofollow" href="https://ss.knet.cn/verifyseal.dll?sn=e12033011010015771301369&ct=df&pa=461082" target="_blank">
                    <img  rel="nofollow" src="/cdn/s01.mifile.cn/i/v-logo-1.png" alt="可信网站" />
                </a>
                <a  rel="nofollow" href="http://www.315online.com.cn/member/315140007.html" target="_blank">
                    <img  rel="nofollow" src="/cdn/s01.mifile.cn/i/v-logo-3.png" alt="网上交易保障中心" />
                </a>
            </div>
        </div>
        <div class="slogan ir">探索黑科技，小米为发烧而生</div>
    </div>
    <div id="J_modalWeixin" class="modal fade modal-hide modal-weixin" data-width="480" data-height="520">
        <div class="modal-hd">
            <a class="close" data-dismiss="modal">
                <i class="iconfont">&#xe602;</i>
            </a>
            <span class="title">小米手机官方微信二维码</span>
        </div>
        <div class="modal-bd">
            <p style="margin: 0 0 10px;">
                打开微信，点击右上角的“+”，选择“扫一扫”功能，
                <br/>
                对准下方二维码即可。
            </p>
            <img alt="" src="/cdn/c1.mifile.cn/f/i/2014/cn/qr.png" width="375" height="375" />
        </div>
    </div>
    @section('js')
    <script src="/data/indexNav.js"></script>
    <script src="/data/indexData.js"></script>
    <script src="/homes/common/myjs/jquery.SuperSlide.js"></script>
    <script src="/homes/common/myjs/common.js"></script>
    <script src="/homes/common/myjs/index.js"></script>
    @show
    {{-- 移动导航: 详情/购物车/结算/个人中心等页重写了 js 区段(且都自行引入 jQuery), 这里兜底加载, 保证汉堡菜单与抽屉在各页都可用 --}}
    <script src="/homes/common/myjs/mobile.js"></script>
    @section('LDjs')
    @show
</body>
</html>
   