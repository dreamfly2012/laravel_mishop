<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 购物车与商品收藏接口。
 */
class CartCollectTest extends TestCase
{
    // -----------------------------------------------------------------
    // 游客购物车（session）
    // -----------------------------------------------------------------

    public function test_guest_can_add_sku_to_session_cart(): void
    {
        $goodId = $this->makeGood();
        $skuId = $this->makeSku($goodId, ['stock' => '10']);

        $response = $this->post('/cart/ajax-add', [
            'good_id' => $goodId,
            'num' => 2,
        ]);

        $response->assertOk()->assertJson([
            'status' => 0,
            'msg' => 'ok',
            'count' => 2,
        ]);

        $this->assertSame([$skuId, 2, $goodId], session('cart')[$skuId]);
    }

    public function test_guest_adding_same_sku_increments_quantity(): void
    {
        $goodId = $this->makeGood();
        $skuId = $this->makeSku($goodId, ['stock' => '10']);

        $this->post('/cart/ajax-add', ['good_id' => $goodId, 'num' => 2]);
        $response = $this->post('/cart/ajax-add', ['good_id' => $goodId, 'num' => 3]);

        $response->assertJson(['status' => 0, 'count' => 5]);
        $this->assertSame([$skuId, 5, $goodId], session('cart')[$skuId]);
    }

    public function test_guest_cannot_exceed_stock(): void
    {
        $goodId = $this->makeGood();
        $this->makeSku($goodId, ['stock' => '5']);

        $response = $this->post('/cart/ajax-add', [
            'good_id' => $goodId,
            'num' => 6,
        ]);

        $response->assertJson(['status' => 1]);
        $this->assertStringContainsString('已超过最大库存量5件', $response->json('msg'));
        $this->assertEmpty(session('cart'));
    }

    public function test_ajax_add_requires_good_id(): void
    {
        $this->post('/cart/ajax-add', ['num' => 1])
            ->assertJson(['status' => 2, 'msg' => '缺少商品参数']);
    }

    public function test_ajax_add_returns_error_when_good_has_no_sku(): void
    {
        $goodId = $this->makeGood();

        $this->post('/cart/ajax-add', ['good_id' => $goodId, 'num' => 1])
            ->assertJson(['status' => 3, 'msg' => '查无该商品信息']);
    }

    public function test_ajax_add_with_detail_spec_requires_all_fields(): void
    {
        $goodId = $this->makeGood();
        $this->makeSku($goodId);

        $response = $this->post('/cart/ajax-add', [
            'good_id' => $goodId,
            'num' => 1,
            'sku_price' => '1999.00',
            'sku_color' => '',
            'sku_attr' => '',
        ]);

        $response->assertJson(['status' => 3]);
        $this->assertStringContainsString('请先选择商品的版本/颜色', $response->json('msg'));
    }

    public function test_ajax_add_with_detail_spec_rejects_unknown_sku(): void
    {
        $goodId = $this->makeGood();
        $this->makeSku($goodId, ['price' => '1999.00', 'color' => '白色', 'attr' => '标准版']);

        $response = $this->post('/cart/ajax-add', [
            'good_id' => $goodId,
            'num' => 1,
            'sku_price' => '1999.00',
            'sku_color' => '黑色',
            'sku_attr' => '标准版',
        ]);

        $response->assertJson(['status' => 3]);
        $this->assertStringContainsString('该商品规格已下架', $response->json('msg'));
    }

    public function test_ajax_add_matches_exact_sku_when_spec_given(): void
    {
        $goodId = $this->makeGood();
        $whiteSku = $this->makeSku($goodId, ['price' => '1999.00', 'color' => '白色', 'attr' => '标准版']);
        $blackSku = $this->makeSku($goodId, ['price' => '2099.00', 'color' => '黑色', 'attr' => '高配版']);

        $response = $this->post('/cart/ajax-add', [
            'good_id' => $goodId,
            'num' => 1,
            'sku_price' => '2099.00',
            'sku_color' => '黑色',
            'sku_attr' => '高配版',
        ]);

        $response->assertJson(['status' => 0]);
        $this->assertArrayHasKey($blackSku, session('cart'));
        $this->assertArrayNotHasKey($whiteSku, session('cart'));
    }

    public function test_legacy_cart_add_url_still_works(): void
    {
        $goodId = $this->makeGood();
        $skuId = $this->makeSku($goodId, ['stock' => '10']);

        $response = $this->get('/cart/add/'.$skuId.'?num=1');

        $response->assertOk()->assertJson(['status' => 0]);
        $this->assertArrayHasKey($skuId, session('cart'));
    }

    public function test_guest_can_delete_item_from_session_cart(): void
    {
        $goodId = $this->makeGood();
        $skuId = $this->makeSku($goodId);

        $response = $this->withSession([
            'cart' => [
                $skuId => [$skuId, 1, $goodId],
            ],
        ])->get('/cart/delete/'.$skuId.'?listItem[]='.$skuId);

        $response->assertJson(['status' => 0, 'msg' => 'ok']);
        $this->assertNull(session('cart.'.$skuId));
    }


    // -----------------------------------------------------------------
    // 登录用户购物车（数据库）
    // -----------------------------------------------------------------

    public function test_logged_in_user_add_to_cart_persists_in_database(): void
    {
        $userId = $this->makeUser();
        $goodId = $this->makeGood();
        $skuId = $this->makeSku($goodId, ['stock' => '10']);

        $response = $this->withSession(['uid' => $userId])->post('/cart/ajax-add', [
            'good_id' => $goodId,
            'num' => 2,
        ]);

        $response->assertJson(['status' => 0, 'count' => 2]);
        $this->assertDatabaseHas('carts', [
            'user_id' => $userId,
            'sku_id' => $skuId,
            'goods_id' => $goodId,
            'goods_num' => 2,
        ]);
    }

    public function test_logged_in_user_adding_same_sku_updates_quantity(): void
    {
        $userId = $this->makeUser();
        $goodId = $this->makeGood();
        $skuId = $this->makeSku($goodId, ['stock' => '10']);
        $cartId = $this->makeCart($userId, $skuId, $goodId, ['goods_num' => 2]);

        $response = $this->withSession(['uid' => $userId])->post('/cart/ajax-add', [
            'good_id' => $goodId,
            'num' => 3,
        ]);

        $response->assertJson(['status' => 0, 'count' => 5]);
        $this->assertDatabaseHas('carts', ['id' => $cartId, 'goods_num' => 5]);
    }

    public function test_logged_in_user_cart_page_lists_items_and_total(): void
    {
        $userId = $this->makeUser();
        $goodId = $this->makeGood();
        $skuId = $this->makeSku($goodId, ['price' => '1999.00']);
        $this->makeCart($userId, $skuId, $goodId, ['goods_num' => 2]);

        $response = $this->withSession(['uid' => $userId])->get('/cart');

        $response->assertOk();
        $response->assertSee('MI/小米 测试机 标准版 白色');
        $response->assertSee('3,998.00');
    }

    // -----------------------------------------------------------------
    // 商品收藏
    // -----------------------------------------------------------------

    public function test_guest_collect_returns_login_redirect(): void
    {
        $goodId = $this->makeGood();

        $this->get('/collect/'.$goodId)->assertJson([
            'status' => 1,
            'redirect' => '/login',
        ]);
    }

    public function test_collect_toggles_on_and_off(): void
    {
        $userId = $this->makeUser();
        $goodId = $this->makeGood();

        $first = $this->withSession(['uid' => $userId])->get('/collect/'.$goodId);
        $first->assertJson(['status' => 0, 'collected' => true, 'info' => '收藏成功']);
        $this->assertDatabaseHas('collects', ['user_id' => $userId, 'good_id' => $goodId]);

        $second = $this->withSession(['uid' => $userId])->get('/collect/'.$goodId);
        $second->assertJson(['status' => 0, 'collected' => false, 'info' => '已取消收藏']);
        $this->assertDatabaseMissing('collects', ['user_id' => $userId, 'good_id' => $goodId]);
    }

    public function test_collect_rejects_missing_good(): void
    {
        $userId = $this->makeUser();

        $this->withSession(['uid' => $userId])
            ->get('/collect/99999')
            ->assertJson(['status' => 2, 'info' => '商品不存在']);
    }

    public function test_collect_rejects_offline_good(): void
    {
        $userId = $this->makeUser();
        $goodId = $this->makeGood(['status' => 0]);

        $this->withSession(['uid' => $userId])
            ->get('/collect/'.$goodId)
            ->assertJson(['status' => 2]);
    }

    public function test_legacy_collect_url_still_works(): void
    {
        $userId = $this->makeUser();
        $goodId = $this->makeGood();

        $this->withSession(['uid' => $userId])
            ->get('/collect?id='.$goodId)
            ->assertJson(['status' => 0, 'collected' => true]);
    }

    public function test_collected_state_is_visible_on_detail_page(): void
    {
        $userId = $this->makeUser();
        $goodId = $this->makeGood(['title' => 'MI/小米 已收藏测试机']);
        $this->makeSku($goodId);

        \DB::table('collects')->insert([
            'user_id' => $userId,
            'good_id' => $goodId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withSession(['uid' => $userId])->get('/detail/'.$goodId);

        $response->assertOk();
        $response->assertSee('data-isfavorite="true"', false);
    }
}
