<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Home;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| 前台 (来源: 原 app/Http/routes.php, 原 Route::controller 隐式路由全部显式化)
|--------------------------------------------------------------------------
| URL 依据视图/JS 实际请求写死; 隐式控制器规则: 方法名(去动词前缀)转小写,
| 驼峰词之间加 `-` (如 getAllTotal -> all-total)。
*/

Route::get('/', [Home\IndexController::class, 'index']);

// 登录/注册/找回密码
Route::get('/login', [Home\LoginController::class, 'getIndex']);
Route::post('/login', [Home\LoginController::class, 'postIndex']);
Route::get('/login/register', [Home\LoginController::class, 'getRegister']);
Route::post('/login/register', [Home\LoginController::class, 'postRegister']);
Route::get('/login/password', [Home\LoginController::class, 'getPassword']);
Route::post('/login/password', [Home\LoginController::class, 'postPassword']);
Route::get('/login/repass', [Home\LoginController::class, 'getRepass']);
Route::post('/login/repass', [Home\LoginController::class, 'postRepass']);
Route::get('/login/captcha', [Home\LoginController::class, 'getCaptcha']);

// 商品列表/搜索/详情 (原 list_/List_Search 方法显式映射)
Route::get('/list', [Home\ListController::class, 'list_']);
Route::get('/list_search', [Home\ListController::class, 'List_Search']);
Route::get('/detail', [Home\ListController::class, 'detail']);

// 帮助页
Route::get('/help/article', [Home\HelpController::class, 'getArticle']);

// 评论
Route::get('/comment', [Home\CommentController::class, 'comment']);

// 购物车 (原代码未挂 home 中间件, 保持一致)
Route::get('/cart', [Home\CartController::class, 'index']);
Route::get('/cart/addproduct', [Home\CartController::class, 'getAddproduct']);
Route::post('/cart/addproduct', [Home\CartController::class, 'getAddproduct']);
Route::get('/cart/all-total', [Home\CartController::class, 'getAllTotal']);
Route::get('/cart/delcart', [Home\CartController::class, 'getDelcart']);
Route::get('/cart/ajaxaddcart', [Home\CartController::class, 'ajaxAddCart']);
Route::post('/cart/ajaxaddcart', [Home\CartController::class, 'ajaxAddCart']);
Route::get('/cart/init', [Home\CartController::class, 'getInit']);

// 商品收藏 (原 ECShop 遗留 user.php?act=collect; 未登录提示跳登录)
Route::get('/collect', [Home\CollectController::class, 'getIndex']);

Route::group(['middleware' => 'home'], function () {
    // 订单
    Route::get('/order/confirm', [Home\OrderController::class, 'confirm']);
    Route::post('/order/confirm', [Home\OrderController::class, 'postConfirm']);
    Route::post('/order/create', [Home\OrderController::class, 'postCreate']);
    Route::get('/order/pay', [Home\OrderController::class, 'getPay']);
    Route::get('/order/payfor', [Home\OrderController::class, 'getPayfor']);
    Route::get('/order/changeStatus', [Home\OrderController::class, 'changeStatus']);

    // 收货地址
    Route::get('/address/add', [Home\AddressController::class, 'getAdd']);
    Route::get('/address/del', [Home\AddressController::class, 'getDel']);

    // 个人中心
    Route::get('/user/order', [Home\UserController::class, 'getOrder']);
    Route::get('/user/packin', [Home\UserController::class, 'getPackin']);
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
Route::post('/admin/login/login', [Admin\LoginController::class, 'postLogin']);
Route::get('/admin/login/outlogin', [Admin\LoginController::class, 'getOutLogin']);
Route::get('/admin/login/out-login', [Admin\LoginController::class, 'getOutLogin']);

Route::group(['prefix' => 'admin', 'middleware' => 'login'], function () {
    Route::get('/', [Admin\AdminController::class, 'index']);

    // 后台用户管理
    Route::get('/user', [Admin\UserController::class, 'getIndex']);
    Route::get('/user/add', [Admin\UserController::class, 'getAdd']);
    Route::post('/user/insert', [Admin\UserController::class, 'postInsert']);
    Route::get('/user/edit', [Admin\UserController::class, 'getEdit']);
    Route::post('/user/update', [Admin\UserController::class, 'postUpdate']);
    Route::get('/user/delete', [Admin\UserController::class, 'getDelete']);

    // 分类管理
    Route::get('/cate', [Admin\CateController::class, 'getIndex']);
    Route::get('/cate/add', [Admin\CateController::class, 'getAdd']);
    Route::post('/cate/insert', [Admin\CateController::class, 'postInsert']);
    Route::get('/cate/edit', [Admin\CateController::class, 'getEdit']);
    Route::post('/cate/update', [Admin\CateController::class, 'postUpdate']);
    Route::get('/cate/delete', [Admin\CateController::class, 'getDelete']);

    // 商品管理 (prefix 与视图一致: /admin/good/*)
    Route::get('/good', [Admin\GoodsController::class, 'getIndex']);
    Route::get('/good/add', [Admin\GoodsController::class, 'getAdd']);
    Route::post('/good/insert', [Admin\GoodsController::class, 'postInsert']);
    Route::get('/good/edit', [Admin\GoodsController::class, 'getEdit']);
    Route::post('/good/update', [Admin\GoodsController::class, 'postUpdate']);
    Route::get('/good/delete', [Admin\GoodsController::class, 'getDelete']);

    // SKU 管理
    Route::get('/sku', [Admin\SkuController::class, 'getIndex']);
    Route::get('/sku/add', [Admin\SkuController::class, 'getAdd']);
    Route::get('/sku/edit', [Admin\SkuController::class, 'getEdit']);
    Route::get('/sku/insert', [Admin\SkuController::class, 'getInsert']);
    Route::post('/sku/insert', [Admin\SkuController::class, 'postInsert']);
    Route::get('/sku/ajax-edit', [Admin\SkuController::class, 'getAjaxEdit']);
    Route::get('/sku/ajaxedit', [Admin\SkuController::class, 'getAjaxEdit']);
    Route::post('/sku/update', [Admin\SkuController::class, 'postUpdate']);
    Route::get('/sku/ajax-delete', [Admin\SkuController::class, 'getAjaxDelete']);
    Route::get('/sku/ajaxdelete', [Admin\SkuController::class, 'getAjaxDelete']);
    Route::get('/sku/delete', [Admin\SkuController::class, 'getDelete']);

    // 评论管理
    Route::get('/comment', [Admin\CommentController::class, 'getIndex']);
    Route::get('/comment/add', [Admin\CommentController::class, 'getAdd']);
    Route::post('/comment/insert', [Admin\CommentController::class, 'postInsert']);
    Route::get('/comment/edit', [Admin\CommentController::class, 'getEdit']);
    Route::post('/comment/update', [Admin\CommentController::class, 'postUpdate']);
    Route::get('/comment/delete', [Admin\CommentController::class, 'getDelete']);

    // 首页展示配置 (导航/明星单品)
    Route::get('/indexPage/navadd', [Admin\IndexController::class, 'getNavadd']);
    Route::post('/indexPage/navadd', [Admin\IndexController::class, 'postNavadd']);
    Route::get('/indexPage/navlist', [Admin\IndexController::class, 'getNavlist']);
    Route::get('/indexPage/ajaxnavupdate', [Admin\IndexController::class, 'getAjaxnavupdate']);
    Route::get('/indexPage/navedit', [Admin\IndexController::class, 'getNavedit']);
    Route::post('/indexPage/navedit', [Admin\IndexController::class, 'postNavedit']);
    Route::get('/indexPage/navdel', [Admin\IndexController::class, 'getNavdel']);
    Route::get('/indexPage/ajaxcreate', [Admin\IndexController::class, 'getAjaxcreate']);
    Route::get('/indexPage/staradd', [Admin\IndexController::class, 'getStaradd']);
    Route::post('/indexPage/staradd', [Admin\IndexController::class, 'postStaradd']);
    Route::get('/indexPage/sort', [Admin\IndexController::class, 'getSort']);
    Route::get('/indexPage/sortnav', [Admin\IndexController::class, 'getSortnav']);

    // 帮助中心管理
    Route::get('/help/cateadd', [Admin\HelpController::class, 'getCateadd']);
    Route::post('/help/cateadd', [Admin\HelpController::class, 'postCateadd']);
    Route::get('/help/cate', [Admin\HelpController::class, 'getCate']);
    Route::get('/help/ajaxcatestatus', [Admin\HelpController::class, 'getAjaxcatestatus']);
    Route::get('/help/ajaxcateshow', [Admin\HelpController::class, 'getAjaxcateshow']);
    Route::get('/help/cateedit', [Admin\HelpController::class, 'getCateedit']);
    Route::post('/help/cateedit', [Admin\HelpController::class, 'postCateedit']);
    Route::get('/help/catedel', [Admin\HelpController::class, 'getCatedel']);
    Route::get('/help/add', [Admin\HelpController::class, 'getAdd']);
    Route::post('/help/add', [Admin\HelpController::class, 'postAdd']);
    Route::get('/help/article', [Admin\HelpController::class, 'getArticle']);
    Route::get('/help/ajaxstatus', [Admin\HelpController::class, 'getAjaxstatus']);
    Route::get('/help/artedit', [Admin\HelpController::class, 'getArtedit']);
    Route::post('/help/artedit', [Admin\HelpController::class, 'postArtedit']);
    Route::get('/help/artdel', [Admin\HelpController::class, 'getArtdel']);

    // 订单管理
    Route::get('/order', [Admin\OrderController::class, 'getIndex']);
    Route::get('/order/edit', [Admin\OrderController::class, 'getEdit']);
    Route::post('/order/edit', [Admin\OrderController::class, 'postEdit']);
    Route::get('/order/detail', [Admin\OrderController::class, 'getDetail']);
});
