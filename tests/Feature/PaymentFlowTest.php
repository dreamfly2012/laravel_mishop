<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 支付流程（本地沙箱收银台）：
 *   选支付方式 -> 跳转本地收银台 -> 确认支付(notify 回调) -> 订单置为已支付 -> 成功页。
 * 不依赖任何外部支付商户号，全部走本地可调试流程。
 */
class PaymentFlowTest extends TestCase
{
    private function login(int $userId)
    {
        return $this->withSession(['uid' => $userId]);
    }

    public function test_pay_page_shows_amount_and_methods(): void
    {
        $userId = $this->makeUser();
        $goodId = $this->makeGood();
        $skuId = $this->makeSku($goodId);
        $orderId = $this->makeOrder($userId, [
            'order_status' => 0,
            'total_price' => 1999.00,
            'order_num' => 'PAY202601',
        ]);
        $this->makeOrderGoods($orderId, $skuId);

        $response = $this->login($userId)->get('/order/pay/'.$orderId);

        $response->assertOk();
        $response->assertSee('1999.00');
        $response->assertSee('支付宝');
        $response->assertSee('PAY202601');
    }

    public function test_selecting_method_redirects_to_gateway(): void
    {
        $userId = $this->makeUser();
        $orderId = $this->makeOrder($userId, ['order_status' => 0]);

        $this->login($userId)
            ->get('/order/payfor?order_id='.$orderId.'&method=wechat')
            ->assertRedirect('/pay/gateway/'.$orderId.'?method=wechat');
    }

    public function test_invalid_method_is_rejected(): void
    {
        $userId = $this->makeUser();
        $orderId = $this->makeOrder($userId, ['order_status' => 0]);

        $this->login($userId)
            ->get('/order/payfor?order_id='.$orderId.'&method=bitcoin')
            ->assertRedirect('/order/pay/'.$orderId);
    }

    public function test_gateway_shows_confirm_button(): void
    {
        $userId = $this->makeUser();
        $orderId = $this->makeOrder($userId, ['order_status' => 0, 'total_price' => 1999.00]);

        $response = $this->login($userId)->get('/pay/gateway/'.$orderId.'?method=alipay');

        $response->assertOk();
        $response->assertSee('确认支付');
        $response->assertSee('1999.00');
    }

    public function test_notify_marks_order_paid_and_redirects_to_success(): void
    {
        $userId = $this->makeUser();
        $orderId = $this->makeOrder($userId, [
            'order_status' => 0,
            'pay_status' => 0,
            'total_price' => 1999.00,
        ]);

        $response = $this->login($userId)
            ->post('/pay/notify/'.$orderId, ['amount' => 1999.00]);

        $response->assertRedirect('/order/change-status?order_id='.$orderId);
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'order_status' => 1,
            'pay_status' => 1,
        ]);
    }

    public function test_notify_rejects_tampered_amount(): void
    {
        $userId = $this->makeUser();
        $orderId = $this->makeOrder($userId, [
            'order_status' => 0,
            'pay_status' => 0,
            'total_price' => 1999.00,
        ]);

        $this->login($userId)
            ->post('/pay/notify/'.$orderId, ['amount' => 1.00])
            ->assertStatus(400);

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'order_status' => 0,
            'pay_status' => 0,
        ]);
    }

    public function test_change_status_shows_success_only_when_paid(): void
    {
        $userId = $this->makeUser();
        $orderId = $this->makeOrder($userId, ['order_status' => 1, 'pay_status' => 1]);

        $response = $this->login($userId)->get('/order/change-status?order_id='.$orderId);

        $response->assertOk();
        $response->assertSee('交易完成');
    }

    public function test_cannot_pay_another_users_order(): void
    {
        $ownerId = $this->makeUser();
        $attackerId = $this->makeUser();
        $orderId = $this->makeOrder($ownerId, ['order_status' => 0, 'pay_status' => 0]);

        // 攻击者尝试为别人的订单发起/回调支付，应 404
        $this->login($attackerId)
            ->post('/pay/notify/'.$orderId)
            ->assertNotFound();

        $this->login($attackerId)
            ->get('/pay/gateway/'.$orderId)
            ->assertNotFound();
    }

    public function test_notify_is_idempotent_on_repeat_callback(): void
    {
        $userId = $this->makeUser();
        $orderId = $this->makeOrder($userId, [
            'order_status' => 0,
            'pay_status' => 0,
            'total_price' => 1999.00,
        ]);

        // 网关重复回调: 第一次正常入账, 第二次不能再改状态(也不能报错),
        // 两次都应把用户带回同步返回页
        $this->login($userId)
            ->post('/pay/notify/'.$orderId, ['amount' => 1999.00])
            ->assertRedirect('/order/change-status?order_id='.$orderId);

        $this->login($userId)
            ->post('/pay/notify/'.$orderId, ['amount' => 1999.00])
            ->assertRedirect('/order/change-status?order_id='.$orderId);

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'order_status' => 1,
            'pay_status' => 1,
        ]);
    }

    public function test_notify_rejects_non_pending_order(): void
    {
        $userId = $this->makeUser();
        // order_status=3 (已完成/取消) 且未支付的订单不允许再回调支付
        $orderId = $this->makeOrder($userId, [
            'order_status' => 3,
            'pay_status' => 0,
            'total_price' => 1999.00,
        ]);

        $this->login($userId)
            ->post('/pay/notify/'.$orderId, ['amount' => 1999.00])
            ->assertStatus(400);

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'order_status' => 3,
            'pay_status' => 0,
        ]);
    }
}
