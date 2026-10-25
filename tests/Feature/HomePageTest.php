<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * 前台页面与路由兼容性。
 */
class HomePageTest extends TestCase
{
    public function test_home_page_loads(): void
    {
        $cateId = $this->makeCate(['name' => '手机 平板']);
        $goodId = $this->makeGood(['cate_id' => (string) $cateId]);
        $this->makeSku($goodId);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('小米商城');
    }

    public function test_list_page_shows_goods_of_category(): void
    {
        $cateId = $this->makeCate(['name' => '小米手机']);
        $goodId = $this->makeGood([
            'cate_id' => (string) $cateId,
            'title' => 'MI/小米 列表测试机',
        ]);
        $this->makeSku($goodId);

        $response = $this->get('/list/'.$cateId);

        $response->assertOk();
        $response->assertSee('MI/小米 列表测试机');
    }

    public function test_detail_page_shows_good_and_skus(): void
    {
        $cateId = $this->makeCate(['name' => '小米手机']);
        $goodId = $this->makeGood([
            'cate_id' => (string) $cateId,
            'title' => 'MI/小米 详情测试机',
        ]);
        $this->makeSku($goodId, ['attr' => '标准版', 'color' => '白色']);

        $response = $this->get('/detail/'.$goodId);

        $response->assertOk();
        $response->assertSee('MI/小米 详情测试机');
        $response->assertSee('标准版');
        $response->assertSee('白色');
    }

    public function test_search_page_loads_with_keyword(): void
    {
        $parent = $this->makeCate(['name' => '手机 平板', 'pid' => '0', 'path' => '0']);
        $child = $this->makeCate(['name' => '小米手机', 'pid' => (string) $parent, 'path' => '0,'.$parent]);
        $goodId = $this->makeGood(['cate_id' => (string) $child, 'title' => 'MI/小米 搜索测试机']);
        $this->makeSku($goodId);

        $response = $this->get('/list-search?kWord=手机');

        $response->assertOk();
        $response->assertSee('MI/小米 搜索测试机');
    }

    public function test_search_page_with_empty_keyword_does_not_error(): void
    {
        $this->makeCate(['name' => '手机 平板', 'pid' => '0']);

        $this->get('/list-search')->assertOk();
    }

    public function test_help_article_page_loads(): void
    {
        $cateId = $this->makeHelpCate(['name' => '账户管理', 'pid' => 0, 'show' => 1]);
        $articleId = $this->makeHelpArticle($cateId, ['title' => '账户注册']);

        $response = $this->get('/help/article/'.$articleId);

        $response->assertOk();
        $response->assertSee('账户注册');
    }

    public function test_comment_page_loads_for_sku(): void
    {
        $goodId = $this->makeGood(['title' => 'MI/小米 评价测试机']);
        $skuId = $this->makeSku($goodId, ['title' => '评价测试机 白色']);

        $response = $this->get('/comment/'.$skuId.'?order_id=9');

        $response->assertOk();
        $response->assertSee('评价测试机 白色');
        $response->assertSee('value="'.$goodId.'"', false);
    }

    public function test_guest_cart_page_loads_empty(): void
    {
        $this->get('/cart')->assertOk()->assertSee('购物车');
    }

    public function test_guest_cart_page_reads_session_cart(): void
    {
        $goodId = $this->makeGood(['title' => 'MI/小米 会话购物车测试机']);
        $skuId = $this->makeSku($goodId, ['price' => '1999.00']);

        $response = $this->withSession([
            'cart' => [$skuId => [$skuId, 2, $goodId]],
        ])->get('/cart');

        $response->assertOk();
        $response->assertSee('MI/小米 测试机 标准版 白色');
        $response->assertSee('3,998.00');
    }

    public function test_missing_good_redirects_to_home(): void
    {
        $this->get('/detail/99999')->assertRedirect('/');
    }

    public function test_missing_help_article_returns_404(): void
    {
        $this->get('/help/article/99999')->assertNotFound();
    }

    public function test_legacy_detail_url_redirects_permanently(): void
    {
        $goodId = $this->makeGood();
        $this->makeSku($goodId);

        $this->get('/detail?id='.$goodId)
            ->assertStatus(301)
            ->assertRedirect('/detail/'.$goodId);
    }

    public function test_legacy_list_url_redirects_permanently(): void
    {
        $cateId = $this->makeCate(['name' => '小米手机']);

        $this->get('/list?id='.$cateId)
            ->assertStatus(301)
            ->assertRedirect('/list/'.$cateId);
    }

    public function test_legacy_help_article_url_redirects_permanently(): void
    {
        $cateId = $this->makeHelpCate();
        $articleId = $this->makeHelpArticle($cateId);

        $this->get('/help/article?id='.$articleId)
            ->assertStatus(301)
            ->assertRedirect('/help/article/'.$articleId);
    }

    public function test_legacy_comment_url_keeps_order_id(): void
    {
        $goodId = $this->makeGood();
        $skuId = $this->makeSku($goodId);

        $this->get('/comment?id='.$skuId.'&order_id=12')
            ->assertStatus(301)
            ->assertRedirect('/comment/'.$skuId.'?order_id=12');
    }

    public function test_legacy_order_change_status_url_redirects_permanently(): void
    {
        $response = $this->withSession(['uid' => $this->makeUser()])
            ->get('/order/changeStatus?status=00&order_id=3');

        $response->assertStatus(301);
        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('/order/change-status?', $location);
        $this->assertStringContainsString('status=00', $location);
        $this->assertStringContainsString('order_id=3', $location);
    }

    public function test_captcha_returns_png_and_stores_phrase(): void
    {
        $response = $this->get('/login/captcha');

        $response->assertOk();
        $this->assertStringContainsString('image/png', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith("\x89PNG", $response->getContent());

        $phrase = (string) session('Vcode');
        $this->assertMatchesRegularExpression('/^[23456789abcdefghjkmnpqrstuvwxyz]{4}$/', $phrase);
    }

    public function test_login_register_and_password_pages_render(): void
    {
        $this->get('/login')->assertOk();
        $this->get('/login/register')->assertOk();
        $this->get('/login/password')->assertOk();
        // 重置密码页必须携带一次性令牌, 否则 403
        $token = Str::random(40);
        Cache::put('pwd_reset_'.$token, 1, now()->addMinutes(15));
        $this->get('/login/repass?token='.$token)->assertOk();
        $this->get('/login/repass')->assertForbidden();
    }
}
