<?php
/*
 * 演示数据补全脚本 (非 artisan seeder, 与 xm.sql 手工导入体系配套)
 *
 * - 给所有"无商品"的二级分类生成 1-3 个商品 + SKU(风格与既有数据一致)
 * - 补充订单/订单商品/评价/购物车样本
 * - 重新生成首页明星单品 public/data/stargoods.json
 *
 * 幂等: 只处理当前没有商品的分类; 已有数据的分类不重复生成。
 * 用法: php database/seed_extra.php
 */

$db = new PDO('mysql:host=127.0.0.1;dbname=xm;charset=utf8mb4', 'root', '123456', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$db->exec("SET time_zone = '+8:00'");

$now  = date('Y-m-d H:i:s');
$pick = fn (array $a) => $a[array_rand($a)];

/* ------------------------------------------------------------------ */
/* 1. 现有商品图池 (hqbcdn CDN 风格, 循环复用)                          */
/* ------------------------------------------------------------------ */
$imgPool = [];
$collect = function (string $text) use (&$imgPool) {
    preg_match_all('#(?:https?://[^\s"\',]+|/image/upload/[^\s"\',]+)#', $text, $m);
    foreach ($m[0] as $u) {
        $imgPool[$u] = true;
    }
};
foreach ($db->query('SELECT showImg, img, content FROM goods') as $r) {
    $collect(($r['showImg'] ?? '') . ',' . ($r['img'] ?? '') . ',' . ($r['content'] ?? ''));
}
foreach ($db->query('SELECT img, LEFT(info, 4000) AS info FROM skus') as $r) {
    $collect(($r['img'] ?? '') . ',' . ($r['info'] ?? ''));
}
$imgPool = array_keys($imgPool);
if (count($imgPool) < 5) {
    fwrite(STDERR, "图池过小, 异常\n");
    exit(1);
}
echo '图池: ' . count($imgPool) . " 张\n";
$img = fn () => $pick($imgPool);

/* ------------------------------------------------------------------ */
/* 2. 分类细分桶: [关键词 => [价格区间, SKU 版本池, 颜色池]]            */
/* ------------------------------------------------------------------ */
$COLOR_PHONES = ['灰色', '白色', '粉色', '金色', '黑色', '银色'];
$COLOR_METAL  = ['金色', '银色', '黑色'];

$TABLET_ATTRS = ['16GB WiFi版', '64GB WiFi版', '64GB 4G版'];

$buckets = [
    ['电视',     [3299, 8999], ['标准版', '影院版'],      ['金色', '黑色']],
    ['盒子',     [199, 399],   ['标准版', '增强版'],      ['黑色']],
    ['平板',     [1299, 1999], $TABLET_ATTRS,            ['金色', '银色', '粉色']],
    ['合约机',   [1999, 4999], ['合约版'],                ['白色']],
    ['无人机',   [2499, 4999], ['标准版'],                ['白色']],
    ['平衡车',   [1499, 1999], ['标准版'],                ['白色']],
    ['电饭煲',   [399, 999],   ['IH 3L', 'IH 4L'],        ['白色']],
    ['水壶',     [99, 199],    ['基础版', 'Pro'],         ['白色', '棕色']],
    ['手表',     [199, 799],   ['基础版', '粉色版'],      ['蓝色', '粉色']],
    ['血压计',   [199, 499],   ['基础版'],                ['白色']],
    ['净化器',   [899, 2499],  ['标准版', 'Pro'],         ['白色']],
    ['净水器',   [1299, 2499], ['厨下式 400G', '厨下式 600G'], ['白色']],
    ['路由器',   [129, 599],   ['3代', '4代千兆版'],      ['白色']],
    ['手环',     [79, 199],    ['标准版'],                ['黑色', '红色']],
    ['体重秤',   [99, 219],    ['基础版', '好精灵版'],    ['黑色', '白色']],
    ['放大器',   [59, 129],    ['300M', '新一代'],        ['白色']],
    ['智能灯',   [59, 229],    ['标准版', '彩色版'],      ['白色']],
    ['摄像机',   [199, 699],   ['1080P', '4K'],           ['白色']],
    ['音响',     [129, 499],   ['标准版'],                ['白色']],
    ['低音炮',   [399, 799],   ['标准版'],                ['黑色']],
    ['手柄',     [99, 199],    ['蓝牙版', '有线版'],      ['黑色', '白色']],
    ['电源',     [49, 199],    ['5000mAh', '10000mAh', '20000mAh'], $COLOR_METAL],
    ['插线板',   [49, 149],    ['3孔位', '4孔位 USB版'],  ['白色']],
    ['插座',     [59, 99],     ['基础版'],                ['白色']],
    ['耳机',     [99, 399],    ['有线版', '蓝牙版'],      $COLOR_METAL],
    ['音箱',     [129, 399],   ['标准版', 'NFC版'],       ['白色', '金色']],
    ['收音机',   [119, 229],   ['标准版'],                ['白色']],
    ['电池',     [9, 49],      ['5号4粒装', '7号4粒装'],  ['彩色']],
    ['存储卡',   [39, 199],    ['32G', '64G', '128G'],    ['白色']],
    ['充电器',   [29, 99],     ['18W快充', '30W快充'],    ['白色']],
    ['线材',     [9, 39],      ['100cm', '200cm USB-C'],  ['白色', '黑色']],
    ['保护套',   [19, 79],     ['TPU软胶壳', '磨砂硬壳'], $COLOR_PHONES],
    ['后盖',     [29, 69],     ['原装替换后盖'],          $COLOR_PHONES],
    ['贴膜',     [9, 39],      ['高清膜', '钢化膜'],      ['透明']],
    ['自拍杆',   [29, 79],     ['线控版', '蓝牙版'],      ['金色', '灰色']],
    ['贴纸',     [9, 29],      ['个性贴纸套装'],          ['彩色']],
    ['防尘塞',   [9, 19],      ['通用防尘塞'],            $COLOR_METAL],
    ['支架',     [29, 79],     ['桌面支架', '车载支架'],  ['黑色', '银色']],
    ['WiFi',    [69, 129],    ['随身WiFi'],              ['白色']],
    ['米兔',     [29, 129],    ['标准款', '大号款'],      ['米色']],
    ['服饰',     [39, 199],    ['M码', 'L码', 'XL码'],    ['黑色', '白色', '灰色']],
    ['声波',     [49, 199],    ['标准版'],                ['银色']],
    ['箱包',     [99, 299],    ['单肩包', '双肩包'],      ['黑色', '米色']],
    ['鼠标垫',   [29, 99],     ['动感版', '游戏版'],      ['黑色', '彩色']],
    ['旅行箱',   [199, 599],   ['20寸', '24寸'],          ['米色', '黑色']],
];

function bucketOf(string $cateName, array $buckets): array
{
    foreach ($buckets as [$kw, $range, $attrs, $colors]) {
        if (str_contains($cateName, $kw)) {
            return [$range, $attrs, $colors];
        }
    }
    return [[49, 299], ['标准版'], ['白色']];
}

$SLOGANS = [
    '把世界握在手里', '性能与颜值兼备', '让生活更简单一点', '轻装上阵, 快意随行',
    '千元旗舰, 感动人心', '陪伴每一天的好物', '口碑之作, 值得拥有', '为发烧而生又一年',
    '经典再升级, 越级体验', '向下不兼容, 向上不将就',
];
$NAME_SUFFIX = ['', '高配版', '尊享版', '套装版'];

/* ------------------------------------------------------------------ */
/* 3. 给无商品的二级分类生成 商品 + SKU                                 */
/* ------------------------------------------------------------------ */
$emptyCates = $db->query(
    "SELECT c.id, c.name FROM cates c
     WHERE c.pid IN (SELECT id FROM cates WHERE pid = 0) AND c.status = 1
       AND NOT EXISTS (SELECT 1 FROM goods g WHERE g.cate_id = c.id AND g.status = 1)
     ORDER BY c.id"
)->fetchAll(PDO::FETCH_ASSOC);

echo '空分类: ' . count($emptyCates) . " 个\n";

$stmtGood = $db->prepare(
    'INSERT INTO goods (title, sub_title, cate_id, price, content, img, showImg, status, created_at, updated_at)
     VALUES (:title, :sub_title, :cate_id, :price, :content, :img, :showImg, 1, :t, :t)'
);
$stmtSku = $db->prepare(
    'INSERT INTO skus (good_id, title, attr, color, falsePrice, price, img, info, stock, status, created_at, updated_at)
     VALUES (:good_id, :title, :attr, :color, :falsePrice, :price, :img, :info, :stock, 1, :t, :t)'
);

$newGoodsCount = 0;
$newSkusCount = 0;

foreach ($emptyCates as $cate) {
    $goodsN = mt_rand(1, 3);
    $usedSuffixes = [''];

    for ($g = 0; $g < $goodsN; $g++) {
        [$range, $attrs, $colors] = bucketOf($cate['name'], $buckets);

        // 标题: 分类名 + 版本后缀, 同分类不重名
        $suffix = '';
        if ($goodsN > 1) {
            foreach (array_slice($NAME_SUFFIX, 1) as $sx) {
                if (!in_array($sx, $usedSuffixes, true)) {
                    $suffix = $sx;
                    break;
                }
            }
        }
        $usedSuffixes[] = $suffix;
        $title = 'MI/小米 ' . $cate['name'] . ' ' . trim($suffix);

        $basePrice = mt_rand($range[0], $range[1]);

        // 该商品的 SKU 版本组合 (2-3 个)
        $keys = (array)array_rand($attrs, min(mt_rand(2, 3), count($attrs)));
        $usedAttrs = [];
        foreach ($keys as $k) {
            $usedAttrs[] = $attrs[$k];
        }

        $mainImg = $img();
        $subTitle = $pick($SLOGANS) . ',' . $pick($SLOGANS);
        $content = '<img src="' . $img() . '" alt="" />'
            . '<img src="' . $img() . '" alt="" />'
            . '<p style="text-align:center;">' . $subTitle . '</p>'
            . '<p style="text-align:center;">' . $cate['name'] . ' ' . $pick($SLOGANS) . '</p>';
        $imgList = implode(',', [$mainImg, $img(), $img(), $img()]);

        $stmtGood->execute([
            'title'     => $title,
            'sub_title' => $subTitle,
            'cate_id'   => $cate['id'],
            'price'     => number_format($basePrice, 2, '.', ''),
            'content'   => $content,
            'img'       => $imgList,
            'showImg'   => $mainImg,
            't'         => $now,
        ]);
        $goodId = (int)$db->lastInsertId();
        $newGoodsCount++;

        foreach ($usedAttrs as $i => $attr) {
            // 同商品不同版本按版本序加价
            $price = max(9, $basePrice + $i * max(100, intdiv($basePrice, 10)));
            $color = $pick($colors);
            $stmtSku->execute([
                'good_id'    => $goodId,
                'title'      => $title . ' ' . $attr . ' ' . $color,
                'attr'       => $attr,
                'color'      => $color,
                'falsePrice' => number_format($price * mt_rand(130, 180) / 100, 2, '.', ''),
                'price'      => number_format($price, 2, '.', ''),
                'img'        => $img(),
                'info'       => '<table class="cs_tab"><tr><td>品牌</td><td>小米/Xiaomi</td></tr>'
                    . '<tr><td>型号</td><td>' . $attr . '</td></tr><tr><td>颜色</td><td>' . $color . '</td></tr>'
                    . '<tr><td>保修</td><td>官方一年质保</td></tr></table>',
                'stock'      => (string)mt_rand(30, 500),
                't'          => $now,
            ]);
            $newSkusCount++;
        }
    }
}

echo "新增 goods: $newGoodsCount, 新增 skus: $newSkusCount\n";

/* ------------------------------------------------------------------ */
/* 4. 订单样本 (已生成过则跳过, 保证脚本可重复执行)                     */
/* ------------------------------------------------------------------ */
// 用 order_num > 1700000000 (2023+) 作为"本脚本已生成订单"的标记
$hasNewOrders = (int)$db->query(
    "SELECT COUNT(*) FROM orders WHERE CAST(order_num AS UNSIGNED) > 1700000000000000"
)->fetchColumn() > 0;

$PROVINCES = [
    ['北京', '北京市'], ['上海', '上海市'], ['广东', '广州市'], ['浙江', '杭州市'],
    ['江苏', '南京市'], ['四川', '成都市'], ['湖北', '武汉市'], ['陕西', '西安市'],
];
$DISTRICTS = ['朝阳区', '海淀区', '浦东新区', '天河区', '西湖区', '玄武区', '锦江区', '洪山区'];
$NAMES = ['王小明', '李华东', '张伟强', '刘敏', '陈晨', '赵晓丽', '孙浩然', '周芳', '吴迪', '郑帅'];
$REVIEWS = [
    '手机很好, 手感一流, 官网同款!', '物流很快, 包装严实, 很满意。',
    '做工精细, 屏幕显示效果好, 点赞。', '音质超出预期, 性价比高。',
    '能用很多年, 米粉放心买。', '客服很耐心, 物流快, 好评!',
    '和描述一致, 用着不错。', '电池很耐用, 充电也快。',
];
$CN = function () { return '1' . substr(mt_rand(30, 91) . mt_rand(10000000, 99999999), 0, 10); };

if (!$hasNewOrders) {
    // 随机挑一批 SKU (新老混合) 作为下单素材
    $skuRows = $db->query(
        'SELECT s.id, s.title, s.price, s.color, s.attr, g.id AS good_id
         FROM skus s JOIN goods g ON g.id = s.good_id
         WHERE s.status = 1 ORDER BY RAND() LIMIT 60'
    )->fetchAll(PDO::FETCH_ASSOC);

    $stmtOrder = $db->prepare(
        'INSERT INTO orders (user_id, order_status, pay_status, order_num, pack_fee, pack_time, total_price, consignee, province, city, district, address, phone, created_at, updated_at)
         VALUES (:u, :os, :ps, :on, 10, 0, :tp, :cs, :pv, :ct, :ds, :ad, :ph, :t, :t)'
    );
    $stmtOg = $db->prepare(
        'INSERT INTO order_goods (order_id, sku_id, price, num, goods_name, goods_attr, color, created_at, updated_at)
         VALUES (:o, :s, :p, :n, :gn, :ga, :c, :t, :t)'
    );

    $orderN = mt_rand(9, 12);
    for ($i = 0; $i < $orderN; $i++) {
        $userId = mt_rand(2, 53);
        [$pv, $ct] = $pick($PROVINCES);
        $ts = strtotime('-' . mt_rand(0, 45) . ' days') - mt_rand(0, 86400);
        $created = date('Y-m-d H:i:s', $ts);

        // 每单 1-3 件不同 SKU
        $picked = [];
        while (count($picked) < mt_rand(1, 3)) {
            $s = $pick($skuRows);
            $picked[$s['id']] = $s;
        }
        $total = 0;
        foreach ($picked as $s) {
            $total += (float)$s['price'] * mt_rand(1, 2);
        }
        $total = $total + 10; // pack_fee

        $stmtOrder->execute([
            'u'  => $userId,
            'os' => mt_rand(0, 3),
            'ps' => mt_rand(0, 1),
            'on' => $ts . mt_rand(100000, 999999),
            'tp' => number_format($total, 2, '.', ''),
            'cs' => $pick($NAMES),
            'pv' => $pv, 'ct' => $ct, 'ds' => $pick($DISTRICTS),
            'ad' => $ct . ' ' . $pick($DISTRICTS) . ' xx路' . mt_rand(1, 200) . '号',
            'ph' => $CN(),
            't'  => $created,
        ]);
        $orderId = (int)$db->lastInsertId();

        foreach ($picked as $s) {
            $stmtOg->execute([
                'o'  => $orderId,
                's'  => $s['id'],
                'p'  => $s['price'],
                'n'  => mt_rand(1, 2),
                'gn' => $s['title'],
                'ga' => $s['attr'],
                'c'  => $s['color'],
                't'  => $created,
            ]);
        }
    }
    echo "新增 orders: $orderN\n";
}

/* ------------------------------------------------------------------ */
/* 5. 评价样本 (老数据 star 全是 '0', 用 star<>0 当"已生成"标记)        */
/* ------------------------------------------------------------------ */
$hasNewComments = (int)$db->query("SELECT COUNT(*) FROM comments c JOIN goods g ON c.good_id = g.id WHERE g.id > 17")->fetchColumn() > 0;

if (!$hasNewComments) {
    $stmtC = $db->prepare(
        "INSERT INTO comments (content, good_id, user_id, pid, path, img, star, useless, status, created_at, updated_at)
         VALUES (:c, :g, :u, 0, '0', '', :st, '', 1, :t, :t)"
    );
    $goodIds = array_column(
        $db->query('SELECT id FROM goods WHERE status = 1 ORDER BY RAND() LIMIT 40')->fetchAll(PDO::FETCH_ASSOC),
        'id'
    );
    for ($i = 0; $i < 15; $i++) {
        $stmtC->execute([
            'c'  => $pick($REVIEWS),
            'g'  => $pick($goodIds),
            'u'  => mt_rand(2, 53),
            'st' => $pick(['4.0', '4.5', '5.0']),
            't'  => date('Y-m-d H:i:s', strtotime('-' . mt_rand(0, 45) . ' days')),
        ]);
    }
    echo "新增 comments: 15\n";
} else {
    echo "comments 已有新评价, 跳过\n";
}

/* ------------------------------------------------------------------ */
/* 6. 购物车样本 (仅当 carts 为空时填充)                                */
/* ------------------------------------------------------------------ */
$cartCount = (int)$db->query('SELECT COUNT(*) FROM carts')->fetchColumn();
if ($cartCount === 0) {
    $skuRows2 = $db->query(
        'SELECT s.id, s.title, s.attr, s.color, s.good_id FROM skus s WHERE s.status = 1 ORDER BY RAND() LIMIT 6'
    )->fetchAll(PDO::FETCH_ASSOC);
    $stmtCart = $db->prepare(
        'INSERT INTO carts (user_id, sku_id, goods_id, goods_name, goods_attr, goods_num, created_at, updated_at)
         VALUES (:u, :s, :g, :n, :a, :c, :t, :t)'
    );
    foreach ($skuRows2 as $s) {
        $stmtCart->execute([
            'u' => mt_rand(1, 2),
            's' => $s['id'],
            'g' => $s['good_id'],
            'n' => $s['title'],
            'a' => $s['color'],
            'c' => mt_rand(1, 2),
            't' => $now,
        ]);
    }
    echo '新增 carts: ' . count($skuRows2) . "\n";
}

/* ------------------------------------------------------------------ */
/* 7. 重新生成首页明星单品 public/data/stargoods.json                   */
/* ------------------------------------------------------------------ */
$rows = $db->query(
    'SELECT title, id, price, sub_title, showImg, RAND() rd FROM goods WHERE status = 1 ORDER BY id < 18 DESC, rd LIMIT 6'
)->fetchAll(PDO::FETCH_ASSOC);
$stars = [];
foreach ($rows as $r) {
    $stars[] = [
        'title'    => $r['title'],
        'id'       => (int)$r['id'],
        'price'    => $r['price'],
        'sub_title' => (string)$r['sub_title'],
        'showImg'  => $r['showImg'],
    ];
}
file_put_contents(
    __DIR__ . '/../public/data/stargoods.json',
    json_encode($stars, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
);
echo "stargoods.json 已更新 (6 个商品)\n";

echo "完成\n";

