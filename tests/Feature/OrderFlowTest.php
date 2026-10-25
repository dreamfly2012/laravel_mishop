<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 下单、支付状态、收货地址与个人中心订单接口。
 */
class OrderFlowTest extends TestCase
{
    private function seedUserWithCart(): array
    {
        $userId = $this->makeUser();
        $goodId = $this->makeGood(['title' => 'MI/小米 下单测试机']);
        $skuId = $this->makeSku($goodId, ['price' => '1999.00', 'stock' => '10']);
        $cartId = $this->makeCart($userId, $skuId, $goodId, ['goods_num' => 2]);

        return [$userId, $goodId, $skuId, $cartId];
    }

    // -----------------------------------------------------------------
    // 订单确认 / 创建
    // -----------------------------------------------------------------

    public function test_confirm_requires_selected_items(): void
    {
        $userId = $this->makeUser();

        $this->withSession(['uid' => $userId])
            ->post('/order/confirm', [])
            ->assertJson(['status' => 4, 'msg' => '购物车为空']);
    }

    public function test_confirm_stores_selected_items_and_returns_redirect_path(): void
    {
        [$userId, $goodId, $skuId] = $this->seedUserWithCart();

        $response = $this->withSession(['uid' => $userId])->post('/order/confirm', [
            'listItem' => [$skuId],
        ]);

        $response->assertJson(['status' => 0, 'msg' => '/order/confirm']);
        $this->assertSame([$skuId], session('listItem'));
    }

    public function test_confirm_page_shows_address_and_goods(): void
    {
        [$userId, $goodId, $skuId] = $this->seedUserWithCart();
        $this->makeAddress($userId, ['consignee' => '张三']);

        $response = $this->withSession([
            'uid' => $userId,
            'listItem' => [$skuId],
        ])->get('/order/confirm');

        $response->assertOk();
        $response->assertSee('张三');
        $response->assertSee('MI/小米 测试机 标准版 白色');
        $response->assertSee('3,998.00');
    }

    public function test_create_order_writes_order_and_order_goods_and_clears_cart(): void
    {
        [$userId, $goodId, $skuId, $cartId] = $this->seedUserWithCart();
        $addressId = $this->makeAddress($userId, ['consignee' => '李四']);

        $response = $this->withSession([
            'uid' => $userId,
            'listItem' => [$skuId],
        ])->post('/order/create', [
            'addressId' => $addressId,
            'packFee' => 0,
            'totalPrice' => 3998.00,
        ]);

        $response->assertJson(['status' => 0]);
        $orderId = $response->json('msg');
        $this->assertIsInt($orderId);

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'user_id' => $userId,
            'consignee' => '李四',
            'total_price' => 3998.00,
        ]);
        $this->assertDatabaseHas('order_goods', [
            'order_id' => $orderId,
            'sku_id' => $skuId,
            'num' => 2,
        ]);
        $this->assertDatabaseMissing('carts', ['id' => $cartId]);
        $this->assertNull(session('listItem'));
    }

    public function test_create_order_fails_when_address_does_not_exist(): void
    {
        [$userId, $goodId, $skuId] = $this->seedUserWithCart();

        $response = $this->withSession([
            'uid' => $userId,
            'listItem' => [$skuId],
        ])->post('/order/create', [
            'addressId' => 99999,
            'packFee' => 0,
            'totalPrice' => 3998.00,
        ]);

        $response->assertJson(['status' => 1, 'msg' => '服务异常,请稍后再试']);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_goods', 0);
    }

    public function test_create_order_deducts_sku_stock(): void
    {
        [$userId, $goodId, $skuId, $cartId] = $this->seedUserWithCart();
        $addressId = $this->makeAddress($userId);

        $this->assertSame('10', \DB::table('skus')->where('id', $skuId)->value('stock'));

        $response = $this->withSession([
            'uid' => $userId,
            'listItem' => [$skuId],
        ])->post('/order/create', [
            'addressId' => $addressId,
            'packFee' => 0,
            'totalPrice' => 3998.00,
        ]);

        $response->assertJson(['status' => 0]);
        // 下单 2 件, 库存 10 - 2 = 8
        $this->assertSame('8', (string) \DB::table('skus')->where('id', $skuId)->value('stock'));
    }

    public function test_create_order_fails_when_stock_insufficient(): void
    {
        $userId = $this->makeUser();
        $goodId = $this->makeGood(['title' => 'MI/小米 库存不足测试机']);
        $skuId = $this->makeSku($goodId, ['price' => '1999.00', 'stock' => '1']);
        $cartId = $this->makeCart($userId, $skuId, $goodId, ['goods_num' => 2]);
        $addressId = $this->makeAddress($userId);

        $response = $this->withSession([
            'uid' => $userId,
            'listItem' => [$skuId],
        ])->post('/order/create', [
            'addressId' => $addressId,
            'packFee' => 0,
            'totalPrice' => 3998.00,
        ]);

        $response->assertJson(['status' => 5]);
        // 事务回滚: 不落订单, 不扣库存, 购物车保留
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_goods', 0);
        $this->assertSame('1', \DB::table('skus')->where('id', $skuId)->value('stock'));
        $this->assertDatabaseHas('carts', ['id' => $cartId]);
    }

    // -----------------------------------------------------------------
    // 支付 / 订单状态
    // -----------------------------------------------------------------

    public function test_pay_page_loads_for_pending_order(): void
    {
        $userId = $this->makeUser();
        $goodId = $this->makeGood();
        $skuId = $this->makeSku($goodId);
        $orderId = $this->makeOrder($userId, ['order_status' => 0, 'order_num' => '202610240001']);
        $this->makeOrderGoods($orderId, $skuId);

        $response = $this->withSession(['uid' => $userId])->get('/order/pay/'.$orderId);

        $response->assertOk();
        $response->assertSee('202610240001');
    }

    public function test_legacy_pay_url_redirects_permanently(): void
    {
        $userId = $this->makeUser();
        $orderId = $this->makeOrder($userId);

        $this->withSession(['uid' => $userId])
            ->get('/order/pay?id='.$orderId)
            ->assertStatus(301)
            ->assertRedirect('/order/pay/'.$orderId);
    }

    public function test_pay_page_returns_404_for_non_pending_order(): void
    {
        $userId = $this->makeUser();
        $orderId = $this->makeOrder($userId, ['order_status' => 3]);

        $this->withSession(['uid' => $userId])
            ->get('/order/pay/'.$orderId)
            ->assertNotFound();
    }

    public function test_pay_callback_marks_order_as_paid(): void
    {
        $userId = $this->makeUser();
        $orderId = $this->makeOrder($userId, ['order_status' => 0, 'pay_status' => 0]);

        // 新的支付流程由 notify 回调把订单置为已支付，再跳回成功页
        $response = $this->withSession(['uid' => $userId])
            ->post('/pay/notify/'.$orderId, ['amount' => 1999.00]);

        $response->assertRedirect('/order/change-status?order_id='.$orderId);
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'order_status' => 1,
            'pay_status' => 1,
        ]);
    }

    public function test_payfor_redirects_to_gateway(): void
    {
        $userId = $this->makeUser();
        $orderId = $this->makeOrder($userId, ['order_status' => 0]);

        $this->withSession(['uid' => $userId])
            ->get('/order/payfor?order_id='.$orderId.'&method=alipay')
            ->assertRedirect('/pay/gateway/'.$orderId.'?method=alipay');
    }

    // -----------------------------------------------------------------
    // 收货地址
    // -----------------------------------------------------------------

    public function test_address_can_be_added_and_deleted(): void
    {
        $userId = $this->makeUser();

        $response = $this->withSession(['uid' => $userId])->get('/address/add?'.http_build_query([
            'uname' => '赵六',
            'phone' => '13100000000',
            'province' => '上海',
            'city' => '上海市',
            'country' => '浦东新区',
            'zipcode' => '200000',
            'address' => '测试大道 8 号',
        ]));

        $response->assertJson(['status' => 0]);
        $addressId = $response->json('msg');

        $this->assertDatabaseHas('addresses', [
            'id' => $addressId,
            'user_id' => $userId,
            'consignee' => '赵六',
            'district' => '浦东新区',
        ]);

        $this->withSession(['uid' => $userId])
            ->get('/address/delete/'.$addressId)
            ->assertJson(['status' => 0, 'msg' => 'ok']);

        $this->assertDatabaseMissing('addresses', ['id' => $addressId]);
    }

    public function test_legacy_address_delete_url_still_works(): void
    {
        $userId = $this->makeUser();
        $addressId = $this->makeAddress($userId);

        $this->withSession(['uid' => $userId])
            ->get('/address/del?id='.$addressId)
            ->assertJson(['status' => 0]);

        $this->assertDatabaseMissing('addresses', ['id' => $addressId]);
    }

    // -----------------------------------------------------------------
    // 个人中心
    // -----------------------------------------------------------------

    public function test_user_order_list_loads_and_filters_by_status(): void
    {
        $userId = $this->makeUser();
        $goodId = $this->makeGood();
        $skuId = $this->makeSku($goodId);
        $orderId = $this->makeOrder($userId, [
            'order_status' => 0,
            'order_num' => '202610240002',
        ]);
        $this->makeOrderGoods($orderId, $skuId);

        $response = $this->withSession(['uid' => $userId])->get('/user/order');

        $response->assertOk();
        $response->assertSee('202610240002');
        $response->assertSee('等待付款');

        $this->withSession(['uid' => $userId])
            ->get('/user/order?s=0')
            ->assertOk()
            ->assertSee('202610240002');

        $this->withSession(['uid' => $userId])
            ->get('/user/order?s=2')
            ->assertOk()
            ->assertDontSee('202610240002');
    }

    public function test_user_comment_page_lists_uncommented_orders(): void
    {
        $userId = $this->makeUser();
        $goodId = $this->makeGood(['title' => 'MI/小米 待评价测试机']);
        $skuId = $this->makeSku($goodId);
        $orderId = $this->makeOrder($userId, ['order_status' => 3]);
        $this->makeOrderGoods($orderId, $skuId);

        $response = $this->withSession(['uid' => $userId])->get('/user/comment?filter=1');

        $response->assertOk();
        $response->assertSee('待评价商品');
        $response->assertSee('/comment/'.$skuId.'?order_id='.$orderId, false);
    }

    public function test_confirm_receipt_moves_order_to_received(): void
    {
        $userId = $this->makeUser();
        $orderId = $this->makeOrder($userId, ['order_status' => 2]);

        $response = $this->withSession(['uid' => $userId])
            ->get('/user/packin/'.$orderId);

        $response->assertRedirect('/user/comment?filter=1');
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'order_status' => 3]);
    }

    public function test_legacy_packin_url_redirects_permanently(): void
    {
        $userId = $this->makeUser();
        $orderId = $this->makeOrder($userId, ['order_status' => 2]);

        $this->withSession(['uid' => $userId])
            ->get('/user/packin?id='.$orderId)
            ->assertStatus(301)
            ->assertRedirect('/user/packin/'.$orderId);
    }

    public function test_user_address_page_lists_addresses(): void
    {
        $userId = $this->makeUser();
        $this->makeAddress($userId, ['consignee' => '钱七']);

        $response = $this->withSession(['uid' => $userId])->get('/user/address');

        $response->assertOk();
        $response->assertSee('钱七');
    }

    public function test_comment_can_be_created_for_order(): void
    {
        $userId = $this->makeUser();
        $goodId = $this->makeGood(['title' => 'MI/小米 评价提交测试机']);
        $skuId = $this->makeSku($goodId);

        // 构造一个属于该用户、已收货(状态3)且含该 SKU 的订单
        $orderId = $this->makeOrder($userId, ['order_status' => 3]);
        $this->makeOrderGoods($orderId, $skuId);

        $response = $this->withSession(['uid' => $userId])->post('/comment/insert', [
            'score' => 5,
            'content' => '手机很好用，下次还会再来。',
            'good_id' => $goodId,
            'order_id' => $orderId,
        ]);

        $response->assertRedirect('/detail/'.$goodId.'#comment');
        $this->assertDatabaseHas('comments', [
            'good_id' => $goodId,
            'user_id' => $userId,
            'useless' => $orderId,
            'star' => 5,
            'content' => '手机很好用，下次还会再来。',
        ]);
        $this->assertNotNull($skuId);
    }
}
