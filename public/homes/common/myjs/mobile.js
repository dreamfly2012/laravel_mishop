/* 米商城 移动端导航 (mobile.js)
   依赖: layout 中的 .m-bar / .m-drawer / .m-mask 标记
   汉堡按钮: 桌面隐藏, ≤1024 出现在顶栏右侧, ≤767 出现在移动导航栏
   分类抽屉内容由 Blade 服务端渲染, 本文件只负责交互 */
jQuery(function ($) {
    var $drawer = $('#J_mobileDrawer');
    var $mask = $('.m-mask');
    var animating = false;

    // 有移动栏时给 body 顶部留白 (仅 ≤767 媒体查询里生效)
    if ($('.m-bar').length) {
        document.body.classList.add('has-mbar');
    }
    // 搜索页筛选条: 窄屏默认折叠 3 行, 点"更多"展开 (样式见 responsive.css .filter-list-wrap-toggled)
    $(document).on('click', '.J_filterToggle', function (e) {
        e.preventDefault();
        $(this).closest('.filter-list-wrap').toggleClass('filter-list-wrap-toggled');
    });

    if (!$drawer.length) return;

    function closeDrawer() {
        if (animating) return;
        animating = true;
        $drawer.removeClass('open');
        $mask.removeClass('open');
        $('.J_mBurger').removeClass('open');
        setTimeout(function () {
            $drawer.removeClass('show');
            $mask.removeClass('show');
            $('body').css('overflow', '');
            animating = false;
        }, 260);
    }

    function openDrawer($btn) {
        $drawer.addClass('show');
        $mask.addClass('show');
        $('body').css('overflow', 'hidden');
        // 下一个动画帧再滑入, 保证 transition 生效
        window.requestAnimationFrame(function () {
            $drawer.addClass('open');
        });
        if ($btn) $btn.addClass('open');
    }

    $('.J_mBurger').on('click', function () {
        $drawer.hasClass('open') ? closeDrawer() : openDrawer($(this));
    });

    $mask.on('click', closeDrawer);
    $('.J_mClose').on('click', closeDrawer);

    // 分类手风琴: 点主分类展开子分类 (不跳转), 点子分类跳转
    $drawer.on('click', '.m-cate-item > a.hd', function (e) {
        e.preventDefault();
        var $li = $(this).closest('.m-cate-item');
        $li.toggleClass('open');
        if ($li.hasClass('open')) {
            $li.siblings('.m-cate-item').removeClass('open');
        }
    });

    // 点击抽屉内跳转链接时自动收起 (排除分类手风琴头)
    $drawer.on('click', 'a[href]', function () {
        if (!$(this).hasClass('hd')) closeDrawer();
    });

    // Esc 关闭
    $(document).on('keydown', function (e) {
        if (e.keyCode === 27 && $drawer.hasClass('open')) closeDrawer();
    });

    // 顶栏购物车角标联动: mobile 版徽标不显示书名号括号
    if (typeof window.syncCartNum === 'function') {
        var origSync = window.syncCartNum;
        window.syncCartNum = function (count) {
            origSync(count);
            var n = parseInt(count, 10) || 0;
            var $badge = $('.m-cart-num');
            if ($badge.length) {
                n > 0 ? $badge.text(n).show() : $badge.hide();
            }
        };
    }
});
