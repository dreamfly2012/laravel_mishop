<?php
/*
 * 轮播图 / 促销小图 / 频道入口 数据初始化脚本
 * 与 xm.sql 手工导入体系配套，幂等可执行。
 *
 * - 若 banners 表不存在则创建
 * - 若 banners 表为空则写入默认演示数据（轮播5 / 促销3 / 频道6）
 * - 每条 banner 可关联商品 good_id（点击跳 /detail/{id}）或自定义 url
 *
 * 用法: php database/seed_banners.php
 */
$db = new PDO('mysql:host=127.0.0.1;dbname=xm;charset=utf8mb4', 'root', '123456', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$db->exec("SET time_zone = '+8:00'");

$db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS `banners` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `position` varchar(32) NOT NULL DEFAULT 'carousel' COMMENT 'carousel 轮播图 / promo 焦点图下方小图 / channel 频道入口',
  `title` varchar(255) DEFAULT NULL,
  `image` varchar(512) DEFAULT NULL COMMENT '图片路径(轮播/促销用)',
  `icon` varchar(64) DEFAULT NULL COMMENT '频道入口图标(可选)',
  `good_id` int(10) unsigned DEFAULT NULL COMMENT '关联商品 -> /detail/{good_id}',
  `url` varchar(512) DEFAULT NULL COMMENT '自定义跳转链接',
  `sort` int(11) NOT NULL DEFAULT '0',
  `status` tinyint(4) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  PRIMARY KEY (`id`),
  KEY `position` (`position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
SQL
);

$count = (int) $db->query('SELECT COUNT(*) FROM banners')->fetchColumn();
if ($count > 0) {
    fwrite(STDOUT, "banners 已有数据({$count} 条)，跳过写入\n");
    exit(0);
}

$now = date('Y-m-d H:i:s');
$rows = [
    // 轮播图（关联商品，点击跳详情）
    ['carousel', '小米新品', '/cdn/i3.mifile.cn/a4/3df93b73-1f38-41dc-9c19-c6feb05c9cc1', null, 1, null, 1],
    ['carousel', '红米系列', '/cdn/i3.mifile.cn/a4/8ebfdc81-ace7-4bfb-b07a-409e17d6d3cf', null, 2, null, 2],
    ['carousel', '小米电视', '/cdn/i3.mifile.cn/a4/eadf8c22-c83d-446b-ac04-b3ce72078388', null, 3, null, 3],
    ['carousel', '小米平板', '/cdn/i3.mifile.cn/a4/c5688819-4791-4b8f-b13e-2d29718f0ed6', null, 4, null, 4],
    ['carousel', '小米生态', '/cdn/i3.mifile.cn/a4/9ea4cb98-3628-4384-bdb1-21646222a53a', null, 5, null, 5],
    // 焦点图下方促销小图
    ['promo', '米兔儿童手表', '/cdn/i3.mifile.cn/a4/bcd96601-1406-4716-8258-975a90e8a706', null, 1, null, 1],
    ['promo', '小米手机5', '/cdn/i3.mifile.cn/a4/699590cf-1eec-4cbb-84e7-3db5d965bb0d', null, 2, null, 2],
    ['promo', '红米Note3', '/cdn/i3.mifile.cn/a4/dc80da21-68df-4d2a-9b3f-3bbea5b539a4', null, 3, null, 3],
    // 频道入口
    ['channel', '电信专场', null, null, null, '/', 1],
    ['channel', '企业团购', null, null, null, '/', 2],
    ['channel', '官翻产品', null, null, null, '/', 3],
    ['channel', '小米移动', null, null, null, '/', 4],
    ['channel', '以旧换新', null, null, null, '/', 5],
    ['channel', '话费充值', null, null, null, '/', 6],
];

$stmt = $db->prepare('INSERT INTO banners (position, title, image, icon, good_id, url, sort, status, created_at, updated_at) VALUES (?,?,?,?,?,?,?,1,?,?)');
foreach ($rows as $r) {
    $stmt->execute([$r[0], $r[1], $r[2], $r[3], $r[4], $r[5], $r[6], $now, $now]);
}

fwrite(STDOUT, 'banners 初始化完成，共写入 ' . count($rows) . " 条\n");
