# 米商城 MiShop

一个仿小米商城的手机端电商演示项目，包含完整的**前台购物流程**与**后台管理系统**。

> 仅用于学习与技术演示，非商业项目。

---

## 目录

- [项目简介](#项目简介)
- [功能一览](#功能一览)
- [环境要求](#环境要求)
- [快速开始](#快速开始)
- [使用指南](#使用指南)
- [演示数据](#演示数据)
- [访问入口](#访问入口)
- [常见问题](#常见问题)
- [免责声明](#免责声明)

---

## 项目简介

米商城是一个“麻雀虽小、五脏俱全”的电商 demo：

- **前台**：首页、分类列表、搜索、商品详情（多 SKU）、购物车、下单、支付（模拟）、个人中心、收藏、评价、帮助中心。
- **后台**：用户、分类、商品、SKU、评价、订单管理，以及首页导航 / 明星单品、帮助中心内容配置。
- **数据**：随仓库提供 `xm.sql`，导入即得一套完整演示数据（148 个商品 / 282 个 SKU / 89 个分类 / 25 个订单 / 68 条评价），商品图片全部本地化，不依赖外链。
- **零前端构建**：所有 CSS/JS/图片以静态资源形式直接提交，无需 npm / Vite。

**技术栈**：PHP >= 8.2、Laravel 12、MySQL（库名 `xm`，utf8mb4）、Blade 模板、原生 HTML/CSS/jQuery、UEditor 富文本、`gregwar/captcha` 验证码、文件会话 / 缓存。

---

## 功能一览

### 前台

| 模块 | 说明 |
| --- | --- |
| 首页 | 轮播、侧边分类导航、明星单品；导航与明星单品数据来自 `public/data/*.json` |
| 分类 / 搜索 | `/list/{id}` 分类列表、`/list-search?kWord=xx` 关键词搜索 |
| 商品详情 | 商品图集、规格参数、SKU 切换（价格 / 库存联动）、用户评价 |
| 注册 / 登录 | 用户名密码登录，注册、找回密码，图形验证码（`gregwar/captcha`） |
| 购物车 | 未登录存 Session，登录后存 `carts` 表；支持数量增减、单选 / 全选、实时小计 |
| 订单 | 确认订单、选择收货地址、创建订单、48 小时支付倒计时、模拟支付 |
| 个人中心 | 我的订单、待收货、收货地址、我的评价、退出登录 |
| 商品收藏 | `/collect?id=xx` 收藏 / 取消收藏切换；未登录返回 JSON 提示跳转登录 |
| 评价 | 已购商品发表评价（需登录） |
| 帮助中心 | 帮助分类与文章详情 |

### 后台

| 模块 | 说明 |
| --- | --- |
| 用户管理 | 用户的增删改查、头像与密码修改 |
| 分类管理 | 多级商品分类维护 |
| 商品管理 | 商品增删改查、富文本详情（UEditor）、商品图上传 |
| SKU 管理 | 按商品维护 SKU、价格、库存、规格图 |
| 评价管理 | 评价增删改查、配图上传 |
| 订单管理 | 订单列表、详情、状态修改 |
| 网站导航 | 首页导航栏目的增删改与排序 |
| 首页管理 | 分类导航脚本更新、明星单品管理 |
| 帮助中心 | 帮助栏目与文章管理 |

---

## 环境要求

- PHP >= 8.2，需启用 `pdo_mysql`、`mbstring`、`gd`、`fileinfo`、`openssl` 扩展
- MySQL 5.7+ / 8.0（或 MariaDB 10.3+）
- Composer 2.x
- Web 根目录需指向 `public/`
- 无需 Node.js / npm

---

## 快速开始

### 1. 安装依赖

```bash
composer install
```

### 2. 配置环境变量

```bash
cp .env.example .env      # Windows: copy .env.example .env
php artisan key:generate
```

修改 `.env` 中的数据库、会话与缓存配置（**必须使用 MySQL，且会话 / 缓存不能走数据库驱动**）：

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=xm
DB_USERNAME=root
DB_PASSWORD=123456

SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
```

### 3. 导入数据库

创建数据库并导入 `xm.sql`（`xm.sql` 是唯一数据源，含表结构与完整演示数据）：

```bash
mysql -u root -p -e "CREATE DATABASE xm DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p xm < xm.sql
```

也可直接用 Navicat / phpMyAdmin 新建 `xm` 库后导入。

### 4. 启动

```bash
php artisan serve
```

访问 <http://127.0.0.1:8000>。

生产部署时将站点根目录绑定到 `public/`，并确保 `storage/`、`bootstrap/cache/` 可写。

### 5. 后台登录

- 地址：<http://127.0.0.1:8000/admin/login>
- 账号：`admin`
- 密码：`iloveyou`

> 后台要求管理员账号 `id` 为 `1`，请勿修改其 `id`。

---

## 使用指南

### 前台购物流程

1. 打开首页，点击分类或搜索进入商品列表。
2. 进入商品详情页，选择 SKU（颜色 / 版本），调整数量后加入购物车。
3. 在购物车中勾选商品、修改数量，点击结算。
4. 登录（或先登录再购物），确认订单并选择收货地址。
5. 提交订单后进入支付页，选择支付方式完成**模拟支付**（不接入真实支付渠道）。
6. 在“个人中心 → 我的订单”查看订单状态，收货后可对商品发表评价。
7. 商品详情页点击收藏图标可收藏 / 取消收藏。

### 后台管理流程

1. 登录后台，默认进入用户列表。
2. 在「分类管理」维护分类树 →「商品管理」新增商品并上传图片、填写富文本详情 →「Sku 管理」为商品配置规格、价格与库存。
3. 在「评价管理」「订单管理」处理用户评价与订单状态。
4. 在「网站导航管理」「首页管理」维护首页导航、明星单品。
5. 在「帮助栏目管理」维护帮助中心分类与文章。

---

## 演示数据

`xm.sql` 已包含完整的演示数据（148 商品 / 282 SKU / 89 分类 / 25 订单 / 68 评价），导入即可使用；商品图片全部本地化在 `public/image/upload/`，运行时不依赖任何外部图床。

---

## 访问入口

| 路径 | 说明 |
| --- | --- |
| `/` | 前台首页 |
| `/list/1` | 分类商品列表 |
| `/list-search?kWord=小米` | 商品搜索 |
| `/detail/1` | 商品详情 |
| `/cart` | 购物车 |
| `/login`、`/login/register` | 登录、注册 |
| `/user/order` | 个人中心订单（需登录） |
| `/admin/login` | 后台登录 |

---

## 常见问题

**后台登录后仍被踢回登录页？**
检查管理员账号的 `users.id` 是否为 `1`——后台判定条件是该账号 `id` 必须为 `1`。

**验证码不显示或报 GD 错误？**
确认 PHP 已启用 `gd` 扩展。

**提交表单报 419 Page Expired？**
CSRF Token 失效。表单需包含 `{{ csrf_field() }}`，AJAX 请求需带 `X-CSRF-TOKEN` 请求头，并确认 Session 正常写入。

**报错 “Base table or view not found: sessions / cache”？**
`.env` 中仍在使用 database 驱动，改为 `SESSION_DRIVER=file`、`CACHE_STORE=file` 后执行 `php artisan config:clear`。

**商品图片 404？**
确认 `public/image/upload/` 下存在 `mi-*.jpg|png|webp` 文件，且 Web 根目录指向 `public/`。

**首页导航或明星单品不更新？**
后台修改后执行 `php database/regen_navdata.php`，或检查 `public/data/` 目录写权限。

---

## 免责声明

本项目仅用于 Web 开发学习与演示。“小米”、“MI” 等商标及相关商品图片版权归小米公司所有；如涉及侵权，请联系删除。
