# AGENTS.md

米商城 demo 电商应用（手机商城）。Laravel 12 / PHP 8.4 + MySQL，UI 文案、种子数据为中文。

## 代码结构

- **框架**: Laravel 12（`composer.json`），PHP >= 8.2；路由为现代 `bootstrap/app.php` 骨架 + `routes/web.php` 显式路由。
- **模型**: `app/Models/`（`Good`、`Sku`、`Cart`、`Order`、`OrderGoods`、`Address`、`Cate`、`Comment`、`HelpArticle`、`HelpCate`、`Navigation`、`Banner`、`Collect`、`User`），关系用字符串类名 `'App\Models\X'`。
- **控制器**: `app/Http/Controllers/Home/*`（前台）与 `app/Http/Controllers/Admin/*`（后台）。
- **中间件别名**（在 `bootstrap/app.php` 注册）: `login` → 守卫后台（要求 `session('user_id')==1`）；`home` → 守卫个人中心/订单/地址。
- **工具类**: `app/Support/ImageUpload.php`（图片上传，`ImageUpload::store/storeMany`）、`app/helpers.php`（composer files autoload，含 `sub_title()`）。
- **无前端构建**: `public/homes`(前台样式)、`public/back`(后台)、`public/ueditor`、`public/{data,image,uploads}` 均为直接提交的静态资源；`vite.config.js`/`package.json` 存在但不用。

## Setup

1. 建 MySQL 库名 `xm` 并导入根目录 `xm.sql`——这是唯一数据源（含完整演示数据：148 商品 / 282 SKU / 89 分类全有商品 / 25 订单 / 68 评价；数据可用 `php database/seed_extra.php` 重新生成，该脚本只给"无商品的分类"填充，幂等）。**商品图已全部本地化**（`public/image/upload/mi-*.jpg|png|webp`，182 张真实小米官方图，站点不依赖任何外图链）。`php artisan migrate` **不要运行**（`database/migrations/` 只是框架默认三张表，与 `xm` 里既有的 `users` 等表冲突）。
2. `cp .env.example .env` 然后改 DB_*（本地 phpstudy 为 root/123456）并 `php artisan key:generate`。`.env` 关键项: `SESSION_DRIVER=file`、`CACHE_STORE=file`（`xm` 库没有 sessions/cache 表）。
3. `composer install`（已配置阿里云 composer 镜像 repository）；`php artisan serve` 即可。
4. 后台登录 `/admin/login`，账号 `admin` / `iloveyou`。`LoginMiddleware` 要求 `user_id==1`，别动 admin 的 id。

### 图片/数据补全管线（脚本顺序，均可重复执行）

1. `php database/seed_extra.php` — 幂等补全商品/SKU/订单/评价（只处理"无商品的分类"），重生成 `public/data/stargoods.json`。
2. `php database/seed_images.php` — 双源抓真实小米官图：mi.com 型号页（老款静态页含页面专属 `mifile.cn/f/i/<年>/<slug>/` 图）+ cn.bing.com 图片搜索（域名白名单：小米官方 CDN 优先、国内电商/数码媒体图床兜底；原图失败退 Bing 缩略图）。下载→GD 压宽 1000px→`public/image/upload/mi-*`（流式落盘防内存爆）→产出 `database/mi-images.json`（分类桶 → 本地图列表）。外网需可达 mi.com / cn.bing.com / 大陆 CDN。
3. `php database/seed_replace_images.php` — 按桶把库内全部 hqbcdn 外链图换成 `mi-*` 本地图（goods/skus/cates；偏移量取实体 id，重跑结果稳定），末尾全库复查残留。
4. `php database/seed_banners.php` — `banners` 表初始化：表不存在则创建，空表时写 14 条演示数据（轮播 5 / 促销 3 / 频道 6），幂等。
5. `php database/regen_navdata.php` — 重生成 `public/data/indexNav.js`（逻辑同 Admin/IndexController::getAjaxcreate，后台改导航后亦可手动刷）。
6. `php database/seed_help_articles.php` — 帮助中心文章：把 `help_articles.content` 里的 `mifile.cn` 外链图下载到 `public/image/help/a{id}_{n}.jpg` 并改成本地路径（同时去掉写死的宽高内联样式）、修正坏掉的 `target=".../_blank"`；正文数据在 `database/help_articles_content.php`，按 id 覆盖过短文章（幂等，图已存在则跳过）。
7. 分类桶定义集中在 `database/buckets.php`（关键词→价格区间/SKU 版本/颜色桶），`seed_extra.php` 与配图预案共用，保持生成风格与配图归类一致。改桶后需重跑 2-3 补图。
8. `php database/localize_cdn.php` — 把模板/样式/脚本里的 `*.mifile.cn` 外链（图片、样式、字体，含 CSS 内部 `url()`）下载到 `public/cdn/<host>/<path>` 并把引用改写成 `/cdn/...`，顺带修 `cates.img` 的脏值（`111`、截断的 `!`）；`--dry` 只预览。**保留在线的只有埋点脚本**（`xmst.js`/statData 之类，纯统计不影响渲染）。`php database/localize_dump.php` 把同样处理同步进 `xm.sql`（`cates.img` → `/cdn/...`、`help_articles.content` → `/image/help/...`），重新导入不会带回外链。若 `cates.img` 被误清空，用 `php database/restore_cates_img.php` 从 dump 原始数据恢复（80 行本地图 + 9 行顶级分类空图）。

## 路由

- 全站统一规范：路径全小写 + 连字符（`/list-search`、`/order/change-status`、`/admin/index-page/nav-list`），资源 id 走路径参数（`/detail/1`、`/admin/good/edit/1`），搜索/筛选/分页保留 query（`?kWord=`、`?s=`、`?filter=`、`?page=`）。前台旧地址（`/list?id=`、`/detail?id=`、`/help/article?id=`、`/comment?id=`、`/order/pay?id=`、`/user/packin?id=`）在控制器内 301 到新地址。改路由前先检索视图与 JS 中的实际引用。
- 购物车 `/cart/*` 不挂 `home` 中间件（未登录在方法内返回 JSON 让前端提示跳登录）；`/comment/insert` 挂 `home`。
- CSRF 正常开启：表单用 `{{csrf_field()}}`，AJAX 用 `<meta name="csrf-token">` + `X-CSRF-TOKEN` 头（`public/homes/common/myjs/*.js`），测试脚本需先取 `/cart` 或表单页的 token。

## 搜索（Laravel Scout + Meilisearch）

- 前台搜索走 `Home/ListController::List_Search`（`/list-search?kWord=`），核心在 `searchGoods()`：已配置的搜索引擎（Meilisearch/Algolia）可达时走 `Good::search()`（中文部分匹配 + 拼写容错）；否则 / 不可达时 **自动退回到数据库 `LIKE`（title / sub_title / 分类名）**，保证 demo 无外部依赖也能用。
- `Good` 模型用 `Laravel\Scout\Searchable`，`toSearchableArray()` 含 `title`/`sub_title`/`category`/`content`（`category` 是虚拟字段，仅进 Meilisearch 索引；数据库驱动不会用到它，所以 `searchGoods()` 只在 meilisearch/algolia 时调 `Good::search()`，避免 DatabaseEngine 把数组键当列名报错）。
- 驱动配置：`config/scout.php`（已 publish），`.env` 用 `SCOUT_DRIVER=meilisearch` + `MEILISEARCH_HOST` + `MEILISEARCH_KEY`。依赖 `laravel/scout ^10.20` 与 `meilisearch/meilisearch-php ^1.16`。
- **本地起 Meilisearch 并建索引**（可选，不跑也能搜索，只是没有容错）：
  - 用环境变量传入 master key，且须与 `.env` 的 `MEILISEARCH_KEY` 一致：
    `docker run -d --name meili -p 7700:7700 -e MEILI_MASTER_KEY=masterKey getmeili/meilisearch:v1.16`
  - 若启动时不带 key，则必须把 `.env` 的 `MEILISEARCH_KEY` 留空（否则 403）；带 key 时两者必须一致。
  - `php artisan scout:import "App\\Models\\Good"`（建索引需要写权限，故 `MEILISEARCH_KEY` 须含写+搜权限，demo 直接用 master key 即可）
- 测试：`phpunit.xml` 设 `SCOUT_DRIVER=database`，`tests/Feature/SearchTest.php` 覆盖标题 / 副标题 / 分类名搜索、无匹配、空关键词。搜索结果区在视图里用 `.goods-item` 包裹，侧栏「最新」用 `<dd>`，断言据此隔离。

## 列表排序

- 商品列表页 `Home/ListController::list_` 支持三个排序维度和正序/倒序切换：`?sort=price|new|sales_volume&order=ASC|DESC`（默认 `sales_volume` + `DESC`）。
- `price` → `orderBy('price')`，`new` → `orderBy('created_at')`；`sales_volume` 没有库存字段，销量由关联子查询实时算出：`(SELECT COALESCE(SUM(og.num),0) FROM order_goods og JOIN skus s ON s.id=og.sku_id WHERE s.good_id=goods.id)`。
- 视图 `home/list.blade.php` 为每个 tab 计算「下一个顺序」（ASC↔DESC 翻转）和箭头（▲/▼跟随当前排序），当前激活 tab 带 `curr` 类；`$listBase` 保留分类/筛选 query 只替换 sort/order。
- 测试：`tests/Feature/ListSortTest.php` 覆盖价格/上架时间四个方向、销量统计、切换时箭头翻转。

## 支付流程（本地沙箱收银台）

- 完整的「真实」支付链路，但**不接任何外部商户号**，可纯本地调试：选支付方式 → `getPayfor` 跳转本地收银台 `/pay/gateway/{id}?method=alipay|wechat|mipay` → 点「确认支付」→ POST `/pay/notify/{id}`（模拟网关回调）→ 订单 `order_status/pay_status` 置 1 → 跳回 `/order/change-status?order_id=` 渲染成功页。
- 支付方式白名单在 `OrderController::PAY_METHODS`（alipay / wechat / mipay）。`getPayfor` 校验 method 合法且订单归属当前用户且未支付。
- `notify` 是支付结果回调：真实环境应验签，本地沙箱改为校验「订单归属 + 金额未被篡改（以库内 `total_price` 为准）」后落库。金额用 `number_format(...,2,'.','')` 显示两位小数。
- `changeStatus` 仅作同步返回页：已支付才显示成功，否则回支付页。
- **并发与幂等**：`notify` 不用「先查后写」，改为原子条件更新 `where('id',..)->where('order_status',0)->update([...])` + 影响行数判断——网关回调重发/并发时只有一个请求能入账，其余影响 0 行（已支付则照常回成功页，非待支付状态回 400）。
- **下单事务与库存**：`postCreate` 用 `DB::transaction(闭包)` 包住「预扣库存 → 写订单 → 写 order_goods → 清购物车」四步，任一步抛异常整体回滚（不会留下半个订单）。库存扣减用条件 UPDATE 防超卖：`whereRaw('stock + 0 >= ?',[$num])->decrement('stock',$num)`，影响 0 行即库存不足 → status=5（前端 alert msg）。**注意 `skus.stock` 在库里是 varchar**：直接 `where('stock','>=',$num)` 在 SQLite（测试环境）会退化成字符串比较（'10' < '2'），必须写 `stock + 0` 强制数值化，MySQL/SQLite 行为才一致。
- 测试：`tests/Feature/PaymentFlowTest.php` 覆盖下单页金额/方式、跳转网关、非法方式拒绝、网关确认按钮、notify 置已支付、金额被篡改返回 400、成功页、越权（不能付他人订单）、重复回调幂等、非待支付订单拒绝；`tests/Feature/OrderFlowTest.php` 覆盖下单扣库存、库存不足整体回滚。

## 首页展示位（轮播图 / 促销小图 / 频道入口）

- 首页 carousel 轮播图、焦点图下方 promo 促销小图、频道入口 channel 三块由 `banners` 表驱动，后台可增删改。
- **表结构**：`banners`（`position` carousel/promo/channel、`title`、`image`、`icon`、`good_id`、`url`、`sort`、`status`）。跳转优先级在 `Banner::link()`：`good_id` → `/detail/{good_id}`，否则 `url`，都没有回 `/`。**不放 php artisan migrate**：`banners` 已写进 `xm.sql`，并配幂等脚本 `php database/seed_banners.php`（建表 + 空表时写 14 条演示数据：轮播 5 / 促销 3 / 频道 6）。
- **后台**：`Admin/BannerController`，路由 `/admin/banner/list|add|edit|delete/{id}|update/{id}`（update 为 ibutton 状态切换 AJAX），视图在 `resources/views/admin/banner/`。左侧「首页管理」菜单已加入口。`POSITIONS` 常量定义三个展示位，position 非法直接 `back()->with('error')`。图片支持上传（`ImageUpload::store`）或直接填路径。
- **前台**：`Home/IndexController::index` 按 `position + status=1` 查三条（`$carousel/$promo/$channel`）传给 `home/index.blade.php`。轮播图分页圆点改用 SuperSlide `autoPage:true`（`public/homes/common/myjs/index.js`），圆点数自动跟随轮播图数量，改后台轮播数量不用动 HTML。
- **测试**：`tests/Feature/BannerTest.php` 覆盖后台 CRUD / 状态切换 / 非法 position、前台 carousel、promo 跳 `/detail/{id}`、channel 走自定义 url、禁用 banner 不渲染。

## 商品收藏

- `collects` 表 + `Collect` 模型 + `CollectController`，路由 `GET /collect`（同 `/cart` 不挂 `home` 中间件，未登录在方法内返回 `{status:1, redirect:'/login'}` 让前端提示跳登录）。收藏/取消为切换语义。

## 其他注意

- 登录验证码为 `gregwar/captcha`（`/login/captcha`，登录/注册共用）：4 位小写字母数字（排除 0/o/1/l/i），关掉波纹扭曲和压字干扰线，浅底深字；输出改 `imagepng($builder->getGd())` 无损返回 `image/png`。图片 125x46 与 CSS 自然尺寸一致，避免浏览器拉伸发虚；校验用 `strcasecmp` 忽略大小写（部分字体把字符画成大写）。
- 前台所有页面 0 外链请求（小米自家统计/推荐脚本已全部摘除，文件也已删）。视图里残留的 `onclick="_msq.push([...]"` 由 `layout/index.blade.php` head 里的一行空实现兜住（否则点击会抛 `ReferenceError`），别把它删了。
- 不要往回加 `dd()`/`dump()` 到生产路径；新视图路径全用小写。
- **测试**：`tests/Feature` 覆盖前台页面/旧地址 301/登录注册/购物车/收藏/下单支付/地址/后台 CRUD/搜索/排序/展示位与两个守卫，`tests/Unit` 覆盖 `sub_title()`。测试使用 `phpunit.xml` 里的 sqlite `:memory:`，业务表结构在 `tests/TestCase.php` 手写（**不跑 `database/migrations`，不读也不写 `xm`**）；直接 `php artisan test`。
- `安装文档.txt` 是早期文档，仅具历史参考价值。
