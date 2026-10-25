<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 后台管理接口（需 session user_id == 1）。
 */
class AdminTest extends TestCase
{
    private function asAdmin(): static
    {
        $this->makeAdmin();

        return $this->withSession(['user_id' => 1]);
    }

    public function test_admin_dashboard_redirects_to_user_list(): void
    {
        $this->asAdmin()->get('/admin')->assertRedirect('admin/user');
    }

    public function test_admin_list_pages_render(): void
    {
        $admin = $this->asAdmin();

        $cateId = $this->makeCate(['name' => '手机 平板']);
        $goodId = $this->makeGood(['cate_id' => (string) $cateId]);
        $this->makeSku($goodId);

        $userId = $this->makeUser(['username' => 'shopuser']);
        $orderId = $this->makeOrder($userId);
        $this->makeOrderGoods($orderId, 1);
        \DB::table('comments')->insert([
            'content' => '后台评论测试',
            'good_id' => $goodId,
            'user_id' => $userId,
            'pid' => 0,
            'path' => '0',
            'img' => '',
            'star' => '5',
            'useless' => '',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $helpCateId = $this->makeHelpCate();
        $this->makeHelpArticle($helpCateId);

        foreach ([
            '/admin/user',
            '/admin/cate',
            '/admin/good',
            '/admin/sku',
            '/admin/comment',
            '/admin/order',
            '/admin/help/cate',
            '/admin/help/article',
            '/admin/index-page/nav-list',
            '/admin/index-page/nav-add',
        ] as $url) {
            $admin->get($url)->assertOk();
        }
    }

    public function test_admin_can_create_child_category(): void
    {
        $admin = $this->asAdmin();
        $parentId = $this->makeCate(['name' => '手机 平板', 'pid' => '0', 'path' => '0']);

        $response = $admin->post('/admin/cate/insert', [
            'name' => '小米手机',
            'pid' => $parentId,
            'status' => 1,
        ]);

        $response->assertRedirect('admin/cate');
        $this->assertDatabaseHas('cates', [
            'name' => '小米手机',
            'pid' => (string) $parentId,
            'path' => '0,'.$parentId,
        ]);
    }

    public function test_admin_category_create_requires_name(): void
    {
        $admin = $this->asAdmin();
        $parentId = $this->makeCate(['name' => '手机 平板']);

        $admin->post('/admin/cate/insert', ['pid' => $parentId, 'status' => 1])
            ->assertSessionHasErrors('name');
    }

    public function test_admin_can_delete_category(): void
    {
        $admin = $this->asAdmin();
        $cateId = $this->makeCate(['name' => '待删除分类']);

        $admin->get('/admin/cate/delete/'.$cateId)->assertRedirect();

        $this->assertDatabaseMissing('cates', ['id' => $cateId]);
    }

    public function test_admin_can_create_and_delete_user(): void
    {
        $admin = $this->asAdmin();

        $response = $admin->post('/admin/user/insert', [
            'username' => 'adminshopuser',
            'password' => 'secret123',
            'repassword' => 'secret123',
            'email' => 'adminshop@example.com',
            'phone' => '13900000000',
        ]);

        $response->assertRedirect('admin/user/add');
        $this->assertDatabaseHas('users', [
            'username' => 'adminshopuser',
            'email' => 'adminshop@example.com',
            'phone' => '13900000000',
        ]);

        $newUserId = (int) \DB::table('users')->where('username', 'adminshopuser')->value('id');

        $admin->get('/admin/user/delete/'.$newUserId)->assertRedirect();
        $this->assertDatabaseMissing('users', ['id' => $newUserId]);
    }

    public function test_admin_user_create_validates_fields(): void
    {
        $admin = $this->asAdmin();

        $admin->post('/admin/user/insert', [
            'username' => 'short',
            'password' => '123',
            'repassword' => '456',
            'email' => 'bad-email',
            'phone' => '123',
        ])->assertSessionHasErrors(['username', 'password', 'repassword', 'email', 'phone']);
    }

    public function test_admin_can_update_order_status(): void
    {
        $admin = $this->asAdmin();
        $userId = $this->makeUser();
        $orderId = $this->makeOrder($userId, ['order_status' => 0]);

        $response = $admin->post('/admin/order/edit', [
            'id' => $orderId,
            'status' => 2,
        ]);

        $response->assertRedirect('/admin/order');
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'order_status' => 2]);
    }

    public function test_admin_can_add_navigation_and_toggle_status(): void
    {
        $admin = $this->asAdmin();

        $response = $admin->post('/admin/index-page/nav-add', [
            'nav_name' => '测试导航',
            'sku_id_group' => '1,2,3',
        ]);

        $response->assertRedirect('/admin/index-page/nav-list');
        $this->assertDatabaseHas('navigations', [
            'nav_name' => '测试导航',
            'sku_id_group' => '1,2,3',
            'status' => '1',
        ]);

        $navId = (int) \DB::table('navigations')->where('nav_name', '测试导航')->value('id');

        $admin->get('/admin/index-page/nav-update/'.$navId.'?status=0')
            ->assertOk()
            ->assertSee('0');

        $this->assertDatabaseHas('navigations', ['id' => $navId, 'status' => '0']);
    }

    public function test_admin_can_add_help_category(): void
    {
        $admin = $this->asAdmin();

        $response = $admin->post('/admin/help/cate-add', [
            'name' => '新帮助分类',
            'pid' => 0,
            'show' => 1,
            'status' => 1,
        ]);

        $response->assertRedirect('/admin/help/cate');
        $this->assertDatabaseHas('help_cates', [
            'name' => '新帮助分类',
            'pid' => 0,
            'path' => '0',
            'show' => 1,
            'status' => 1,
        ]);
    }

    public function test_admin_can_add_help_article(): void
    {
        $admin = $this->asAdmin();
        $helpCateId = $this->makeHelpCate();

        $response = $admin->post('/admin/help/add', [
            'name' => '后台添加的文章',
            'pid' => $helpCateId,
            'content' => '<p>正文内容</p>',
            'status' => 1,
        ]);

        $response->assertRedirect('/admin/help/article/'.$helpCateId);
        $this->assertDatabaseHas('help_articles', [
            'help_cate_id' => $helpCateId,
            'title' => '后台添加的文章',
        ]);
    }
}
