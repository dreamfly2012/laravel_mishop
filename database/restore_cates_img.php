<?php
/**
 * 修复 localize_cdn.php 早期版本误清空的 cates.img：
 * 从 xm.sql 的原始 INSERT 里取回每行图片地址，映射到 public/cdn/ 下的本地文件。
 * 幂等；缺文件会现下载，下载失败则保留原外链（不写空值）。
 */
$root = dirname(__DIR__);
$publicDir = $root . '/public';
$dry = in_array('--dry', $argv, true);

$sql = file_get_contents($root . '/xm.sql');
if (!preg_match('~INSERT INTO `cates` VALUES (.*?);\s*$~ms', $sql, $m)) {
    exit("找不到 cates 的 INSERT 语句\n");
}
preg_match_all("~\\((\\d+),'((?:[^'\\\\]|\\\\.)*)','((?:[^'\\\\]|\\\\.)*)'~", $m[1], $rows, PREG_SET_ORDER);
echo '从 xm.sql 解析出 ' . count($rows) . " 行\n";

$UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36';
$fetch = function (string $url, string $dest) use ($UA, $dry): bool {
    if ($dry) return true;
    $dir = dirname($dest);
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    if (is_file($dest) && filesize($dest) > 0) return true;
    $fp = @fopen($dest . '.part', 'wb');
    if (!$fp) return false;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FILE => $fp, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 40, CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_USERAGENT => $UA,
        CURLOPT_REFERER => 'https://www.mi.com/', CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $good = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fp);
    if (!$good || $code >= 400) { @unlink($dest . '.part'); return false; }
    rename($dest . '.part', $dest);
    return true;
};

require $root . '/vendor/autoload.php';
$app = require $root . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$todo = [];   // id => new value
$keep = [];   // 保外链的
foreach ($rows as $r) {
    $id  = (int) $r[1];
    $img = str_replace(["\\'", "\\\\"], ["'", "\\"], $r[3]);
    $img = trim($img);
    if ($img === '') { $todo[$id] = ''; continue; }
    if (!preg_match('~^(?:https?:)?//~', $img)) { $todo[$id] = ''; continue; } // "111" 之类脏值
    if (str_ends_with($img, '!')) $img .= '70x70.jpg';                          // 截断的 "!"
    $url  = str_starts_with($img, '//') ? 'https:' . $img : $img;
    $p    = parse_url($url);
    $key  = ($p['host'] ?? '') . ($p['path'] ?? '');
    $local = '/cdn/' . $key;
    $dest  = $publicDir . $local;
    if (!is_file($dest) || filesize($dest) <= 0) {
        if (!$fetch($url, $dest)) {
            $alt = preg_replace('~^https:~', 'http:', $url);
            if (!($alt !== $url && $fetch($alt, $dest))) {
                $keep[$id] = $img;
                continue;
            }
        }
    }
    $todo[$id] = $local;
}

$changed = 0;
foreach ($todo as $id => $val) {
    $cur = (string) DB::table('cates')->where('id', $id)->value('img');
    if ($cur === $val) continue;
    if (!$dry) DB::table('cates')->where('id', $id)->update(['img' => $val]);
    $changed++;
}
echo "cates.img 已修复: {$changed} 行" . ($dry ? ' (dry)' : '') . "\n";
if ($keep) {
    echo '下载失败仍保留外链: ' . count($keep) . " 行 -> " . implode(',', array_keys($keep)) . "\n";
    foreach ($keep as $id => $u) echo "  $id\t$u\n";
}

$local = DB::table('cates')->where('img', 'like', '/cdn/%')->count();
$empty = DB::table('cates')->where('img', '')->count();
$ext   = DB::table('cates')->where('img', 'like', '%mifile%')->count();
echo "local=$local empty=$empty ext=$ext total=" . DB::table('cates')->count() . "\n";
