<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * 前台登录/注册/找回密码，以及后台登录与两个守卫中间件。
 */
class AuthTest extends TestCase
{
    // -----------------------------------------------------------------
    // 前台登录
    // -----------------------------------------------------------------

    public function test_login_fails_without_captcha(): void
    {
        $this->makeUser(['username' => 'alice']);

        $response = $this->post('/login', [
            'username' => 'alice',
            'password' => 'secret123',
        ]);

        $response->assertSessionHas('error', '验证码有误!');
        $this->assertNull(session('uid'));
    }

    public function test_login_fails_with_wrong_captcha(): void
    {
        $this->makeUser(['username' => 'alice']);

        $response = $this->withSession(['Vcode' => 'abcd'])->post('/login', [
            'username' => 'alice',
            'password' => 'secret123',
            'Vcode' => 'wxyz',
        ]);

        $response->assertSessionHas('error', '验证码有误!');
        $this->assertNull(session('uid'));
    }

    public function test_login_fails_with_unknown_username(): void
    {
        $response = $this->withSession(['Vcode' => 'abcd'])->post('/login', [
            'username' => 'nobody',
            'password' => 'secret123',
            'Vcode' => 'abcd',
        ]);

        $response->assertSessionHas('error', '无效的用户名!');
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $userId = $this->makeUser(['username' => 'alice']);

        $response = $this->withSession(['Vcode' => 'abcd'])->post('/login', [
            'username' => 'alice',
            'password' => 'wrong-password',
            'Vcode' => 'abcd',
        ]);

        $response->assertSessionHas('error', '密码错误!');
        $this->assertNull(session('uid'));
        $this->assertNotNull($userId);
    }

    public function test_login_succeeds_and_ignores_captcha_case(): void
    {
        $userId = $this->makeUser([
            'username' => 'alice',
            'pic' => '/image/upload/alice.png',
        ]);

        $response = $this->withSession(['Vcode' => 'AbCd'])->post('/login', [
            'username' => 'alice',
            'password' => 'secret123',
            'Vcode' => 'aBcD',
        ]);

        $response->assertRedirect('/');
        $this->assertSame($userId, session('uid'));
        $this->assertSame('/image/upload/alice.png', session('pic'));
    }

    public function test_logout_clears_home_session(): void
    {
        $userId = $this->makeUser();

        $response = $this->withSession(['uid' => $userId, 'pic' => 'x.png'])
            ->get('/user/logout');

        $response->assertRedirect('/');
        $this->assertNull(session('uid'));
        $this->assertNull(session('pic'));
    }

    // -----------------------------------------------------------------
    // 注册 / 找回密码
    // -----------------------------------------------------------------

    public function test_register_requires_valid_fields(): void
    {
        $response = $this->withSession(['Vcode' => 'abcd'])->post('/login/register', [
            'username' => 'ab',
            'password' => '123',
            'email' => 'not-an-email',
            'Vcode' => 'abcd',
        ]);

        $response->assertSessionHasErrors(['username', 'password', 'email']);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_register_rejects_duplicate_username(): void
    {
        $this->makeUser(['username' => 'alice']);

        $response = $this->withSession(['Vcode' => 'abcd'])->post('/login/register', [
            'username' => 'alice',
            'password' => 'secret123',
            'email' => 'alice2@example.com',
            'Vcode' => 'abcd',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_register_fails_with_wrong_captcha(): void
    {
        $response = $this->withSession(['Vcode' => 'abcd'])->post('/login/register', [
            'username' => 'newuser',
            'password' => 'secret123',
            'email' => 'new@example.com',
            'Vcode' => 'zzzz',
        ]);

        $response->assertSessionHas('error', '验证码有误!');
        $this->assertDatabaseMissing('users', ['username' => 'newuser']);
    }

    public function test_register_creates_user_with_hashed_password(): void
    {
        $response = $this->withSession(['Vcode' => 'abcd'])->post('/login/register', [
            'username' => 'newuser',
            'password' => 'secret123',
            'email' => 'new@example.com',
            'Vcode' => 'abcd',
        ]);

        $response->assertRedirect('/login');
        $this->assertDatabaseHas('users', [
            'username' => 'newuser',
            'email' => 'new@example.com',
            'status' => 1,
        ]);

        $user = User::where('username', 'newuser')->firstOrFail();
        $this->assertNotSame('secret123', $user->password);
        $this->assertTrue(Hash::check('secret123', $user->password));
    }

    public function test_password_reset_flow_updates_password(): void
    {
        $userId = $this->makeUser([
            'username' => 'alice',
            'email' => 'alice@example.com',
            'password' => Hash::make('oldpass123'),
        ]);

        // 第一步：用户名 + 邮箱匹配 -> 跳转到带一次性令牌的重置页
        $response = $this->post('/login/password', [
            'username' => 'alice',
            'email' => 'alice@example.com',
        ]);

        $location = $response->headers->get('Location');
        $this->assertStringContainsString('/login/repass?token=', $location);
        preg_match('/token=([^&]+)/', $location, $m);
        $token = $m[1];

        // 第二步：用令牌提交新密码
        $response = $this->post('/login/repass', [
            'token' => $token,
            'password' => 'newpass456',
            'confirm_password' => 'newpass456',
        ]);

        $response->assertRedirect('/login');
        $this->assertTrue(Hash::check('newpass456', User::find($userId)->password));

        // 令牌一次性：重复使用应失效
        $response = $this->post('/login/repass', [
            'token' => $token,
            'password' => 'another789',
            'confirm_password' => 'another789',
        ]);
        $this->assertFalse(Hash::check('another789', User::find($userId)->password));
    }

    public function test_password_reset_rejects_mismatched_email(): void
    {
        $this->makeUser([
            'username' => 'alice',
            'email' => 'alice@example.com',
        ]);

        $response = $this->post('/login/password', [
            'username' => 'alice',
            'email' => 'someone@example.com',
        ]);

        $response->assertSessionHas('error', '用户名或邮箱不正确!');
        $this->assertNull(session('uid'));
    }

    // -----------------------------------------------------------------
    // 后台登录与守卫
    // -----------------------------------------------------------------

    public function test_admin_login_succeeds_for_admin_account(): void
    {
        $this->makeAdmin();

        $response = $this->post('/admin/login', [
            'username' => 'admin',
            'password' => 'iloveyou',
        ]);

        $response->assertRedirect('/admin/user');
        $this->assertSame(1, session('user_id'));
    }

    public function test_admin_login_rejects_wrong_password(): void
    {
        $this->makeAdmin();

        $response = $this->post('/admin/login', [
            'username' => 'admin',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHas('error', '密码错误!');
        $this->assertNull(session('user_id'));
    }

    public function test_non_admin_user_cannot_enter_admin_panel(): void
    {
        // id 必须不是 1：LoginMiddleware 以 session('user_id')==1 判定管理员
        $userId = $this->makeUser([
            'id' => 2,
            'username' => 'normaluser',
            'password' => Hash::make('secret123'),
        ]);

        // 后台登录不限制 id，但登录后仍会被 LoginMiddleware 拦下
        $this->post('/admin/login', [
            'username' => 'normaluser',
            'password' => 'secret123',
        ])->assertRedirect('/admin/user');

        $this->assertSame($userId, session('user_id'));
        $this->get('/admin/user')->assertRedirect('/admin/login');
    }

    public function test_admin_logout_clears_session(): void
    {
        $this->makeAdmin();

        $response = $this->withSession(['user_id' => 1])->get('/admin/logout');

        $response->assertRedirect('/admin/login');
        $this->assertNull(session('user_id'));
    }

    // -----------------------------------------------------------------
    // 中间件
    // -----------------------------------------------------------------

    public function test_home_guard_redirects_guests_to_login(): void
    {
        foreach (['/order/confirm', '/user/order', '/user/address', '/address/add'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }

    public function test_admin_guard_redirects_guests_to_admin_login(): void
    {
        $this->get('/admin/user')->assertRedirect('/admin/login');
        $this->get('/admin/cate')->assertRedirect('/admin/login');
        $this->get('/admin/good')->assertRedirect('/admin/login');
    }

    public function test_logged_in_user_can_open_order_pages(): void
    {
        $userId = $this->makeUser();

        $this->withSession(['uid' => $userId])->get('/user/order')->assertOk();
        $this->withSession(['uid' => $userId])->get('/user/address')->assertOk();
        $this->withSession(['uid' => $userId])->get('/order/confirm')->assertOk();
    }
}
