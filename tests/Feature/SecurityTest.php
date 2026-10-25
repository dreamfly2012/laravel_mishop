<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Comment;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 安全回归：越权访问、调试后门、评论校验、服务端算价。
 */
class SecurityTest extends TestCase
{
    public function test_dev_login_route_is_removed(): void
    {
        $this->get('/__dev-login')->assertNotFound();
    }

    public function test_user_cannot_view_others_unpaid_order_pay_page(): void
    {
        $victim = $this->makeUser(['username' => 'victim', 'email' => 'victim@example.com']);
        $attacker = $this->makeUser(['username' => 'attacker', 'email' => 'attacker@example.com']);

        $goodId = $this->makeGood();
        $skuId = $this->makeSku($goodId);
        $orderId = $this->makeOrder($victim, ['order_status' => 0, 'order_num' => 'SECURITY0001']);
        $this->makeOrderGoods($orderId, $skuId);

        // 以 attacker 身份访问 victim 的未支付订单支付页
        $this->withSession(['uid' => $attacker])
            ->get('/order/pay/'.$orderId)
            ->assertNotFound();

        // victim 的订单状态未被改变
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'order_status' => 0]);
    }

    public function test_user_cannot_delete_others_address(): void
    {
        $victim = $this->makeUser(['username' => 'victim2', 'email' => 'victim2@example.com']);
        $attacker = $this->makeUser(['username' => 'attacker2', 'email' => 'attacker2@example.com']);

        $addrId = $this->makeAddress($victim, ['consignee' => '受害者地址']);

        $this->withSession(['uid' => $attacker])
            ->get('/address/delete/'.$addrId)
            ->assertJson(['status' => 1]);

        // 地址仍然存在
        $this->assertNotNull(Address::find($addrId));
    }

    public function test_comment_requires_a_valid_owned_received_order(): void
    {
        $userId = $this->makeUser();
        $goodId = $this->makeGood();
        $skuId = $this->makeSku($goodId);

        // 订单不属于该用户
        $other = $this->makeUser(['username' => 'other3', 'email' => 'other3@example.com']);
        $otherOrder = $this->makeOrder($other, ['order_status' => 3]);
        $this->makeOrderGoods($otherOrder, $skuId);

        $this->withSession(['uid' => $userId])->post('/comment/insert', [
            'score' => 5,
            'content' => '刷评价',
            'good_id' => $goodId,
            'order_id' => $otherOrder,
        ])->assertSessionHas('error');

        $this->assertDatabaseMissing('comments', [
            'good_id' => $goodId,
            'user_id' => $userId,
            'useless' => $otherOrder,
        ]);
    }

    public function test_user_cannot_confirm_receipt_of_others_order(): void
    {
        $victim = $this->makeUser(['username' => 'vpack', 'email' => 'vpack@example.com']);
        $attacker = $this->makeUser(['username' => 'apack', 'email' => 'apack@example.com']);
        $goodId = $this->makeGood();
        $skuId = $this->makeSku($goodId);

        $orderId = $this->makeOrder($victim, ['order_status' => 2]);
        $this->makeOrderGoods($orderId, $skuId);

        $this->withSession(['uid' => $attacker])
            ->get('/user/packin/'.$orderId)
            ->assertNotFound();

        // victim 的订单状态不变
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'order_status' => 2]);
    }

    public function test_order_total_is_computed_server_side_not_from_client(): void
    {
        $userId = $this->makeUser();
        $goodId = $this->makeGood(['title' => '金额校验测试机']);
        $skuId = $this->makeSku($goodId, ['price' => '100.00']);

        $this->withSession(['uid' => $userId])
            ->post('/cart/ajax-add', ['good_id' => $goodId, 'num' => 1])
            ->assertJson(['status' => 0]);

        $cart = DB::table('carts')->where('user_id', $userId)->where('sku_id', $skuId)->first();
        $this->assertNotNull($cart);

        $this->withSession(['uid' => $userId, 'listItem' => [$skuId]])
            ->post('/order/confirm', ['listItem' => [$skuId]])
            ->assertJson(['status' => 0]);

        // 伪造超低价提交, 服务端应按 100.00 计算
        $this->withSession(['uid' => $userId, 'listItem' => [$skuId]])
            ->post('/order/create', [
                'addressId' => $this->makeAddress($userId),
                'totalPrice' => '0.01',
                'packFee' => '0',
            ])
            ->assertJson(['status' => 0]);

        $order = Order::where('user_id', $userId)->orderBy('id', 'desc')->first();
        $this->assertNotNull($order);
        $this->assertEquals(100, $order->total_price);
        // 满 99 免运费
        $this->assertEquals(0, $order->pack_fee);
    }
}
