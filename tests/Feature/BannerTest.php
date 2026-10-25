<?php

namespace Tests\Feature;

use App\Models\Banner;
use Tests\TestCase;

/**
 * 首页展示位 (轮播图 / 促销小图 / 频道入口) 后台 CRUD + 前台渲染。
 */
class BannerTest extends TestCase
{
    private function asAdmin(): static
    {
        $this->makeAdmin();

        return $this->withSession(['user_id' => 1]);
    }

    public function test_admin_banner_list_renders(): void
    {
        $admin = $this->asAdmin();

        $admin->get('/admin/banner/list')->assertOk();
        $admin->get('/admin/banner/list?position=carousel')->assertOk();
        $admin->get('/admin/banner/add')->assertOk();
    }

    public function test_admin_can_add_carousel_banner(): void
    {
        $admin = $this->asAdmin();
        $goodId = $this->makeGood();

        $response = $admin->post('/admin/banner/add', [
            'position' => 'carousel',
            'title' => '测试轮播',
            'image' => '/image/upload/mi-test.jpg',
            'good_id' => $goodId,
            'sort' => 1,
            'status' => 1,
        ]);

        $response->assertRedirect('/admin/banner/list?position=carousel');
        $this->assertDatabaseHas('banners', [
            'position' => 'carousel',
            'title' => '测试轮播',
            'good_id' => $goodId,
        ]);
    }

    public function test_admin_rejects_unknown_position(): void
    {
        $admin = $this->asAdmin();

        $admin->post('/admin/banner/add', [
            'position' => 'bogus',
            'title' => 'x',
            'status' => 1,
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertDatabaseMissing('banners', ['position' => 'bogus']);
    }

    public function test_admin_can_edit_banner(): void
    {
        $admin = $this->asAdmin();
        $id = $this->makeBanner(['title' => '原标题', 'position' => 'promo']);

        $response = $admin->post('/admin/banner/edit', [
            'id' => $id,
            'position' => 'promo',
            'title' => '新标题',
            'sort' => 5,
            'status' => 0,
        ]);

        $response->assertRedirect('/admin/banner/list?position=promo');
        $this->assertDatabaseHas('banners', ['id' => $id, 'title' => '新标题', 'status' => 0]);
    }

    public function test_admin_can_delete_banner(): void
    {
        $admin = $this->asAdmin();
        $id = $this->makeBanner(['position' => 'channel']);

        $admin->get('/admin/banner/delete/'.$id)->assertRedirect();

        $this->assertDatabaseMissing('banners', ['id' => $id]);
    }

    public function test_admin_toggles_status(): void
    {
        $admin = $this->asAdmin();
        $id = $this->makeBanner(['status' => 1]);

        $admin->get('/admin/banner/update/'.$id.'?status=0')->assertOk();

        $this->assertSame(0, (int) Banner::find($id)->status);
    }

    public function test_homepage_carousel_links_to_detail(): void
    {
        $goodId = $this->makeGood();
        $this->makeBanner([
            'position' => 'carousel',
            'title' => '跳商品',
            'good_id' => $goodId,
            'image' => '/image/upload/mi-test.jpg',
            'status' => 1,
        ]);

        $this->get('/')->assertOk()->assertSee('/detail/'.$goodId);
    }

    public function test_homepage_promo_links_to_detail(): void
    {
        $goodId = $this->makeGood();
        $this->makeBanner([
            'position' => 'promo',
            'title' => '促销位',
            'good_id' => $goodId,
            'image' => '/image/upload/mi-test.jpg',
            'status' => 1,
        ]);

        $this->get('/')->assertOk()->assertSee('/detail/'.$goodId);
    }

    public function test_homepage_channel_uses_custom_url(): void
    {
        $this->makeBanner([
            'position' => 'channel',
            'title' => '频道入口',
            'good_id' => null,
            'url' => '/list/1',
            'status' => 1,
        ]);

        $this->get('/')->assertOk()->assertSee('/list/1');
    }

    public function test_disabled_banner_not_shown(): void
    {
        $this->makeBanner([
            'position' => 'carousel',
            'image' => '/image/upload/mi-disabled-uni.jpg',
            'status' => 0,
        ]);

        $this->get('/')->assertOk()->assertDontSee('mi-disabled-uni.jpg');
    }
}
