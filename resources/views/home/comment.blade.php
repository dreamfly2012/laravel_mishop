<!DOCTYPE html>
<html lang="zh-CN" xml:lang="zh-CN">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=Edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>发表评价 - 小米商城</title>
    <link rel="icon" href="/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="/homes/common/css/base.min.css">
    {{-- 本页是独立模板(不继承 layout.index), 需要自带 jQuery --}}
    <script src="/homes/common/myjs/jquery.min.js"></script>
    <script src="/homes/common/lib/jquery.raty.min.js"></script>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-width: 0;
            background: #f5f5f5;
            color: #333;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "PingFang SC",
                "Hiragino Sans GB", "Microsoft Yahei", "Helvetica Neue", Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        .cm-header { background: #fff; border-bottom: 1px solid #e0e0e0; }
        .cm-header-inner {
            max-width: 900px; margin: 0 auto; padding: 0 16px; height: 56px;
            display: flex; align-items: center;
        }
        .cm-header a { color: #757575; text-decoration: none; font-size: 14px; }
        .cm-header a:hover { color: #ff6700; }
        .cm-header .cm-title { margin: 0 auto; font-size: 16px; font-weight: 400; color: #333; }
        .cm-header .cm-back { width: 64px; }
        .cm-header .cm-placeholder { width: 64px; }
        .cm-wrap { max-width: 900px; margin: 24px auto 60px; padding: 0 16px; }
        .cm-card {
            background: #fff; border-radius: 8px; padding: 28px 32px 32px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, .06);
        }
        .cm-card h2 { margin: 0 0 20px; font-size: 20px; font-weight: 400; }
        .cm-goods {
            display: flex; align-items: center; gap: 16px;
            padding-bottom: 20px; border-bottom: 1px solid #f0f0f0;
        }
        .cm-goods-img {
            width: 96px; height: 96px; flex: 0 0 96px; border: 1px solid #f0f0f0;
            border-radius: 6px; overflow: hidden; display: flex;
            align-items: center; justify-content: center;
        }
        .cm-goods-img img { max-width: 100%; max-height: 100%; display: block; }
        .cm-goods-info { min-width: 0; }
        .cm-goods-name {
            margin: 0 0 8px; font-size: 16px; line-height: 1.5;
            overflow: hidden; text-overflow: ellipsis; display: -webkit-box;
            -webkit-line-clamp: 2; -webkit-box-orient: vertical;
        }
        .cm-goods-name a { color: #333; text-decoration: none; }
        .cm-goods-name a:hover { color: #ff6700; }
        .cm-goods-meta { margin: 0; font-size: 13px; line-height: 1.9; color: #757575; }
        .cm-goods-meta b { color: #ff6700; font-weight: 400; }
        .cm-rate-row {
            display: flex; align-items: center; flex-wrap: wrap; gap: 10px;
            padding: 22px 0 6px;
        }
        .cm-rate-row .cm-label { font-size: 14px; color: #757575; }
        .cm-rate-row .hint { font-size: 14px; color: #ff6700; }
        .cm-textarea {
            display: block; width: 100%; min-height: 180px; margin: 12px 0 0;
            padding: 14px 16px; border: 1px solid #e0e0e0; border-radius: 6px;
            font-size: 14px; line-height: 1.8; color: #333; resize: vertical;
            outline: none; transition: border-color .2s;
        }
        .cm-textarea:focus { border-color: #ff6700; }
        .cm-bar { display: flex; justify-content: flex-end; margin-top: 24px; }
        .cm-bar .btn {
            min-width: 160px; height: 44px; line-height: 44px; padding: 0 28px;
            border: 0; border-radius: 4px; background: #ff6700; color: #fff;
            font-size: 15px; cursor: pointer; transition: background-color .2s;
        }
        .cm-bar .btn:hover { background: #e55c00; }

        @media (max-width: 767px) {
            .cm-header-inner { height: 50px; padding: 0 12px; }
            .cm-wrap { margin: 12px auto 40px; padding: 0 10px; }
            .cm-card { padding: 20px 16px 22px; border-radius: 6px; }
            .cm-card h2 { font-size: 17px; margin-bottom: 16px; }
            .cm-goods { gap: 12px; padding-bottom: 16px; }
            .cm-goods-img { width: 76px; height: 76px; flex-basis: 76px; }
            .cm-goods-name { font-size: 14px; }
            .cm-goods-meta { font-size: 12px; }
            .cm-rate-row { padding: 16px 0 4px; }
            .cm-textarea { min-height: 140px; font-size: 13px; }
            .cm-bar { margin-top: 18px; }
            .cm-bar .btn { width: 100%; min-width: 0; }
        }
    </style>
</head>
<body>
<div class="cm-header">
    <div class="cm-header-inner">
        <a class="cm-back" href="/user/order">&lt; 返回</a>
        <h1 class="cm-title">发表评价</h1>
        <span class="cm-placeholder"></span>
    </div>
</div>
<div class="cm-wrap">
    <form action="/comment/insert" method="post">
        {{ csrf_field() }}
        <input type="hidden" name="good_id" value="{{ $sku->good->id }}">
        <input type="hidden" name="order_id" value="{{ $order_id }}">
        <div class="cm-card">
            <h2>亲，留个评价吧！</h2>
            <div class="cm-goods">
                <div class="cm-goods-img">
                    <a href="/detail/{{ $sku->good_id }}">
                        <img src="{{ $sku->img }}" alt="">
                    </a>
                </div>
                <div class="cm-goods-info">
                    <p class="cm-goods-name">
                        <a href="/detail/{{ $sku->good_id }}">{{ $sku->title }}</a>
                    </p>
                    <p class="cm-goods-meta">
                        价格：<b>{{ $sku->price }} 元</b><br>
                        型号：{{ $sku->attr }}<br>
                        颜色：{{ $sku->color }}
                    </p>
                </div>
            </div>
            <div class="cm-rate-row">
                <span class="cm-label">商品评分</span>
                <div id="precision-demo" class="target-demo"></div>
                <div id="precision-hint" class="hint"></div>
            </div>
            <textarea class="cm-textarea" name="content" placeholder="外形如何，使用如何，写下您的感受吧..."></textarea>
            <div class="cm-bar">
                <button type="submit" class="btn" id="J_checkoutToPay">发表评论</button>
            </div>
        </div>
    </form>
</div>
<script type="text/javascript">
    $(function () {
        $.fn.raty.defaults.path = '/homes/common/demo/img';
        $('#precision-demo').raty({
            path: '/homes/common/demo/img',
            cancelOff: 'cancel-off-big.png',
            cancelOn: 'cancel-on-big.png',
            size: 24,
            starHalf: 'star-half-big.png',
            starOff: 'star-off-big.png',
            starOn: 'star-on-big.png',
            target: '#precision-hint',
            cancel: true,
            targetKeep: true,
            precision: true
        });
    });
</script>
</body>
</html>
