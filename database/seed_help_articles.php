<?php
/*
 * 帮助中心文章补全脚本 (非 artisan seeder, 与 xm.sql 手工导入体系配套)
 *
 * 1) 图片本地化: 把 help_articles.content 里的 mifile.cn 外链图下载到
 *    public/image/help/a{文章id}_{序号}.jpg, 并把 <img> 换成该本地路径;
 *    同时去掉写死的 height/width 内联样式(交给 .service-right img{max-width} 自适应)。
 * 2) 修正坏掉的 target="https://support.kefu.mi.com/_blank" -> "_blank"。
 * 3) 用 database/help_articles_content.php 覆盖正文过短(<200字)的文章正文。
 *
 * 幂等: 本地图已存在则跳过下载; 正文按 id 覆盖。
 * 用法: php database/seed_help_articles.php
 */

$db = new PDO('mysql:host=127.0.0.1;dbname=xm;charset=utf8mb4', 'root', '123456', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$db->exec("SET time_zone = '+8:00'");

$imgDir = dirname(__DIR__) . '/public/image/help';
if (!is_dir($imgDir) && !mkdir($imgDir, 0755, true)) {
    fwrite(STDERR, "无法创建图片目录: {$imgDir}\n");
    exit(1);
}

/* ------------------------------------------------------------------ */
/* 下载工具                                                            */
/* ------------------------------------------------------------------ */
function fetch_url(string $url, string $saveTo): bool
{
    $url = str_starts_with($url, '//') ? 'https:' . $url : $url;

    if (function_exists('curl_init')) {
        $fp = fopen($saveTo, 'wb');
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $ok = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);
        if ($ok && $code === 200 && filesize($saveTo) > 1024) {
            return true;
        }
        @unlink($saveTo);
        return false;
    }

    $ctx = stream_context_create([
        'http' => ['timeout' => 30, 'user_agent' => 'Mozilla/5.0'],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    $data = @file_get_contents($url, false, $ctx);
    if ($data === false || strlen($data) < 1024) {
        return false;
    }
    return file_put_contents($saveTo, $data) !== false;
}

/* ------------------------------------------------------------------ */
/* 1+2. 图片本地化 + 修 target                                          */
/* ------------------------------------------------------------------ */
$stats = ['img_ok' => 0, 'img_skip' => 0, 'img_fail' => 0, 'img_fix' => 0];

$rows = $db->query('SELECT id, content FROM help_articles ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $row) {
    $id = (int) $row['id'];
    $content = $row['content'];
    $original = $content;

    // 修坏掉的 target
    $content = str_replace('target="https://support.kefu.mi.com/_blank"', 'target="_blank"', $content);

    // 逐张处理图片
    $index = 0;
    $content = preg_replace_callback(
        '/<img\b[^>]*>/i',
        function ($m) use ($id, &$index, $imgDir, &$stats) {
            $tag = $m[0];
            if (!preg_match('/src="([^"]+)"/i', $tag, $sm)) {
                return $tag;
            }
            $src = $sm[1];
            $index++;

            if (str_starts_with($src, '/image/')) {
                $stats['img_skip']++;
                return $tag;
            }

            $local = '/image/help/a' . $id . '_' . $index . '.jpg';
            $file = $imgDir . '/a' . $id . '_' . $index . '.jpg';

            if (is_file($file) && filesize($file) > 1024) {
                $stats['img_skip']++;
            } elseif (fetch_url($src, $file)) {
                $stats['img_ok']++;
            } else {
                $stats['img_fail']++;
                fwrite(STDERR, "  下载失败 #{$id}: {$src}\n");
                return $tag; // 保留下载失败的标签, 下次再试
            }
            $stats['img_fix']++;

            // 重建 img 标签: 去掉写死的宽高, 交给 CSS 自适应
            $alt = preg_match('/alt="([^"]*)"/i', $tag, $am) ? $am[1] : '';
            return '<img src="' . $local . '"' . ($alt !== '' ? ' alt="' . $alt . '"' : '') . ' />';
        },
        $content
    );

    if ($content !== $original) {
        $stmt = $db->prepare('UPDATE help_articles SET content = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([$content, date('Y-m-d H:i:s'), $id]);
    }
}

/* ------------------------------------------------------------------ */
/* 3. 覆盖过短文章的正文                                                */
/* ------------------------------------------------------------------ */
$map = require __DIR__ . '/help_articles_content.php';
$updated = 0;
$stmt = $db->prepare('UPDATE help_articles SET content = ?, updated_at = ? WHERE id = ?');
foreach ($map as $id => $html) {
    $stmt->execute([trim($html), date('Y-m-d H:i:s'), (int) $id]);
    $updated += $stmt->rowCount() ? 1 : 0;
}

printf(
    "图片: 新下载 %d, 跳过 %d, 失败 %d; 改写正文 %d 篇\n",
    $stats['img_ok'],
    $stats['img_skip'],
    $stats['img_fail'],
    $updated
);

// 复查: 是否还有外链图 / 空正文
$left = $db->query("SELECT id FROM help_articles WHERE content LIKE '%mifile.cn%'")->fetchAll(PDO::FETCH_COLUMN);
echo '残留外链图文章: ' . ($left ? implode(',', $left) : '无') . "\n";
