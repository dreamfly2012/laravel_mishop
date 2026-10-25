<?php
/**
 * 把模板/样式/脚本/数据库里所有 mifile.cn 外链资源下载到 public/cdn/，
 * 并把引用改成本地路径（/cdn/<host>/<path>）。
 *
 * - 幂等：本地文件已存在且非空时跳过下载
 * - 顺带把线上 CSS（pay 页的 base.min.css / pay-confirm.min.css）里的 url() 也本地化
 * - 数据库：cates.img 修正为本地路径，顺带修掉 "111" 与结尾截断的 "!" 脏值
 *
 * 用法：php database/localize_cdn.php [--dry]
 */

$root      = dirname(__DIR__);
$publicDir = $root . '/public';
$cdnDir    = $publicDir . '/cdn';
$dry       = in_array('--dry', $argv, true);
$mapFile   = $root . '/database/cdn-map.json';

$UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36';

$RE = '~(?:(?:https?:)?//)(?:i[0-9]+|c[0-9]+|s[0-9]+)\.mifile\.cn/[^"\'\s)>\x5c]*~i';

/* ---------- 1. 收集待处理文件 ---------- */
$files = [];
$walk = function (string $dir) use (&$walk, &$files) {
    foreach (scandir($dir) as $f) {
        if ($f === '.' || $f === '..') continue;
        $p = $dir . '/' . $f;
        if (is_dir($p)) { $walk($p); continue; }
        if (preg_match('~\.(php|css|js|json|html)$~', $f)) $files[] = $p;
    }
};
foreach (['resources/views', 'public/homes', 'public/data', 'public/back'] as $rel) {
    $d = $root . '/' . $rel;
    if (is_dir($d)) $walk($d);
}
$files = array_values(array_unique($files));
echo '扫描文件: ' . count($files) . "\n";

/* ---------- 2. 解析 URL -> 本地路径 ---------- */
$map = [];          // key: host+path(no query)  =>  local path
$queue = [];        // key => 完整下载 URL（含 query，首次出现的那条）
$parse = function (string $raw) use (&$map, &$queue) {
    $url = str_starts_with($raw, '//') ? 'https:' . $raw : $raw;
    $parts = parse_url($url);
    if (empty($parts['host']) || empty($parts['path'])) return null;
    // 统计类脚本留在线上（纯埋点，不影响页面渲染），只本地化图片与样式
    if (preg_match('~\.js$~i', $parts['path'])) return null;
    $key = $parts['host'] . $parts['path'];
    $local = '/cdn/' . $key;
    if (!isset($map[$key])) {
        $map[$key] = $local;
        $queue[$key] = $url;
    }
    return $key;
};

$hits = [];
foreach ($files as $f) {
    $src = file_get_contents($f);
    if (preg_match_all($RE, $src, $m)) {
        foreach ($m[0] as $u) {
            $key = $parse($u);
            if ($key !== null) $hits[$f] = ($hits[$f] ?? 0) + 1;
        }
    }
}
echo '发现外链资源: ' . count($map) . ' 个（引用 ' . array_sum($hits) . ' 处，涉及 ' . count($hits) . ' 个文件）' . "\n";

/* ---------- 3. 下载 ---------- */
$ok = 0; $skip = 0; $fail = [];
$fetch = function (string $url, string $dest) use ($UA, $dry): bool {
    if ($dry) return true;
    $dir = dirname($dest);
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    if (is_file($dest) && filesize($dest) > 0) return true;
    $fp = @fopen($dest . '.part', 'wb');
    if (!$fp) return false;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FILE => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 40,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_USERAGENT => $UA,
        CURLOPT_REFERER => 'https://www.mi.com/',
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $good = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fp);
    if (!$good || $code >= 400) { @unlink($dest . '.part'); return false; }
    rename($dest . '.part', $dest);
    return true;
};

foreach ($queue as $key => $url) {
    $dest = $publicDir . $map[$key];
    if (is_file($dest) && filesize($dest) > 0) { $skip++; continue; }
    if ($fetch($url, $dest)) {
        $ok++;
    } else {
        // 再试一次 http（部分老 CDN 不支持 https）
        $alt = preg_replace('~^https:~', 'http:', $url);
        if ($alt !== $url && $fetch($alt, $dest)) { $ok++; }
        else $fail[$key] = $url;
    }
}
echo "下载完成: 新增 {$ok} / 已存在 {$skip} / 失败 " . count($fail) . "\n";
foreach ($fail as $k => $u) echo "  失败: $u\n";

/* ---------- 4. 线上 CSS 内部的 url() 一并本地化 ---------- */
$cssQueue = [];
foreach ($map as $key => $local) {
    if (!str_ends_with(strtolower($key), '.css')) continue;
    $file = $publicDir . $local;
    if (!is_file($file)) continue;
    $css = file_get_contents($file);
    if (!preg_match_all('~url\(\s*[\'"]?([^\'")]+)[\'"]?\s*\)~i', $css, $m)) continue;
    foreach ($m[1] as $ref) {
        if (str_starts_with($ref, 'data:')) continue;
        if (preg_match('~^(?:https?:)?//~', $ref)) {
            $key2 = $parse($ref);
            if ($key2 && !isset($map[$key2])) { $map[$key2] = '/cdn/' . $key2; $cssQueue[$key2] = (str_starts_with($ref, '//') ? 'https:' : '') . $ref; }
            elseif ($key2) { $cssQueue[$key2] = $cssQueue[$key2] ?? ((str_starts_with($ref, '//') ? 'https:' : '') . $ref); }
            continue;
        }
        if (str_starts_with($ref, '/')) {
            // 绝对路径: 按 CSS 所在主机解析
            $host = parse_url('https://' . $key, PHP_URL_HOST);
            $key2 = $host . $ref;
            $map[$key2] = '/cdn/' . $key2;
            $cssQueue[$key2] = 'https://' . $key2;
            continue;
        }
        // 相对路径: 以 CSS 的 URL 为基准
        $base = 'https://' . $key;
        $dir  = substr($base, 0, strrpos($base, '/') + 1);
        $abs  = $dir . $ref;
        // 规范化 ../
        $p = parse_url($abs);
        $segs = [];
        foreach (explode('/', $p['path']) as $s) {
            if ($s === '..') array_pop($segs);
            elseif ($s !== '.' && $s !== '') $segs[] = $s;
        }
        $key2 = $p['host'] . '/' . implode('/', $segs);
        $map[$key2] = '/cdn/' . $key2;
        $cssQueue[$key2] = 'https://' . $key2;
    }
}
if ($cssQueue) {
    echo 'CSS 内部引用: ' . count($cssQueue) . " 个\n";
    foreach ($cssQueue as $key => $url) {
        $dest = $publicDir . $map[$key];
        if (is_file($dest) && filesize($dest) > 0) continue;
        if (!$fetch($url, $dest)) {
            $alt = preg_replace('~^https:~', 'http:', $url);
            if (!($alt !== $url && $fetch($alt, $dest))) echo "  CSS 资源失败: $url\n";
        }
    }
}

/* ---------- 5. 回写文件引用 ---------- */
$rewrite = function (string $src) use ($RE, $map): string {
    return preg_replace_callback($RE, function ($m) use ($map) {
        $raw = $m[0];
        $url = str_starts_with($raw, '//') ? 'https:' . $raw : $raw;
        $p = parse_url($url);
        $key = ($p['host'] ?? '') . ($p['path'] ?? '');
        return $map[$key] ?? $raw;
    }, $src);
};

$changed = 0;
foreach ($files as $f) {
    $src = file_get_contents($f);
    $new = $rewrite($src);
    if ($new !== $src) {
        if (!$dry) file_put_contents($f, $new);
        $changed++;
    }
}
echo "回写文件: {$changed}\n";

/* ---------- 6. 数据库 ---------- */
require $root . '/vendor/autoload.php';
$app = require $root . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$rows = DB::table('cates')->select('id', 'img')->get();
$dbChanged = 0;
foreach ($rows as $r) {
    $img = trim((string) $r->img);
    $new = null;

    if ($img === '') {
        continue;
    } elseif (str_starts_with($img, '/')) {
        continue;   // 已经是本地路径（/cdn/... 或 /image/...），别再动
    } elseif (preg_match('~^(?:https?:)?//~', $img)) {
        // 结尾被截断的脏值（例如 .../scoket140list!）补全为同款缩略图
        if (str_ends_with($img, '!')) $img .= '70x70.jpg';
        $p = parse_url(str_starts_with($img, '//') ? 'https:' . $img : $img);
        $key = ($p['host'] ?? '') . ($p['path'] ?? '');
        $new = $map[$key] ?? null;
        if ($new === null) {
            // 没在模板里出现过 -> 现下载
            $dest = $publicDir . '/cdn/' . $key;
            $new = '/cdn/' . $key;
            $map[$key] = $new;
            if (!$fetch('https://' . $key, $dest)) { $new = null; }
        }
    } else {
        // 非路径脏值（如 "111"）-> 清空，模板会跳过空图
        $new = '';
    }

    if ($new !== null && $new !== (string) $r->img) {
        if (!$dry) DB::table('cates')->where('id', $r->id)->update(['img' => $new]);
        $dbChanged++;
    }
}
echo "数据库 cates.img 更新: {$dbChanged}\n";

if (!$dry) file_put_contents($mapFile, json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "映射表: database/cdn-map.json\n";
