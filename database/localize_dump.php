<?php
/**
 * 把 xm.sql 里 cates.img / help_articles.content 的外链改成本地路径，
 * 保证重新导入 dump 也不会带回 mifile.cn 外链。
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$file = __DIR__ . '/../xm.sql';
$sql  = file_get_contents($file);
$orig = $sql;

/* ---------- cates: 只改 cates 的 INSERT 行 ---------- */
$lines = preg_split('~(\r\n)~', $sql, -1, PREG_SPLIT_DELIM_CAPTURE);
$catesIdx = null; $helpIdx = null;
foreach ($lines as $i => $l) {
    if (str_starts_with($l, 'INSERT INTO `cates`')) $catesIdx = $i;
    if (str_starts_with($l, 'INSERT INTO `help_articles`')) $helpIdx = $i;
}
echo "cates 行=$catesIdx help 行=$helpIdx\n";

$c = $lines[$catesIdx];
$n1 = 0;
$c = preg_replace_callback("~'((?:https?:)?//)(c[0-9]+\\.mifile\\.cn/[^']*)'~i", function ($m) use (&$n1) {
    $n1++;
    return "'/cdn/" . $m[2] . "'";
}, $c);
// 截断的 "!" 补全
$c = str_replace("scoket140list!'", "scoket140list!70x70.jpg'", $c);
// 脏值 111
$c = str_replace("'电视盒子配件','111'", "'电视盒子配件',''", $c);
$lines[$catesIdx] = $c;
echo "cates 改写 $n1 处\n";

/* ---------- help_articles: 按顺序把外链换成 DB 里的本地图 ---------- */
preg_match_all("~mifile\\.cn/[^'\"\\\\\\\\ ]+~", $lines[$helpIdx], $m);
$dumpUrls = $m[0];
$local = [];
foreach (DB::table('help_articles')->orderBy('id')->pluck('content') as $html) {
    if (preg_match_all('~/image/help/[^"\' ]+~', (string) $html, $mm)) {
        foreach ($mm[0] as $u) $local[] = $u;
    }
}
echo 'dump 外链 ' . count($dumpUrls) . ' / DB 本地图 ' . count($local) . "\n";
if (count($dumpUrls) !== count($local)) { exit("数量不一致，放弃改写\n"); }

$line = $lines[$helpIdx];
foreach ($dumpUrls as $i => $u) {
    $pos = strpos($line, $u);
    if ($pos === false) { exit("找不到第 $i 个外链\n"); }
    // 同时吃掉前面的协议/主机部分（//i3.mifile.cn/a4/x.jpg 或 http://... ）
    $start = $pos;
    while ($start > 0 && !in_array($line[$start - 1], ["'", '"'], true)) $start--;
    $line = substr($line, 0, $start) . $local[$i] . substr($line, $pos + strlen($u));
}
$lines[$helpIdx] = $line;
echo "help_articles 改写 " . count($dumpUrls) . " 处\n";

$sql = implode('', $lines);
if ($sql === $orig) { echo "无变化\n"; exit; }
file_put_contents($file, $sql);
echo "已写入 xm.sql, mifile 残留=" . substr_count($sql, 'mifile') . "\n";