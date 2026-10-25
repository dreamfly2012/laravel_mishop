@extends('layout.index')
@section('myCss')
<link rel="stylesheet" href="/homes/common/css/index.min.css" />
@endsection
@section('content')
    <div class="home-hero-container container">
        <div class="home-hero">
            <div class="home-hero-slider">
                <div class="ui-wrapper" style="max-width: 100%;">
                    <div class="ui-viewport" style="width: 100%; overflow: hidden; position: relative; height: 460px;">
                        <div id="J_homeSlider" class="xm-slider" data-stat-title="焦点图轮播" style="width: auto; position: relative;">
                            @forelse($carousel as $b)
                            <div class="slide loaded">
                                <a href="{{$b->link()}}" >
                                   <img src="{{$b->image}}" alt="{{$b->title}}"></a>
                            </div>
                            @empty
                            <div class="slide loaded">
                                <a href="/" >
                                   <img src="/image/upload/mi-test.jpg" alt=""></a>
                            </div>
                            @endforelse

                        </div>
                    </div>
                    <div class="ui-controls-direction">
                        <a class="ui-prev" href="javascript:void(0);">上一张</a>
                        <a class="ui-next" href="javascript:void(0);">下一张</a>
                    </div>
                    <div class="ui-controls ui-has-pager ui-has-controls-direction">
                        <div class="ui-pager ui-default-pager"></div>
                    </div>
                </div>
            </div>

            <div class="home-hero-sub row">
                <div class="span4">
                    <ul class="home-channel-list clearfix">
                        @forelse($channel as $c)
                        <li class="@if($loop->first) top left @elseif($loop->iteration<=3) top @elseif($loop->iteration%2==1) left @endif">
                            <a href="{{$c->link()}}">
                                @if($c->icon)<i class="iconfont">{!! $c->icon !!}</i>@endif
                                {{$c->title}}
                            </a>
                        </li>
                        @empty
                        <li class="top left"><a href="/">频道入口</a></li>
                        @endforelse
                    </ul>
                </div>
                <div class="span16" id="J_homePromo" data-stat-title="焦点图下方小图">
                    <ul class="home-promo-list clearfix">
                        @forelse($promo as $p)
                        <li class="@if($loop->first) first @endif">
                            <a class="item" href="{{$p->link()}}">
                                <img alt="{{$p->title}}" src="{{$p->image}}" />
                            </a>
                        </li>
                        @empty
                        <li class="first"><a class="item" href="/"><img alt="" src="/image/upload/mi-test.jpg" /></a></li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>

        <div class="home-star-goods" id="J_homeStar">
            <div class="xm-plain-box">
                <div class="box-hd">
                    <h2 class="title">小米明星单品</h2>
                    <div class="more">
                        <div class="xm-controls xm-controls-line-small xm-carousel-controls">
                            <a class="control control-prev iconfont" href="javascript: void(0);"></a>
                            <a class="control control-next iconfont" href="javascript: void(0);"></a>
                        </div>
                    </div>
                </div>
                <div class="box-bd">
                    <ul class="xm-carousel-list xm-carousel-col-5-list goods-list rainbow-list clearfix J_carouselList">
                    @if(!empty($star))
                        @foreach($star as $key=>$val)
                            @if($key<=20)
                        <li class="rainbow-item-{{$key}}">
                            <a class="thumb" href="/detail/{{$val->id}}"  target="_blank">
                                <img src="/cdn/i3.mifile.cn/a4/40d24892-317d-4883-ad63-647f1b9e3cdf" srcset="{{$val->showImg}}" alt="{{$val->title}}" />
                            </a>
                            <h3 class="title">
                                <a href="/detail/{{$val->id}}" target="_blank">{{$val->title}}</a>
                            </h3>
                            <p class="desc">{{$val->sub_title}}</p>
                            <p class="price">{{$val->price}}</p>
                        </li>
                            @endif
                        @endforeach
                    @endif
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="page-main home-main">
        <div class="container">
            <div id="smart" class="home-brick-box home-brick-row-2-box xm-plain-box J_itemBox J_brickBox" data-from-stat="false">
                <div class="box-hd">
                    <h2 class="title">智能硬件</h2>
                    <div class="more J_brickNav"></div>
                </div>
                <div class="box-bd J_brickBd">
                <!-- 智能硬件 start-->
                    <div class="row">
                        <div class="span4 span-first">
                            <ul class="brick-promo-list clearfix">
                                <li class="brick-item brick-item-l brick-item-active">
                                    <a href="/" data-stat-aid="AA13253" data-stat-pid="2_18_1_90" target="_blank" data-stat-id="AA13253+2_18_1_90" onclick="_msq.push(['trackEvent', '79fe2eae924d2a2e-AA13253+2_18_1_90', '//www.mi.com/scooter/', 'pcpid']);">
                                        <img src="/cdn/i3.mifile.cn/a4/124d82cc-cfce-44ab-b711-28b21be81683" width="160" height="160" alt=""></a>
                                </li>
                            </ul>
                        </div>
                        <div class="span16">
                            <ul class="brick-list clearfix">
                                @foreach($smarty as $key=>$v)
                                <li class="brick-item brick-item-m brick-item-m-2" data-gid="1161200059">
                                    <div class="figure figure-img">
                                        <a href="/detail/{{$v->good->id}}">
                                            <img src="{{$v->img}}" width="160" height="160" alt="{{$v->title}}"></a>
                                    </div>
                                    <h3 class="title">
                                        <a href="/detail/{{$v->good->id}}">{{$v->title}}</a>
                                    </h3>
                                    <p class="desc">{{$v->good->sub_title}}</p>
                                    <p class="price">
                                        <span class="num">{{$v->price}}</span>
                                        元
                                    </p>
                                    <!-- <div class="flag flag-postfree">免邮费</div> -->
                                </li>
                                @endforeach

                            </ul>
                        </div>
                    </div>
                <!-- 智能硬件 end-->
                </div>
            </div>





            <div id="video" class="home-video-box xm-plain-box J_itemBox J_videoBox is-visible">
                <div class="box-hd">
                    <h2 class="title">视频</h2>
                    <div class="more J_brickNav">

                    </div>
                </div>
                <div class="box-bd J_brickBd">
                    <!-- 视频 start -->
                    <ul class="video-list clearfix">
                        <li class="video-item video-item-first">
                            <div class="figure figure-img">
                                <a class="J_videoTrigger" href="javascript: void(0);" data-stat-aid="AA12841" data-stat-pid="2_43_1_245" data-video="http://player.youku.com/embed/XMTU2NDM3NjEzMg==" data-video-title="小米Max 绝美外观视频" >
                                    <img src="/cdn/i3.mifile.cn/a4/T1v3LgBTxv1RXrhCrK.jpg" width="296" height="180" alt="小米Max 绝美外观视频">
                                    <span class="play"> <i class="iconfont"></i>
                                    </span>
                                </a>
                            </div>
                            <h3 class="title">
                                <a class="J_videoTrigger" href="javascript: void(0);" data-stat-aid="AA12841" data-stat-pid="2_43_1_245" data-video="http://player.youku.com/embed/XMTU2NDM3NjEzMg==" data-video-title="小米Max 绝美外观视频" >小米Max 绝美外观视频</a>
                            </h3>
                            <p class="desc">6.44" 大屏黄金尺寸，看什么都震撼</p>
                        </li>
                        <li class="video-item">
                            <div class="figure figure-img">
                                <a class="J_videoTrigger" href="javascript: void(0);" data-stat-aid="AA13049" data-stat-pid="2_43_2_246" data-video="http://player.youku.com/embed/XMTU5ODI2NzMyMA==" data-video-title="手机摄影，防抖绷带（教程）" title="点击播放视频" >
                                    <img src="/cdn/i3.mifile.cn/a4/66cbfd29-8e48-4ed1-aee0-e0e46f4cdc8b" width="296" height="180" alt="手机摄影，防抖绷带（教程）">
                                    <span class="play"> <i class="iconfont"></i>
                                    </span>
                                </a>
                            </div>
                            <h3 class="title">
                                <a class="J_videoTrigger" href="javascript: void(0);" data-stat-aid="AA13049" data-stat-pid="2_43_2_246" data-video="http://player.youku.com/embed/XMTU5ODI2NzMyMA==" data-video-title="手机摄影，防抖绷带（教程）" data-stat-id="AA13049+2_43_2_246">手机摄影，防抖绷带（教程）</a>
                            </h3>
                            <p class="desc">一分钟，拍照手抖迅速见效</p>
                        </li>
                        <li class="video-item">
                            <div class="figure figure-img">
                                <a class="J_videoTrigger" href="javascript: void(0);" data-stat-aid="AA12771" data-stat-pid="2_43_3_247" data-video="http://player.youku.com/embed/XMTU2MTQ2Njg3Ng==" data-video-title="笑喷了，沈腾爆笑出演，6集联播" title="点击播放视频">
                                    <img src="/cdn/i3.mifile.cn/a4/T1k9CgB7Av1RXrhCrK.jpg" width="296" height="180" alt="笑喷了，沈腾爆笑出演，6集联播">
                                    <span class="play">
                                        <i class="iconfont"></i>
                                    </span>
                                </a>
                            </div>
                            <h3 class="title">
                                <a class="J_videoTrigger" href="javascript: void(0);" data-stat-aid="AA12771" data-stat-pid="2_43_3_247" data-video="XMTU2MTQ2Njg3Ng==" data-video-title="笑喷了，沈腾爆笑出演，6集联播" >笑喷了，沈腾爆笑出演，6集联播</a>
                            </h3>
                            <p class="desc">小米Max沈腾爆笑预告全集</p>
                        </li>
                        <li class="video-item">
                            <div class="figure figure-img">
                                <a class="J_videoTrigger" href="javascript: void(0);" data-stat-aid="AA12405" data-stat-pid="2_43_4_249" data-video="XMTUwMTEyMjk0MA==" data-video-title="15秒了解小米5 有多快" title="点击播放视频">
                                    <img src="/cdn/i3.mifile.cn/a4/T1ZOZgBmYv1RXrhCrK.jpg" width="296" height="180" alt="15秒了解小米5 有多快">
                                    <span class="play">
                                        <i class="iconfont"></i>
                                    </span>
                                </a>
                            </div>
                            <h3 class="title">
                                <a class="J_videoTrigger" href="javascript: void(0);" data-stat-aid="AA12405" data-stat-pid="2_43_4_249" data-video="XMTUwMTEyMjk0MA==" data-video-title="15秒了解小米5 有多快">15秒了解小米5 有多快</a>
                            </h3>
                            <p class="desc">华少用超快语速告诉你小米5 到底有多快</p>
                        </li>
                    </ul>
                    <!-- 视频 end -->
                </div>
            </div>
        </div>
    </div>

    <div id="J_modalVideo" class="modal modal-video fade modal-hide">
        <div class="modal-hd">
            <h3 class="title">视频播放</h3>
            <a class="close" data-dismiss="modal" href="javascript: void(0);">
                <i class="iconfont">&#xe602;</i>
            </a>
        </div>
        <div class="modal-bd">
            <div class="loading">
                <div class="loader"></div>
            </div>
        </div>
    </div>
@endsection