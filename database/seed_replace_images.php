<?php
/*
 * 用本地真实图 (database/seed_images.php 产出的 database/mi-images.json)
 * 替换库内所有死链 hqbcdn 图片。
 *
 * - goods: showImg / img(逗号列表) / content 内嵌图 → 按商品所属分类桶配图
 * - skus:  img / info 内嵌图 → 同桶
 * - 原有手机/平板类目 (cate_id 2-12) 走 phone 专项桶
 * - cates.img / 其它表残留 hqbcdn → 兜底默认池
 * - 偏移量取实体 id, 结果确定性, 重跑可复现
 *
 * 用法: php database/seed_replace_images.php
 */

$_bk     = require __DIR__ . '/buckets.php';
$buckets = $_bk['buckets'];

$mapJson = json_decode(file_get_contents(__DIR__ . '/mi-images.json'), true);
$phoneMap = $mapJson['phone'];
$bucketImg = $mapJson['buckets'];
$defaultImg = $mapJson['default'];

$db = new PDO('mysql:host=127.0.0.1;dbname=xm;charset=utf8mb4', 'root', '123456', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$db->exec("SET time_zone = '+8:00'");
$now = date('Y-m-d H:i:s');

/* cate_id => 分类名 */
$cateNames = [];
foreach ($db->query('SELECT id, name FROM cates') as $r) {
    $cateNames[(string)$r['id']] = $r['name'];
}

/* 分类名 → 桶关键词 (与 buckets.php 同规则) */
$bucketKw = function (string $cateName): string {
    foreach ($GLOBALS['__bk_buckets'] as $item) {
        if (str_contains($cateName, $item[0])) {
            return $item[0];
        }
    }
    return '默认';
};
$GLOBALS['__bk_buckets'] = $buckets;

$imgListFor = function (string $cateId) use ($cateNames, $phoneMap, $bucketImg, $defaultImg, $bucketKw): array {
    if (isset($phoneMap[(string)$cateId]) && $phoneMap[(string)$cateId]) {
        return $phoneMap[(string)$cateId];
    }
    $kw = $bucketKw($cateNames[(string)$cateId] ?? '');
    return $bucketImg[$kw] ?? $defaultImg;
};

$HQB_RE = '#https?://img\d+\.hqbcdn\.com/[^"\'\s,<>)\]]+#i';
$replacedCount = 0;

$replaceInline = function (string $html, array $list, int $offset) use ($HQB_RE, &$replacedCount): string {
    $i = 0;
    $n = max(1, count($list));
    return preg_replace_callback($HQB_RE, function () use ($list, $offset, $n, &$i, &$replacedCount) {
        $u = $list[($offset + 100 + $i++) % $n];
        $replacedCount++;
        return $u;
    }, $html);
};

/* ---------------- goods ---------------- */
$stmtUp = $db->prepare(
    'UPDATE goods SET showImg = :s, img = :i, content = :c, updated_at = :t WHERE id = :id'
);
$goodsN = 0;
foreach ($db->query('SELECT id, cate_id, showImg, img, content FROM goods') as $g) {
    $list = $imgListFor((string)$g['cate_id']);
    if (!$list) {
        echo "[跳过] goods #{$g['id']} 无可用桶图\n";
        continue;
    }
    $n = count($list);
    $off = (int)$g['id'];
    $main = $list[$off % $n];
    $imgs = [
        $main,
        $list[($off + 1) % $n],
        $list[($off + 2) % $n],
        $list[($off + 3) % $n],
    ];
    $content = $replaceInline((string)$g['content'], $list, $off);
    $content = str_replace($g['showImg'], $main, $content); // 原主图若在 content 中一并更新

    $stmtUp->execute([
        's' => $main,
        'i' => implode(',', $imgs),
        'c' => $content,
        't' => $now,
        'id' => $g['id'],
    ]);
    $goodsN++;
}
echo "goods 更新: $goodsN\n";

/* ---------------- skus ---------------- */
/* good_id → cate_id */
$goodCate = [];
foreach ($db->query('SELECT id, cate_id FROM goods') as $g) {
    $goodCate[(int)$g['id']] = (string)$g['cate_id'];
}
$stmtUpS = $db->prepare(
    'UPDATE skus SET img = :i, info = :f, updated_at = :t WHERE id = :id'
);
$skuN = 0;
foreach ($db->query('SELECT id, good_id, img, info FROM skus') as $s) {
    $list = $imgListFor($goodCate[(int)$s['good_id']] ?? '');
    if (!$list) {
        continue;
    }
    $n = count($list);
    $off = (int)$s['id'];
    $info = $replaceInline((string)$s['info'], $list, $off);

    $stmtUpS->execute([
        'i' => $list[$off % $n],
        'f' => $info,
        't' => $now,
        'id' => $s['id'],
    ]);
    $skuN++;
}
echo "skus 更新: $skuN\n";

/* ---------------- cates / 其它表的零散残留 ---------------- */
$defaultMain = $defaultImg[0] ?? '';
$textColsOf = function (string $tbl) use ($db): array {
    $cols = [];
    foreach ($db->query("SHOW FULL COLUMNS FROM `{$tbl}`") as $c) {
        if (preg_match('#char|text#i', (string)$c['Type'])) {
            $cols[] = $c['Field'];
        }
    }
    return $cols;
};

foreach (['cates', 'navigations', 'help_articles', 'comments', 'users'] as $tbl) {
    $textCols = $textColsOf($tbl);
    if (!$textCols) {
        continue;
    }
    $where = implode(' OR ', array_map(fn ($c) => "`$c` LIKE '%hqbcdn%'", $textCols));
    $cnt = (int)$db->query("SELECT COUNT(*) FROM `{$tbl}` WHERE $where")->fetchColumn();
    if (!$cnt) {
        continue;
    }

    if ($tbl === 'cates') {
        // 分类图 → 自身桶的第一张
        foreach ($cateNames as $cid => $name) {
            $img = $bucketImg[$bucketKw($name)][0] ?? $defaultMain;
            if (!$img) {
                continue;
            }
            $st = $db->prepare("UPDATE cates SET img = :i WHERE id = :id AND img LIKE '%hqbcdn%'");
            $st->execute(['i' => $img, 'id' => $cid]);
        }
        echo "cates: 处理 $cnt 行\n";
        continue;
    }

    // 其余表: hqbcdn URL 逐处换成默认池轮换
    $di = 0;
    $dn = max(1, count($defaultImg));
    foreach ($textCols as $col) {
        $rows = $db->query("SELECT id, `$col` AS v FROM `{$tbl}` WHERE `$col` LIKE '%hqbcdn%'")->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) {
            continue;
        }
        $st = $db->prepare("UPDATE `{$tbl}` SET `$col` = :r WHERE id = :id");
        foreach ($rows as $r) {
            $newv = preg_replace_callback($HQB_RE, function () use ($defaultImg, $dn, &$di, &$replacedCount) {
                $replacedCount++;
                return $defaultImg[$di++ % $dn];
            }, (string)$r['v']);
            $st->execute(['r' => $newv, 'id' => $r['id']]);
        }
    }
    echo "$tbl: 处理 $cnt 行\n";
}

$left = 0;
foreach (['goods', 'skus', 'cates', 'navigations', 'help_articles', 'comments', 'users', 'orders', 'order_goods', 'carts'] as $tbl) {
    $cols = [];
    foreach ($db->query("SHOW FULL COLUMNS FROM `{$tbl}`") as $c) {
        if (preg_match('#char|text#i', (string)$c['Type'])) {
            $cols[] = $c['Field'];
        }
    }
    if (!$cols) {
        continue;
    }
    $where = implode(' OR ', array_map(fn ($c) => "`$c` LIKE '%hqbcdn%'", $cols));
    $l = (int)$db->query("SELECT COUNT(*) FROM `{$tbl}` WHERE $where")->fetchColumn();
    if ($l) {
        echo "!! $tbl 仍残留 $l 行 hqbcdn\n";
    }
    $left += $l;
}
echo $left === 0 ? "库内 hqbcdn 死链已清零 ✓\n" : "残留 {$left} 行待处理\n";
echo "completed, inline 替换 $replacedCount 处\n";
