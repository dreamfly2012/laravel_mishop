<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Home;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| URL 统一规范
|--------------------------------------------------------------------------
| - 路径全小写 + 连字符 (list-search / change-status / index-page)
| - 资源 id 一律走路径参数 (/detail/12), 不再用 ?id=12
| - 搜索词/筛选/分页保留 query (?kWord= / ?s= / ?filter= / ?page=)
| - 前台旧地址在对应控制器内 301 到新地址, 后台旧地址不再提供
*/

/*
|--------------------------------------------------------------------------
| 前台
|--------------------------------------------------------------------------
*/

Route::get('/', [Home\IndexController::class, 'index']);

// 登录/注册/找回密码
Route::get('/login', [Home\LoginController::class, 'getIndex']);
Route::post('/login', [Home\LoginController::class, 'postIndex'])->middleware('throttle:60,1');
Route::get('/login/register', [Home\LoginController::class, 'getRegister']);
Route::post('/login/register', [Home\LoginController::class, 'postRegister']);
Route::get('/login/password', [Home\LoginController::class, 'getPassword']);
Route::post('/login/password', [Home\LoginController::class, 'postPassword']);
Route::get('/login/repass', [Home\LoginController::class, 'getRepass']);
Route::post('/login/repass', [Home\LoginController::class, 'postRepass']);
Route::get('/login/captcha', [Home\LoginController::class, 'getCaptcha']);

// 商品列表/搜索/详情
Route::get('/list/{id?}', [Home\ListController::class, 'list_'])
    ->whereNumber('id')->name('list');
Route::get('/list-search', [Home\ListController::class, 'List_Search'])->name('list.search');
Route::get('/detail/{id}', [Home\ListController::class, 'detail'])
    ->whereNumber('id')->name('detail');
Route::get('/detail', [Home\ListController::class, 'detail']); // 旧 ?id= 301

// 帮助页
Route::get('/help/article/{id}', [Home\HelpController::class, 'getArticle'])
    ->whereNumber('id');
Route::get('/help/article', [Home\HelpController::class, 'getArticle']); // 旧 ?id= 301

// 评论
Route::get('/comment/{id}', [Home\CommentController::class, 'comment'])
    ->whereNumber('id');
Route::get('/comment', [Home\CommentController::class, 'comment']); // 旧 ?id= 301

// 购物车 (原代码未挂 home 中间件, 保持一致)
Route::get('/cart', [Home\CartController::class, 'index']);
Route::match(['get', 'post'], '/cart/add/{id}', [Home\CartController::class, 'getAddproduct'])
    ->whereNumber('id');
Route::get('/cart/addproduct', [Home\CartController::class, 'getAddproduct']); // 旧 ?id= 兼容
Route::get('/cart/all-total', [Home\CartController::class, 'getAllTotal']);
Route::get('/cart/delete/{id}', [Home\CartController::class, 'getDelcart'])
    ->whereNumber('id');
Route::get('/cart/delcart', [Home\CartController::class, 'getDelcart']); // 旧 ?id= 兼容
Route::match(['get', 'post'], '/cart/ajax-add', [Home\CartController::class, 'ajaxAddCart']);

// 商品收藏 (原 ECShop 遗留 user.php?act=collect; 未登录提示跳登录)
Route::get('/collect/{id}', [Home\CollectController::class, 'getIndex'])
    ->whereNumber('id');
Route::get('/collect', [Home\CollectController::class, 'getIndex']); // 旧 ?id= 兼容

Route::group(['middleware' => 'home'], function () {
    // 订单
    Route::get('/order/confirm', [Home\OrderController::class, 'confirm']);
    Route::post('/order/confirm', [Home\OrderController::class, 'postConfirm']);
    Route::post('/order/create', [Home\OrderController::class, 'postCreate']);
    Route::get('/order/pay/{id}', [Home\OrderController::class, 'getPay'])
        ->whereNumber('id');
    Route::get('/order/pay', [Home\OrderController::class, 'getPay']); // 旧 ?id= 301
    Route::get('/order/payfor', [Home\OrderController::class, 'getPayfor']);
    Route::get('/pay/gateway/{id}', [Home\OrderController::class, 'gateway'])
        ->whereNumber('id');
    Route::post('/pay/notify/{id}', [Home\OrderController::class, 'notify'])
        ->whereNumber('id');
    Route::get('/order/change-status', [Home\OrderController::class, 'changeStatus']);
    Route::get('/order/changeStatus', function () {
        return redirect('/order/change-status'.(request()->getQueryString() ? '?'.request()->getQueryString() : ''), 301);
    });

    // 收货地址
    Route::get('/address/add', [Home\AddressController::class, 'getAdd']);
    Route::get('/address/delete/{id}', [Home\AddressController::class, 'getDel'])
        ->whereNumber('id');
    Route::get('/address/del', [Home\AddressController::class, 'getDel']); // 旧 ?id= 兼容

    // 个人中心
    Route::get('/user/order', [Home\UserController::class, 'getOrder']);
    Route::get('/user/packin/{id}', [Home\UserController::class, 'getPackin'])
        ->whereNumber('id');
    Route::get('/user/packin', [Home\UserController::class, 'getPackin']); // 旧 ?id= 301
    Route::get('/user/address', [Home\UserController::class, 'getAddress']);
    Route::get('/user/comment', [Home\UserController::class, 'getComment']);
    Route::get('/user/logout', [Home\UserController::class, 'getLogout']);

    // 发表评价 (需登录)
    Route::post('/comment/insert', [Home\CommentController::class, 'insert']);
});

/*
|--------------------------------------------------------------------------
| 后台
|--------------------------------------------------------------------------
| 中间件 'login' 要求 session('user_id')==1。
*/

Route::get('/admin/login', [Admin\LoginController::class, 'getIndex']);
Route::post('/admin/login', [Admin\LoginController::class, 'postLogin'])->middleware('throttle:10,1');
Route::post('/admin/login/login', [Admin\LoginController::class, 'postLogin'])->middleware('throttle:10,1'); // 旧地址兼容
Route::get('/admin/logout', [Admin\LoginController::class, 'getOutLogin']);
Route::get('/admin/login/outlogin', [Admin\LoginController::class, 'getOutLogin']); // 旧地址兼容
Route::get('/admin/login/out-login', [Admin\LoginController::class, 'getOutLogin']); // 旧地址兼容

Route::group(['prefix' => 'admin', 'middleware' => 'login'], function () {
    Route::get('/', [Admin\AdminController::class, 'index']);

    // 后台用户管理
    Route::get('/user', [Admin\UserController::class, 'getIndex']);
    Route::get('/user/add', [Admin\UserController::class, 'getAdd']);
    Route::post('/user/insert', [Admin\UserController::class, 'postInsert']);
    Route::get('/user/edit/{id}', [Admin\UserController::class, 'getEdit'])
        ->whereNumber('id');
    Route::post('/user/update', [Admin\UserController::class, 'postUpdate']);
    Route::get('/user/delete/{id}', [Admin\UserController::class, 'getDelete'])
        ->whereNumber('id');

    // 分类管理
    Route::get('/cate', [Admin\CateController::class, 'getIndex']);
    Route::get('/cate/add', [Admin\CateController::class, 'getAdd']);
    Route::post('/cate/insert', [Admin\CateController::class, 'postInsert']);
    Route::get('/cate/edit/{id}', [Admin\CateController::class, 'getEdit'])
        ->whereNumber('id');
    Route::post('/cate/update', [Admin\CateController::class, 'postUpdate']);
    Route::get('/cate/delete/{id}', [Admin\CateController::class, 'getDelete'])
        ->whereNumber('id');

    // 商品管理 (prefix 与视图一致: /admin/good/*)
    Route::get('/good', [Admin\GoodsController::class, 'getIndex']);
    Route::get('/good/add', [Admin\GoodsController::class, 'getAdd']);
    Route::post('/good/insert', [Admin\GoodsController::class, 'postInsert']);
    Route::get('/good/edit/{id}', [Admin\GoodsController::class, 'getEdit'])
        ->whereNumber('id');
    Route::post('/good/update', [Admin\GoodsController::class, 'postUpdate']);
    Route::get('/good/delete/{id}', [Admin\GoodsController::class, 'getDelete'])
        ->whereNumber('id');

    // SKU 管理
    Route::get('/sku', [Admin\SkuController::class, 'getIndex']);
    Route::get('/sku/add/{goodId}', [Admin\SkuController::class, 'getAdd'])
        ->whereNumber('goodId');
    Route::get('/sku/edit/{id}', [Admin\SkuController::class, 'getEdit'])
        ->whereNumber('id');
    Route::get('/sku/insert', [Admin\SkuController::class, 'getInsert']);
    Route::post('/sku/insert', [Admin\SkuController::class, 'postInsert']);
    Route::get('/sku/ajax-edit', [Admin\SkuController::class, 'getAjaxEdit']);
    Route::post('/sku/update', [Admin\SkuController::class, 'postUpdate']);
    Route::get('/sku/ajax-delete', [Admin\SkuController::class, 'getAjaxDelete']);
    Route::get('/sku/delete/{id}', [Admin\SkuController::class, 'getDelete'])
        ->whereNumber('id');

    // 评论管理
    Route::get('/comment', [Admin\CommentController::class, 'getIndex']);
    Route::get('/comment/add', [Admin\CommentController::class, 'getAdd']);
    Route::post('/comment/insert', [Admin\CommentController::class, 'postInsert']);
    Route::get('/comment/edit/{id}', [Admin\CommentController::class, 'getEdit'])
        ->whereNumber('id');
    Route::post('/comment/update', [Admin\CommentController::class, 'postUpdate']);
    Route::get('/comment/delete/{id}', [Admin\CommentController::class, 'getDelete'])
        ->whereNumber('id');

    // 首页展示配置 (导航/明星单品)
    Route::get('/index-page/nav-add', [Admin\IndexController::class, 'getNavadd']);
    Route::post('/index-page/nav-add', [Admin\IndexController::class, 'postNavadd']);
    Route::get('/index-page/nav-list', [Admin\IndexController::class, 'getNavlist']);
    Route::get('/index-page/nav-update/{id}', [Admin\IndexController::class, 'getAjaxnavupdate'])
        ->whereNumber('id');
    Route::get('/index-page/nav-edit/{id}', [Admin\IndexController::class, 'getNavedit'])
        ->whereNumber('id');
    Route::post('/index-page/nav-edit', [Admin\IndexController::class, 'postNavedit']);
    Route::get('/index-page/nav-delete/{id}', [Admin\IndexController::class, 'getNavdel'])
        ->whereNumber('id');
    Route::get('/index-page/create', [Admin\IndexController::class, 'getAjaxcreate']);
    Route::get('/index-page/star-add', [Admin\IndexController::class, 'getStaradd']);
    Route::post('/index-page/star-add', [Admin\IndexController::class, 'postStaradd']);
    Route::get('/index-page/sort', [Admin\IndexController::class, 'getSort']);
    Route::get('/index-page/sort-nav', [Admin\IndexController::class, 'getSortnav']);

    // 首页展示配置 (轮播图 / 促销小图 / 频道入口)
    Route::get('/banner/list', [Admin\BannerController::class, 'getList']);
    Route::get('/banner/add', [Admin\BannerController::class, 'getAdd']);
    Route::post('/banner/add', [Admin\BannerController::class, 'postAdd']);
    Route::get('/banner/edit/{id}', [Admin\BannerController::class, 'getEdit'])
        ->whereNumber('id');
    Route::post('/banner/edit', [Admin\BannerController::class, 'postEdit']);
    Route::get('/banner/delete/{id}', [Admin\BannerController::class, 'getDelete'])
        ->whereNumber('id');
    Route::get('/banner/update/{id}', [Admin\BannerController::class, 'getUpdate'])
        ->whereNumber('id');

    // 帮助中心管理
    Route::get('/help/cate-add', [Admin\HelpController::class, 'getCateadd']);
    Route::post('/help/cate-add', [Admin\HelpController::class, 'postCateadd']);
    Route::get('/help/cate', [Admin\HelpController::class, 'getCate']);
    Route::get('/help/cate-status/{id}', [Admin\HelpController::class, 'getAjaxcatestatus'])
        ->whereNumber('id');
    Route::get('/help/cate-show/{id}', [Admin\HelpController::class, 'getAjaxcateshow'])
        ->whereNumber('id');
    Route::get('/help/cate-edit/{id}', [Admin\HelpController::class, 'getCateedit'])
        ->whereNumber('id');
    Route::post('/help/cate-edit', [Admin\HelpController::class, 'postCateedit']);
    Route::get('/help/cate-delete/{id}', [Admin\HelpController::class, 'getCatedel'])
        ->whereNumber('id');
    Route::get('/help/add/{pid?}', [Admin\HelpController::class, 'getAdd'])
        ->whereNumber('pid');
    Route::post('/help/add', [Admin\HelpController::class, 'postAdd']);
    Route::get('/help/article/{pid?}', [Admin\HelpController::class, 'getArticle'])
        ->whereNumber('pid');
    Route::get('/help/article-status/{id}', [Admin\HelpController::class, 'getAjaxstatus'])
        ->whereNumber('id');
    Route::get('/help/article-edit/{id}', [Admin\HelpController::class, 'getArtedit'])
        ->whereNumber('id');
    Route::post('/help/article-edit', [Admin\HelpController::class, 'postArtedit']);
    Route::get('/help/article-delete/{id}', [Admin\HelpController::class, 'getArtdel'])
        ->whereNumber('id');

    // 订单管理
    Route::get('/order', [Admin\OrderController::class, 'getIndex']);
    Route::get('/order/edit/{id}', [Admin\OrderController::class, 'getEdit'])
        ->whereNumber('id');
    Route::post('/order/edit', [Admin\OrderController::class, 'postEdit']);
    Route::get('/order/detail/{id}', [Admin\OrderController::class, 'getDetail'])
        ->whereNumber('id');
});
