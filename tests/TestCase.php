<?php

namespace Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * 测试基座。
 *
 * phpunit.xml 里使用 sqlite :memory:，这里手写一份与 xm.sql 对齐的测试表结构，
 * 每个测试用例都会拿到一个全新的内存库，不会读写开发库 xm。
 *
 * 之所以不跑 database/migrations：项目里的迁移只有框架默认三张表，
 * 真正的业务表都在 xm.sql 中，直接 migrate 会和演示库冲突。
 */
abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema();
    }

    protected function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('username');
            $table->string('phone')->default('');
            $table->string('email');
            $table->string('password');
            $table->string('level')->default('0');
            $table->string('pic')->default('');
            $table->integer('status')->default(0);
            $table->timestamps();
        });

        Schema::create('cates', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('img')->default('');
            $table->string('pid')->default('0');
            $table->string('path')->default('0');
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('goods', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title');
            $table->string('sub_title')->nullable();
            $table->string('cate_id')->default('0');
            $table->decimal('price', 8, 2)->default(0);
            $table->text('content')->nullable();
            $table->text('img')->nullable();
            $table->text('showImg')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('skus', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('good_id');
            $table->string('title');
            $table->string('attr')->default('');
            $table->string('color')->default('');
            $table->string('falsePrice')->default('');
            $table->string('price');
            $table->string('img')->default('');
            $table->text('info')->nullable();
            $table->string('stock')->default('0');
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('carts', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id');
            $table->integer('sku_id');
            $table->integer('goods_id');
            $table->string('goods_name');
            $table->string('goods_attr')->default('');
            $table->integer('goods_num');
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id');
            $table->integer('order_status');
            $table->integer('pay_status')->default(0);
            $table->string('order_num');
            $table->integer('pack_fee')->default(0);
            $table->integer('pack_time')->default(0);
            $table->decimal('total_price', 10, 2)->default(0);
            $table->string('consignee');
            $table->string('province');
            $table->string('city');
            $table->string('district');
            $table->string('address');
            $table->string('phone');
            $table->timestamps();
        });

        Schema::create('order_goods', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('order_id');
            $table->integer('sku_id');
            $table->decimal('price', 10, 2);
            $table->integer('num');
            $table->string('goods_name');
            $table->string('goods_attr')->default('');
            $table->string('color')->default('');
            $table->timestamps();
        });

        Schema::create('addresses', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id');
            $table->string('consignee');
            $table->string('tel');
            $table->string('province');
            $table->string('city');
            $table->string('district');
            $table->string('address');
            $table->string('postcode')->default('');
            $table->timestamps();
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->increments('id');
            $table->text('content');
            $table->integer('good_id');
            $table->integer('user_id');
            $table->integer('pid')->default(0);
            $table->string('path')->default('0');
            $table->string('img')->default('');
            $table->string('star')->default('0');
            $table->string('useless')->default('');
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('collects', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id');
            $table->unsignedInteger('good_id');
            $table->timestamps();
            $table->unique(['user_id', 'good_id']);
        });

        Schema::create('help_cates', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->integer('pid')->default(0);
            $table->integer('show')->default(1);
            $table->string('path')->default('0');
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('help_articles', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('help_cate_id');
            $table->string('title');
            $table->text('content');
            $table->integer('status')->default(1);
            $table->string('url')->default('');
            $table->timestamps();
        });

        Schema::create('navigations', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nav_name');
            $table->string('nav_url')->default('');
            $table->string('sku_id_group')->default('');
            $table->string('status')->default('1');
        });

        Schema::create('banners', function (Blueprint $table) {
            $table->increments('id');
            $table->string('position')->default('carousel');
            $table->string('title')->nullable();
            $table->string('image')->nullable();
            $table->string('icon')->nullable();
            $table->unsignedInteger('good_id')->nullable();
            $table->string('url')->nullable();
            $table->integer('sort')->default(0);
            $table->integer('status')->default(1);
            $table->timestamps();
        });
    }

    // ---------------------------------------------------------------------
    // 测试数据工厂
    // ---------------------------------------------------------------------

    /**
     * 创建后台管理员（id 固定为 1，LoginMiddleware 依赖这一点）。
     */
    protected function makeAdmin(array $overrides = []): int
    {
        DB::table('users')->where('id', 1)->delete();

        $data = array_merge([
            'id' => 1,
            'username' => 'admin',
            'phone' => '18222222222',
            'email' => 'admin@example.com',
            'password' => Hash::make('iloveyou'),
            'level' => '0',
            'pic' => '/image/upload/user.png',
            'status' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);

        DB::table('users')->insert($data);

        return 1;
    }

    protected function makeUser(array $overrides = []): int
    {
        $data = array_merge([
            'username' => 'user'.mt_rand(100000, 999999),
            'phone' => '13800000000',
            'email' => 'user'.mt_rand(100000, 999999).'@example.com',
            'password' => Hash::make('secret123'),
            'level' => '0',
            'pic' => '/image/upload/user.png',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);

        return (int) DB::table('users')->insertGetId($data);
    }

    protected function makeCate(array $overrides = []): int
    {
        $data = array_merge([
            'name' => '手机 平板',
            'img' => '',
            'pid' => '0',
            'path' => '0',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);

        return (int) DB::table('cates')->insertGetId($data);
    }

    protected function makeGood(array $overrides = []): int
    {
        $data = array_merge([
            'title' => 'MI/小米 测试机',
            'sub_title' => '',
            'cate_id' => '1',
            'price' => 1999.00,
            'content' => '<p>测试商品详情</p>',
            'img' => '/image/upload/mi-test.jpg,/image/upload/mi-test2.jpg',
            'showImg' => '/image/upload/mi-test.jpg',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);

        return (int) DB::table('goods')->insertGetId($data);
    }

    protected function makeSku(int $goodId, array $overrides = []): int
    {
        $data = array_merge([
            'good_id' => $goodId,
            'title' => 'MI/小米 测试机 标准版 白色',
            'attr' => '标准版',
            'color' => '白色',
            'falsePrice' => '2199.00',
            'price' => '1999.00',
            'img' => '/image/upload/mi-test.jpg',
            'info' => '<table><tr><td>品牌</td><td>小米</td></tr></table>',
            'stock' => '10',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);

        return (int) DB::table('skus')->insertGetId($data);
    }

    protected function makeAddress(int $userId, array $overrides = []): int
    {
        $data = array_merge([
            'user_id' => $userId,
            'consignee' => '王小明',
            'tel' => '13000000000',
            'province' => '北京',
            'city' => '北京市',
            'district' => '朝阳区',
            'address' => '测试路 1 号',
            'postcode' => '100000',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);

        return (int) DB::table('addresses')->insertGetId($data);
    }

    protected function makeCart(int $userId, int $skuId, int $goodId, array $overrides = []): int
    {
        $data = array_merge([
            'user_id' => $userId,
            'sku_id' => $skuId,
            'goods_id' => $goodId,
            'goods_name' => 'MI/小米 测试机 标准版 白色',
            'goods_attr' => '白色',
            'goods_num' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);

        return (int) DB::table('carts')->insertGetId($data);
    }

    protected function makeOrder(int $userId, array $overrides = []): int
    {
        $data = array_merge([
            'user_id' => $userId,
            'order_status' => 0,
            'pay_status' => 0,
            'order_num' => (string) mt_rand(1000000000, 9999999999),
            'pack_fee' => 0,
            'pack_time' => 0,
            'total_price' => 1999.00,
            'consignee' => '王小明',
            'province' => '北京',
            'city' => '北京市',
            'district' => '朝阳区',
            'address' => '测试路 1 号',
            'phone' => '13000000000',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);

        return (int) DB::table('orders')->insertGetId($data);
    }

    protected function makeOrderGoods(int $orderId, int $skuId, array $overrides = []): int
    {
        $data = array_merge([
            'order_id' => $orderId,
            'sku_id' => $skuId,
            'price' => 1999.00,
            'num' => 1,
            'goods_name' => 'MI/小米 测试机 标准版 白色',
            'goods_attr' => '标准版',
            'color' => '白色',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);

        return (int) DB::table('order_goods')->insertGetId($data);
    }

    protected function makeHelpCate(array $overrides = []): int
    {
        $data = array_merge([
            'name' => '常见问题',
            'pid' => 0,
            'show' => 1,
            'path' => '0',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);

        return (int) DB::table('help_cates')->insertGetId($data);
    }

    protected function makeHelpArticle(int $helpCateId, array $overrides = []): int
    {
        $data = array_merge([
            'help_cate_id' => $helpCateId,
            'title' => '账户注册',
            'content' => '<p>如何注册小米账号</p>',
            'status' => 1,
            'url' => '',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);

        return (int) DB::table('help_articles')->insertGetId($data);
    }

    protected function makeBanner(array $overrides = []): int
    {
        $data = array_merge([
            'position' => 'carousel',
            'title' => '轮播图',
            'image' => '/image/upload/mi-test.jpg',
            'icon' => null,
            'good_id' => 1,
            'url' => null,
            'sort' => 0,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);

        return (int) DB::table('banners')->insertGetId($data);
    }
}
