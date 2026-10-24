# AGENTS.md

米商城 demo 电商应用（手机商城），**已从 Laravel 5.5 / PHP 5.x 移植到 Laravel 12 / PHP 8.4**（分支 `laravel12-port`）。UI 文案、种子数据仍为中文。

## 架构现状（移植后）

- **框架**: Laravel 12（`composer.json`），PHP >= 8.2；路由为现代 `bootstrap/app.php` 骨架 + `routes/web.php` 显式路由。
- **模型**: `app/Models/`（`Good`、`Sku`、`Cart`、`Order`、`OrderGoods`、`Address`、`Cate`、`Comment`、`HelpArticle`、`HelpCate`、`Navigation`、`User`），关系用字符串类名 `'App\Models\X'`。
- **控制器**: `app/Http/Controllers/Home/*`（前台）与 `app/Http/Controllers/Admin/*`（后台，已由小写 `admin` 重命名）。
- **中间件别名**（在 `bootstrap/app.php` 注册）: `login` → 守卫后台（要求 `session('user_id')==1`）；`home` → 守卫个人中心/订单/地址。
- **无前端构建**: `public/homes`(前台样式)、`public/back`(后台)、`public/ueditor`、`public/{data,image,uploads}` 均为直接提交的静态资源；`vite.config.js`/`package.json` 存在但不用。

## Setup

1. 建 MySQL 库名 `xm` 并导入根目录 `xm.sql`——这是唯一数据源。`php artisan migrate` **不要运行**（`database/migrations/` 只是框架默认三张表，与 `xm` 里既有的 `users` 等表冲突）。`xm` 中有 13 张业务表，MyISAM。
2. `cp .env.example .env` 然后改 DB_*（本地 phpstudy 为 root/123456）并 `php artisan key:generate`。`.env` 关键项: `SESSION_DRIVER=file`、`CACHE_STORE=file`（`xm` 库没有 sessions/cache 表）。
3. `composer install`（已配置阿里云 composer 镜像 repository）；`php artisan serve` 即可。
4. 后台登录 `/admin/login`，账号 `admin` / `iloveyou`（**移植时已把库中 admin(id=1) 密码哈希重置为该值**，原种子哈希与文档不符）。`LoginMiddleware` 要求 `user_id==1`，别动 admin 的 id。

## 路由（移植要点）

全部隐式控制器已显式化，**URL 以为视图/JS 实际引用为准**，规则约为“方法名去 get/post 动词前缀后转小写，驼峰词界加 `-`”（`getAllTotal` → `/cart/all-total`，`getOutLogin` → `/admin/login/out-login`，两版本都注册了）。注意 `/order/changeStatus` 是旧代码显式写死的驼峰 URL。

- 购物车 `/cart/*` 不挂 `home` 中间件（沿用原代码）；`/comment/insert` 挂 `home`。
- CSRF 正常开启：表单用 `{{csrf_field()}}`，AJAX 用 `<meta name="csrf-token">` + `X-CSRF-TOKEN` 头（`public/homes/common/myjs/*.js`），测试脚本需先取 `/cart` 或表单页的 token。

## 移植已修复的坑（别回头改回去）

- 所有 `echo json_encode(...); die;` 已改为 `return response()->json(...)`（private 辅助方法改为返回 Response，调用点 `return $this->helper(...)`）。裸 `echo + die` 会破坏 L12 的 session/响应生命周期。
- `Input` facade 已删除，一律 `$request->input()/file()/only()`；`$request->title` 这类魔法属性访问已改为 `$request->input('title')`。
- 相对路径文件操作（`./data/...`、上传目录）已改为 `public_path(...)` + `config('app.upload_image_dir')`（值 `/image/upload/`，库里存该前缀绝对路径，种子数据与此一致）。
- 上传逻辑重写为实例方法 `uploadFile($request, $field)` + `deleteImage($img, $default)`（原来 static 方法引用不存在的作用域 `$request` + `$_FILES`，本身跑不通）。
- Blade 里 `@if(count(session('errors')))` 已全部改为 `@if($errors->any())`（PHP 8 对 count(null) 抛 TypeError）。
- `regex: /^\w{3,20}$/` 的空格定界损坏正则已修正；`time('YmdHis')` 笔误已改 `date()`；`$sku->$request->input('num')` 已改 `(int)$request->input('num')`。
- `app/MyFunction.php`（含 Windows 字体贴图的 `BNZ_*` 死代码）已删除，只保留 `sub_title()` 于 `app/helpers.php`（composer files autoload）。原 CartController 的 `getSe`/`getLg`（会话后门）已删。
- `Admin/HelpController` import 数据用的 `getData`/`getArticleData`（依赖旧 curl 包）已删。
- 登录验证码为 `gregwar/captcha`，输出已改为 `response(...)->header('Content-Type','image/jpeg')`（原为裸 `header()`）。

## 其他注意

- 视图目录大小写过 Linux 敏感：`resources/views/admin/{Index,Order,Comment}` 已归一为小写，新视图路径全用小写。
- 控制器中 `Time time('YmdHis')`、`dump()` 等残留调试已清理；不要往回加 `dd()`/`dump()` 到生产路径。
- 测试仍然只是占位（`tests/`），无业务覆盖；不要依赖 `php artisan migrate` 或任何 CI 脚本。
- `安装文档.txt` 是老文档（Laravel 5.5 + phpstudy/winserver），除账号提示外仅具历史价值。
