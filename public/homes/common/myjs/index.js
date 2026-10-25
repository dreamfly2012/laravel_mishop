jQuery(function(){
    var isWide = window.innerWidth > 1024;

    //首页幻灯片效果
    $(".ui-wrapper").slide({
        //切换元素的包裹层对象
        mainCell:'#J_homeSlider',
        //前一个
        prevCell:'.ui-prev',
        nextCell:'.ui-next',
        interTime:3600,
        autoPlay:true,
        titOnClassName:"active",
        effect:"fold",
        trigger:"click",
        // 轮播图数量由后台 banners 表动态决定, 自动生成分页圆点
        autoPage:true,
        titCell:'.ui-pager .ui-pager-item a'
    });

    //小米明星单品/智能硬件/视频 (SuperSlide 会用固定像素包装列表, 破坏移动端弹性布局,
    //小屏不启用轮播, 商品直接按响应式栅格铺开)
    if (isWide) {
        $('.xm-plain-box').slide({
            prevCell:".box-hd .more .control-prev",
            nextCell:".box-hd .more .control-next",
            mainCell:".xm-carousel-list",
            autoPage:true,
            effect:"left",
            autoPlay:false,
            vis:5,
            scroll:5,
            trigger:"click",
            pnLoop:false
        });
    }

    //产品划过效果
    $('.brick-item').hover(function(){
        $(this).addClass('brick-item-active');
    },function(){
        $(this).removeClass('brick-item-active');
    })    

    //tab切换
    $(".J_itemBox").slide({
        trigger:"click",
        titCell:'.tab-list li',
        titOnClassName:"tab-active",
        mainCell:'.tab-container',
    });


})