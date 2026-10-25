# 代码审查报告

- 项目：米商城（Laravel 12 / PHP 8.4 移植版，分支 `laravel12-port`）
- 审查日期：2026-10-25
- 审查范围：`routes/web.php`、`app/Http/Controllers/{Home,Admin}`、`app/Http/Middleware`、`app/Http/Requests`、`app/Support`、`app/Models`、`config`、`.env.example`、`tests`
- 结论：**功能完整、无 SQL 注入，但存在 7 个可直接利用的越权/资金类问题（P0），以及一批 CSRF、会话与 XSS 问题（P1）**。当前形态只适合本地演示，不能直接对外部署。

> 说明：P0-1 已在本机实测复现（HTTP 200 + 会话生效）；其余问题为代码审查结论，未在真实库上执行破坏性操作（删数据、改订单），每条都给出了可直接核对的代码位置。

---

## 修复进度（2026-10-25）

以下条目已在本次修复中处理，并新增 tests/Feature/SecurityTest.php（越权 / 调试后门 / 服务端算价回归）与对应测试；php artisan test 结果为 92 passed (292 assertions)。

| 编号 | 问题 | 状态 | 处理 |
| --- | --- | --- | --- |
| P0-1 | 调试后门 /__dev-login | 已修 | 删除路由（routes/web.php） |
| P0-2 | 支付状态伪造 | 已修 | OrderController::changeStatus 增加 where user_id = session uid |
| P0-3 | 金额由前端提交 | 已修 | OrderController::postCreate 按 skus.price 服务端重算 total_price / pack_fee，不再信任客户端 |
| P0-4 | 支付页越权读他人订单 | 已修 | OrderController::getPay 增加 where user_id |
| P0-5 | 确认收货越权 | 已修 | UserController::getPackin 增加 where user_id 且要求 order_status = 2 |
| P0-6 | 地址越权删除 | 已修 | AddressController::getDel 增加 where user_id，不存在时返回 status=1 |
| P0-7 | 密码重置可接管账号 | 已修 | 改为一次性令牌（Cache 存 token 到 uid，15 分钟过期、用完即焚），统一错误提示防用户名枚举；repass 视图增加隐藏 token 字段 |
| P1-9 | 后台登录无验证码/无频率限制 | 已修 | /admin/login 增加 throttle:10,1（前台登录本身有验证码，加 throttle:60,1 防御） |
| P1-10 | 登录未重建会话 | 已修 | 前后台登录成功 session()->regenerate()；退出 invalidate() + regenerateToken() |
| P1-11 | 订单列表收货人 XSS | 已修 | UserController::dealTmp 中 consignee 用 htmlspecialchars 转义 |
| P1-12 | 评论无校验 | 已修 | CommentController::insert 校验订单归属+已收货状态、商品在订单内、同订单同商品只评价一次、score 限 0-10、content 限长 |
| P1-13 | 登录跳转用 Referer 开放重定向 | 已修 | HomeMiddleware 仅允许站内/相对路径跳转 |
| P2-15 | 详情页规格表取到别的商品 | 已修 | ListController::detail 的 Sku::where attr 增加 where good_id |
| P2-16 | .env.example 与项目要求不符 | 已修 | 改为 APP_DEBUG=false、DB_CONNECTION=mysql 加 xm 库、SESSION_DRIVER=file、CACHE_STORE=file |
| P2-17/18 | 购物车数量无校验 / 空指针 | 已修 | CartController::getAddproduct 强制 int 且 min:1；已登录分支不存在购物车时新建 |
| P1-8 部分 | /cart/init 调试后门（GET 强退登录） | 已修 | 删除路由与 getInit() 方法 |
| P2-20 | 删图路径穿越 | 已修 | 四个 deleteImage() 增加必须在 upload_image_dir 内的校验 |
| P2-23 | 死代码 | 已修 | 删除 CateController::getTest() 的 dd() 调试方法 |

仍未处理（需在后续迭代评估，涉及较大改动或 schema）：

- P1-8 破坏性操作走 GET（天然绕过 CSRF）：后台删用户/分类/商品/SKU/评论/帮助、前台删地址/购物车、确认收货、退出登录目前仍是 GET。已删掉最危险的 /__dev-login 和 /cart/init，但其余建议统一改为 POST/DELETE + CSRF（涉及视图与 JS 改动较多，本轮未做）。
- P2-19 订单号唯一索引：makeOrderNum() 已加同号重试降低碰撞概率，但 orders.order_num 仍未加数据库唯一索引（项目约定不跑 migrate，需单独评估建表脚本）。
- P2-21/22 密码策略弱、用户名规则前后台不一致：注册 ^w{3,20}$ 与后台 ^w{8,20}$ 未统一；密码无复杂度/锁定策略。建议后续统一并视需要加复杂度校验。
- P2-14 富文本 {!! !!} 原样输出：详情页规格、帮助正文、后台富文本仍用 {!! !!}}。属受信来源但建议入库前 HTMLPurifier 过滤。

---

## P0 严重（越权 / 资金 / 账号接管）

### 1. 调试后门：`/__dev-login` 可一键登录任意用户

- 位置：`routes/web.php:228`
- 代码：

```php
// TEMP-DEV-LOGIN
Route::get('/__dev-login', function () { session(['uid' => 2]); return 'ok'; });
```

- 影响：任何人访问该 URL 即以 uid=2 登录，可直接进入个人中心、下单、查看订单。**无需任何凭据。**
- 已复现：`GET /__dev-login` → 200，随后 `GET /user/order` → 200（未登录时应 302 到 /login）。
- 修复：删除该路由。若调试需要，放到 `app()->isLocal()` 判断之后，且不要提交到仓库。

### 2. 任意登录用户可把任意订单改成「已支付」

- 位置：`routes/web.php:80` → `app/Http/Controllers/Home/OrderController.php:204` `changeStatus()`
- 代码：

```php
$status = $request->input('status');
$orderid = $request->input('order_id');
if($status=='00'){
    $order = Order::where('order_status',0)->findOrFail($orderid);  // 没有 user_id 条件
    $order->order_status = 1;
    $order->pay_status = 1;   // 直接置为已支付
    $order->save();
}
```

- 影响：支付回调没有任何签名/金额校验，参数完全由客户端提供。攻击者登录后请求
  `GET /order/change-status?status=00&order_id=<任意订单ID>` 即可把别人的未支付订单标记为已付款，属于典型的**支付绕过**。
- 修复：真实支付必须有服务端回调 + 签名验证（或至少校验当前会话就是该订单的 user_id，并核对金额）；演示环境也要加 `->where('user_id', session('uid'))`。

### 3. 订单金额、运费由前端提交，可 0 元下单

- 位置：`OrderController.php:105`、`:107`
- 代码：

```php
$order->pack_fee   = $request->input('packFee');    // 运费
$order->total_price = $request->input('totalPrice'); // 总价
```

- 影响：订单总价完全信任客户端表单，改成 `0.01` 即可低价/0 元下单；`pack_fee` 同理。
- 修复：服务端按 `carts` + `skus.price` 重新计算总价与运费（`confirm()` 里已有同样的计算逻辑，可抽成方法复用），只接受 `listItem`（勾选的 sku）。

### 4. 订单支付页越权：可查看他人订单的收货人 / 电话 / 地址

- 位置：`OrderController.php:162` `getPay()`
- 代码：

```php
$order = Order::where('order_status',0)->findOrFail($id);   // 缺 user_id 条件
```

- 影响：遍历 `/order/pay/{id}` 即可读取所有未支付订单的收件人姓名、手机号、详细地址（订单创建时已把地址快照写入订单行）。
- 修复：`Order::where('user_id', session('uid'))->where('order_status',0)->findOrFail($id)`。

### 5. 确认收货越权

- 位置：`routes/web.php:93` → `app/Http/Controllers/Home/UserController.php:52` `getPackin()`
- 代码：

```php
$order = Order::findOrFail($id);   // 缺 user_id 条件
$order->order_status = 3;          // 直接把任意订单改成已收货
```

- 影响：任意登录用户可把任意订单改成「已收货」，破坏订单状态机。
- 修复：加 `->where('user_id', session('uid'))`，并校验当前状态确实为 2（待收货）。

### 6. 收货地址越权删除

- 位置：`routes/web.php:87` → `app/Http/Controllers/Home/AddressController.php:46` `getDel()`
- 代码：

```php
$address = Address::find($ad_id);   // 缺 user_id 条件
if($address->delete()){ ... }
```

- 影响：遍历 id 可删除任意用户的收货地址；地址不存在时 `$address` 为 null → `delete()` 触发 500。
- 修复：`Address::where('user_id', session('uid'))->findOrFail($ad_id)`。

### 7. 密码重置可接管任意账号（只凭「用户名 + 邮箱」）

- 位置：`app/Http/Controllers/Home/LoginController.php:85` `postPassword()`、`:114` `postRepass()`
- 代码：

```php
// postPassword: 邮箱匹配就直接把 uid 写进 session
if($email==$e){
    $request->session()->put('uid',$user->id);
    return redirect('/login/password')->with('mark','1');
}
// postRepass: 直接用 session 里的 uid 改密码, 不校验 token/旧密码
$user = User::find($id);
$user->password = Hash::make($data['password']);
```

- 影响：
  1. 注册时邮箱未验证，邮箱是用户自己填的，不构成身份证明；
  2. 重置流程没有一次性 token，`postPassword` 一旦把 uid 写进 session，后续 `POST /login/repass` 就能改密；
  3. 该接口无验证码、无频率限制，可批量尝试；
  4. `postPassword` 会返回「无效的用户名」，可用来枚举用户名。
- 修复：生成一次性 `password_reset_tokens`（Laravel 自带表）并发邮件；`postRepass` 校验 token 且一次性失效；错误提示统一为「用户名或邮箱不正确」；加验证码与频率限制。

---

## P1 高（CSRF / 会话 / XSS）

### 8. 所有破坏性操作都走 GET，天然绕过 CSRF

Laravel 的 `VerifyCsrfToken` 只校验 POST/PUT/PATCH/DELETE，以下路由全用 GET 完成写操作，可被 `<img src="...">` 或链接触发：

| 操作 | 路由 |
| --- | --- |
| 后台删用户/分类/商品/SKU/评论/帮助 | `/admin/{user,cate,good,sku,comment,help}/delete/{id}` |
| 后台改状态 | `/admin/help/*-status/{id}`、`/admin/help/*-show/{id}`、`/admin/index-page/nav-delete/{id}` |
| 前台删地址 | `/address/delete/{id}` |
| 前台删购物车 | `/cart/delete/{id}` |
| 确认收货 | `/user/packin/{id}` |
| 支付状态 | `/order/change-status` |
| 退出登录 | `/user/logout`、`/admin/logout` |
| 清空会话 | `/cart/init`（会 `session(['uid'=>null])`，等于强制登出） |

- 影响：管理员在登录状态访问恶意页面，即可被删数据；普通用户可被强制登出/删地址。
- 修复：写操作统一改 POST/DELETE + `@csrf`（或 AJAX 带 `X-CSRF-TOKEN`，项目已有该机制）；`/cart/init` 这种调试接口直接删掉。

### 9. 后台登录无验证码、全站无频率限制

- 位置：`app/Http/Controllers/Admin/LoginController.php:19` `postLogin()`
- 前台登录有验证码（`checkVcode`），**后台登录没有**；且 `routes/web.php` 里没有任何 `throttle` 中间件。
- 影响：可对 `/admin/login` 与 `/login` 做无限次撞库。
- 修复：`Route::post('/admin/login', ...)->middleware('throttle:5,1')`；后台登录加验证码或与前台共用。

### 10. 登录后未重建 session（会话固定）

- 位置：`LoginController.php:23` `postIndex()`、`Admin/LoginController.php:19`
- 代码只有 `session(['uid'=>$user->id])`，没有 `session()->regenerate()`。
- 影响：攻击者预先给受害者植入已知 session id，受害者登录后该 id 仍是有效登录态。
- 修复：登录成功后 `$request->session()->regenerate()`；退出时 `invalidate()` + `regenerateToken()`。

### 11. 订单列表拼接 HTML → 存储型 XSS（收货人姓名）

- 位置：`UserController.php:74` `dealTmp()` 拼字符串，`resources/views/home/user/order.blade.php:78` 用 `{!! $info !!}` 输出
- 代码：

```php
$str .= '... '.$val->consignee.' ...';   // 收货人姓名原样拼进 HTML
```

- 影响：`consignee` 来自 `/address/add` 的 `uname`，无过滤。填入 `<img src=x onerror=alert(document.cookie)>` 后，自己每次打开订单页都会执行；后台订单详情若也输出该字段则影响管理员。
- 修复：改用 Blade 模板 + `{{ }}` 转义，不要拼 HTML 字符串（`dealTmp` 整体重构为 partial）。

### 12. 评论接口无购买校验、无输入校验

- 位置：`app/Http/Controllers/Home/CommentController.php:32` `insert()`
- 代码：

```php
$comment->star     = round($data['score']*10)/10;  // 任意值
$comment->good_id  = $data['good_id'];             // 任意商品
$comment->useless  = $data['order_id'];            // 任意订单
$comment->content  = $data['content'];
```

- 影响：登录用户可给任意商品刷评价（不需要买过）、star 可传 9999、content 长度不限（数据库截断/膨胀）。
- 修复：校验 `order_id` 属于当前用户且状态为已收货/完成、`good_id` 在该订单内、`score` 在 0–10、`content` 限长；同一订单同一商品只允许评价一次。

### 13. 登录跳转使用 HTTP_REFERER（开放重定向）

- 位置：`app/Http/Middleware/HomeMiddleware.php:28`

```php
session(['back'=>$_SERVER['HTTP_REFERER']]);
...
return redirect(session('back'));
```

- 影响：伪造 Referer 为外部站点，未登录用户访问受保护页面会被跳到攻击者站点（钓鱼）。`LoginMiddleware` 的 `redirectUrl` 同理（目前只存不跳，属潜在）。
- 修复：只接受站内相对路径（`parse_url` 后校验 host 为空或等于 `config('app.url')`）。

---

## P2 中（正确性 / 配置 / 数据）

### 14. 富文本原样输出（`{!! !!}`）

- 位置：`resources/views/home/detail.blade.php:196,225`（商品详情、规格表）、`home/help/help.blade.php:51`（帮助正文）、`admin/help/artedit.blade.php:43`、`admin/help/cate.blade.php:49`
- 说明：这些内容来自后台 UEditor / 种子数据，属于「受信来源」，但一旦后台账号被盗或导入了脏数据，就是全站 XSS（UEditor 允许 `<script>`）。
- 修复：入库前用 HTMLPurifier 之类白名单过滤（至少去掉 `script/iframe/on*`），或改用 iframe 沙箱渲染。

### 15. 详情页规格表取到了别的商品的数据

- 位置：`app/Http/Controllers/Home/ListController.php:49,51`

```php
$info[$value] = Sku::where('attr',$value)->first()->info;       // 没带 good_id
foreach (Sku::where('attr',$value)->get() as $k => $v) { ... }  // 没带 good_id
```

- 影响：`attr` 在多款商品间大量重复。实测当前库 77 个 attr 中有 5 个被多个商品共用（`10000mAh`、`100cm`、`1080P`、`128G`、`16GB WiFi版`），这些商品的详情页「规格参数」会显示**另一款商品**的规格；颜色列表也会混入别的商品颜色。
- 修复：`->where('good_id', $good->id)`；顺便把循环里的查询提到循环外（见 P3-25）。

### 16. `.env.example` 与项目实际要求不符，照抄会直接 500

- 位置：`.env.example`
- 问题：
  - `APP_DEBUG=true`：任何报错都会把堆栈、SQL、`.env` 片段输出到页面；
  - `SESSION_DRIVER=database`、`CACHE_STORE`（默认 database）：`xm` 库没有 `sessions`/`cache` 表，登录直接报错；
  - `DB_CONNECTION=sqlite`：项目实际用 MySQL。
- 修复：示例改成 `APP_DEBUG=false`、`SESSION_DRIVER=file`、`CACHE_STORE=file`、`DB_CONNECTION=mysql` + 注释说明（AGENTS.md 里已写明，但示例文件没同步）。

### 17. 购物车数量无校验

- 位置：`CartController::getAddproduct()`
- `$request->input('num')` 未做类型/范围校验：负数、`abc`、超大值都能写进 session/数据库；`$sku->stock < $num` 的比较对负数恒为 false。
- 影响：负数量会产生负金额，配合 P0-3 的前端总价可构造异常订单。
- 修复：`(int)` + `min:1` + `max:$sku->stock`。

### 18. 购物车更新时空指针

- 位置：`CartController::getAddproduct()` 已登录分支

```php
$cart = Cart::where('user_id',$uid)->where('sku_id',$skuId)->first();
$cart->goods_num = $request->input('num');   // $cart 可能为 null
```

- 影响：直接请求一个不在购物车里的 sku → 500（`Attempt to assign property on null`）。
- 修复：`if (!$cart) { 走新增逻辑 }`。

### 19. 订单号可能重复

- 位置：`OrderController.php:102` `date('YmdHis').rand(100000,999999)`
- 说明：`order_num` 无唯一索引，同一秒内并发下单有碰撞概率；订单号是用户侧唯一凭据。
- 修复：加唯一索引 + 冲突重试，或改用 `Str::uuid()`/`uniqid` 拼接。

### 20. 后台删图直接使用库内路径

- 位置：`Admin/{Goods,Sku,User,Comment}Controller::deleteImage()` → `@unlink(public_path($img))`
- 说明：路径来自数据库，若某处能写入 `../../` 之类值，可删除项目内任意文件（当前写入路径均由服务端生成，风险较低）。
- 修复：校验路径必须以 `config('app.upload_image_dir')` 开头再删。

---

## P3 低 / 建议

21. **密码策略弱**：`\S{6,30}`，无复杂度要求、无邮箱验证、无登录失败锁定。
22. **用户名校验不一致**：注册 `^\w{3,20}$`（`RegisterRequest`），后台新增 `^\w{8,20}$`（`UserInsertRequest`），同一份数据两套规则。
23. **死代码**：`Admin\CateController::getTest()` 里的 `dd()`、多处注释掉的 `dd()`、`ArticleInsertRequest/ArticleUpdateRequest` 的 `rules()` 是空的（等于不做任何校验）。
24. **无审计日志**：后台删除/改价/改状态没有任何操作记录，出问题无法追溯。
25. **N+1 查询**：详情页对每个 `attr` 查两次 `Sku`（`ListController:49,51`）；首页 `allGoods()` 按分类逐个查商品，分类多时明显变慢。
26. **测试缺口**：现有 87 个用例覆盖了页面/流程，但**没有一个越权、CSRF、上传、支付校验的用例**，所以上面这些问题全部是绿的。建议至少补：越权访问他人订单/地址、`__dev-login` 已移除、订单金额由服务端计算、GET 写操作返回 405。

---

## 已经做对的地方（修改时别破坏）

- **SQL 注入**：全站使用查询构造器，未发现字符串拼接 SQL；3 处 `DB::raw` 均为常量片段（`HelpController:69,203`、`CartController:73`）。
- **密码存储**：`Hash::make` / `Hash::check`（bcrypt），未发现明文或 md5。
- **上传**：`app/Support/ImageUpload.php` 校验真实 MIME 白名单（jpg/png/gif/webp）、5MB 上限、文件名随机、扩展名由 MIME 推导，能挡住 `shell.php` / `a.php.jpg`。
- **CSRF**：POST 路由受 Laravel CSRF 保护，AJAX 走 `X-CSRF-TOKEN`；问题只在于「写操作用了 GET」。
- **输出转义**：用户名、评论内容、订单商品名等均为 `{{ }}` 转义（只有富文本字段用 `{!! !!}`，见 P2-14）。
- **依赖**：`composer audit` 无已知漏洞。
- **资源**：图片/样式/脚本已全部本地化，前台 0 外链请求。

---

## 修复顺序建议

1. **立刻**：删 `/__dev-login`（P0-1）。
2. **对外部署前必须**：P0-2 ~ P0-7（越权与金额校验）、P1-8（GET 改 POST）、P1-10（session regenerate）、P2-16（`.env.example`）。
3. **随后**：P1-9（限流）、P1-11（订单页 XSS）、P1-12（评论校验）、P1-13（跳转校验）。
4. **有余力**：P2 其余项 + 补安全测试用例（越权/CSRF/上传/金额）。

> 如果这个项目只作为本地演示，至少要保证：`/__dev-login` 已删除、`.env` 里 `APP_DEBUG=false`、不把 `php artisan serve` 暴露到公网。
