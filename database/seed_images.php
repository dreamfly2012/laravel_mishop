<?php
/*
 * 为商城演示库抓取真实小米商品图 (本地化改造, 替换死链 hqbcdn)。
 *
 * 双源策略:
 *   A) mi.com 经典型号静态页 → 页面专属真图 //c1|i1.mifile.cn/f/i/<年份>/<slug>/xxx.jpg
 *      (提取时只留路径含 slug 的图, 保证"页图配页品")
 *   B) cn.bing.com 图片搜索 → 原图直链 murl (小米官方 CDN 优先, 国内电商/数码媒体图库兜底,
 *      海外慢域丢弃); murl 下载失败时退回 Bing 缩略图 ts*.mm.bing.net (国内必达)
 *
 * 下载为流式处理 (curl_multi 完成即落盘, 不驻留内存), GD 把宽>1000px 的图
 * 等比压到 1000px 白底 JPEG; 文件名 mi-<md5前10>.<ext>。
 * 输出 database/mi-images.json: { buckets:{kw:[路径]}, phone:{cate_id:[路径]}, default:[路径] }
 * 用法: php database/seed_images.php   (外网需可达 mi.com / cn.bing.com / 大陆 CDN)
 */

$_bk     = require __DIR__ . '/buckets.php';
$buckets = $_bk['buckets'];

/* A 源: 桶关键词 => mi.com 型号页 slug */
$SLUG_MAP = [
    '电视'   => ['mitv3', 'mitv55', 'mitv48', 'buytv', 'tvzj'],
    '盒子'   => ['hezi3', 'hezi3s', 'hezimini', 'hezis', 'mibox3s'],
    '平板'   => ['mipad2', 'mipad3', 'mipad4'],
    '合约机' => ['mi6', 'mi5', 'mix2'],
    '无人机' => ['minidrone', 'miuav'],
    '平衡车' => ['scooterplus', 'mibicycle'],
    '电饭煲' => ['dianfanbao', 'dianfanbao2', 'dianfanbao2-4l'],
    '水壶'   => ['electric-kettle', 'kettle'],
    '手表'   => ['hmwatch', 'mtwatch-2', 'mj-watch'],
    '血压计' => ['ihealth', 'ihealth2'],
    '净化器' => ['air', 'airpro', 'air2s'],
    '净水器' => ['water2', 'water3', 'water1a'],
    '路由器' => ['miwifi3', 'miwifi4', 'miwifimini', 'miwifi'],
    '手环'   => ['shouhuan', 'shouhuan2', 'shouhuan3'],
    '体重秤' => ['scale', 'scale2'],
    '放大器' => ['miwifibox', 'miwifilite', 'miwifi3c'],
    '智能灯' => ['yeelight', 'nightlight', 'mjsmartlamp', 'desklamp', 'colorfullamp'],
    '摄像机' => ['yicamera', 'mj-camera-1080p', 'smartcamera-v', 'camera-360'],
    '音响'   => ['tvspeaker', 'yinxiang', 'misurround', 'dafang'],
    '低音炮' => ['tvspeaker', 'misurround', 'aispeaker'],
    '手柄'   => ['game-keyboard', 'gaming-mouse'],
    '电源'   => ['battery', 'battery2', 'dianyuan10000', 'minidianyuan', 'battery20000'],
    '插线板' => ['powerstrip', 'powerstrip6', 'mj-powerstrip', 'chaxianban2', 'powerstrip-4k4'],
    '插座'   => ['mj-socket', 'socket'],
    '耳机'   => ['headphone', 'capsuleearphone', 'bluetooth-earphone', 'sportearphone', 'earphone3-5', 'huosai', 'huosai2', 'huosai3'],
    '音箱'   => ['bluetooth-speaker', 'wifispeaker', 'littleaudio', 'pocketaudio', 'aispeaker-mini', 'dafang'],
    '收音机' => ['radio', 'radio-plus'],
    '电池'   => ['recharger', 'batterytwins'],
    '存储卡' => ['camera-head', 'xinpeijian'],
    '充电器' => ['chongdianqi4kou', 'recharger'],
    '线材'   => ['xinpeijian', 'commutator-dp'],
    '保护套' => ['xinpeijian', 'quantie'],
    '后盖'   => ['xinpeijian'],
    '贴膜'   => ['xinpeijian', 'quantie'],
    '自拍杆' => ['selfiestick-tripod', 'toupingshenqi'],
    '贴纸'   => ['xinpeijian'],
    '防尘塞' => ['xinpeijian', 'quantie'],
    '支架'   => ['selfiestick-tripod', 'commutator-dp', 'camera-head'],
    'WiFi'  => ['miniwifi', 'miwifibox'],
    '米兔'   => ['mitu', 'mitu-mini', 'mitu-set'],
    '服饰'   => ['hoodie', 'windproof-hoodies', 'smartshoes'],
    '声波'   => ['toyblock', 'toyblock2', 'mitublocks'],
    '箱包'   => ['suitcase', 'metal-suitcase'],
    '鼠标垫' => ['gaming-mouse', 'mouse-2'],
    '旅行箱' => ['suitcase', 'metal-suitcase'],
    '__phone' => [
        2  => ['mi5', 'mi5s'],
        3  => ['mimax', 'max2'],
        4  => ['mi4c', 'mi4s'],
        5  => ['note3', 'note2'],
        6  => ['hongmi3s', 'hongmi3'],
        7  => ['hongmi3', 'hongmi2a'],
        8  => ['mipad2', 'mipad3'],
        9  => ['mi6', 'mi8'],
        10 => ['mi5x', 'mi4c'],
        11 => ['mix2', 'mi5'],
        12 => ['mimobile', 'micard'],
    ],
    '默认' => ['xinpeijian', 'quantie', 'toyblock'],
];

/* B 源: 桶关键词 => bing 图片搜索词 */
$QUERY_MAP = [
    '电视'   => ['小米电视3S', '小米电视3 55英寸'],
    '盒子'   => ['小米盒子3', '小米盒子3S'],
    '平板'   => ['小米平板2', '小米平板3'],
    '合约机' => ['小米手机5', '小米MIX2'],
    '无人机' => ['小米无人机'],
    '平衡车' => ['小米九号平衡车'],
    '电饭煲' => ['米家压力IH电饭煲', '米家电饭煲'],
    '水壶'   => ['米家恒温电水壶'],
    '手表'   => ['华米AMAZFIT手表', '米兔儿童电话手表'],
    '血压计' => ['米家 iHealth 血压计'],
    '净化器' => ['小米空气净化器2', '小米空气净化器'],
    '净水器' => ['小米净水器'],
    '路由器' => ['小米路由器3', '小米路由器HD'],
    '手环'   => ['小米手环', '小米手环3'],
    '体重秤' => ['小米体重秤', '小米体脂秤'],
    '放大器' => ['小米WiFi放大器'],
    '智能灯' => ['Yeelight智能灯', '米家LED台灯'],
    '摄像机' => ['小蚁智能摄像机', '米家智能摄像机1080P'],
    '音响'   => ['小米电视音响', '小米家庭音响'],
    '低音炮' => ['小米低音炮'],
    '手柄'   => ['小米蓝牙手柄'],
    '电源'   => ['小米移动电源', '小米移动电源2'],
    '插线板' => ['小米插线板', '小米插线板USB'],
    '插座'   => ['小米智能插座'],
    '耳机'   => ['小米活塞耳机', '小米头戴式耳机'],
    '音箱'   => ['小米小钢炮音箱', '小米方盒子音箱'],
    '收音机' => ['小米网络收音机'],
    '电池'   => ['小米彩虹5号电池'],
    '存储卡' => ['小米存储卡'],
    '充电器' => ['小米充电器', '紫米USB充电器'],
    '线材'   => ['小米type-c数据线', '小米数据线'],
    '保护套' => ['小米5手机壳', '红米手机套'],
    '后盖'   => ['红米3原装后盖'],
    '贴膜'   => ['小米钢化膜', '小米手机贴膜'],
    '自拍杆' => ['小米自拍杆'],
    '贴纸'   => ['小米周边 手机贴纸'],
    '防尘塞' => ['手机防尘塞'],
    '支架'   => ['手机桌面支架'],
    'WiFi'  => ['小米随身WiFi'],
    '米兔'   => ['米兔玩偶', '米兔抱枕'],
    '服饰'   => ['小米T恤', '米粉卫衣'],
    '声波'   => ['小米变形金刚 特别版'],
    '箱包'   => ['小米双肩包', '小米背包'],
    '鼠标垫' => ['小米鼠标垫'],
    '旅行箱' => ['90分旅行箱', '小米旅行箱'],
    '__phone' => [
        2  => ['小米手机5'],
        3  => ['小米Max手机'],
        4  => ['小米手机4c'],
        5  => ['红米Note3'],
        6  => ['红米手机3S'],
        7  => ['红米手机3'],
        8  => ['小米平板2'],
        9  => ['小米手机5合约'],
        10 => ['小米定制手机'],
        11 => ['小米MIX'],
        12 => ['小米移动电话卡'],
    ],
    '默认' => ['小米周边', '米家好物'],
];

/* bing murl 域名白名单 (后缀匹配, 大陆可达; 越靠前越优先) */
$DOMAIN_RANK = [
    'mifile.cn', 'mi-img.com', 'appmifile.com', 'mi.com',
    'zol-img.com.cn', 'zol.com.cn', 'pconline.com.cn',
    '360buyimg.com', 'suning.cn', 'suningshop', 'zdmimg.com',
    'sinaimg.cn', 'zhimg.com', 'bdstatic.com', 'bcebos.com',
    'itc.cn', 'byteimg.com', 'toutiaoimg.com', 'qpic.cn',
    'hdslb.com', 'bilivideo.com', 'myqcloud.com',
    'alicdn.com', 'taobaocdn.com',
];
$TITLE_KEEP = '#小米|米家|Redmi|红米#u';

$UA    = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
$imDir = __DIR__ . '/../public/image/upload/';
if (!is_dir($imDir)) {
    mkdir($imDir, 0777, true);
}

/* ---------------- curl 基础设施 ---------------- */
$curlGet = function (string $url): string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 4,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_USERAGENT      => $GLOBALS['UA'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);
    $s = curl_exec($ch);
    curl_close($ch);
    return (string)$s;
};

/* $onDone 非 null 时流式处理 (完成即交出 body, 不驻留结果集) */
$fetchAll = function (array $urls, int $window = 8, ?callable $onDone = null): array {
    $urls = array_values(array_unique($urls));
    $mh = curl_multi_init();
    $tasks = [];
    $queue = $urls;
    $results = [];
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 4,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_USERAGENT      => $GLOBALS['UA'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ];
    $WIN = $window;
    $pump = function () use (&$tasks, &$queue, $mh, $opts, &$WIN) {
        while (count($tasks) < $WIN && $queue) {
            $url = array_shift($queue);
            $ch = curl_init($url);
            curl_setopt_array($ch, $opts);
            curl_multi_add_handle($mh, $ch);
            $tasks[(int)$ch] = $url;
        }
    };
    $pump();
    do {
        curl_multi_exec($mh, $running);
        if ($running) {
            curl_multi_select($mh, 0.2);
        }
        while (($info = curl_multi_info_read($mh)) !== false) {
            $ch = $info['handle'];
            $url = $tasks[(int)$ch];
            $body = ($info['result'] === CURLE_OK) ? (string)curl_multi_getcontent($ch) : '';
            if ($onDone) {
                $onDone($url, $body);
            } else {
                $results[$url] = $body;
            }
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
            unset($tasks[(int)$ch]);
        }
        $pump();
    } while ($running || $tasks);
    curl_multi_close($mh);
    return $results;
};

/* ---------------- A 源: mi.com 静态页 ---------------- */
$IMG_RE     = '#(https?:)?//[a-z0-9]+\.mifile\.cn/f/i/[0-9]{2,4}/[A-Za-z0-9._/-]+\.(?:jpg|jpeg|png|webp)#i';
$EXCLUDE_RE = '#site/|appdownload|stat/|2014/cn|placeholder|icon|logo|truste|weixin|qrcode|favicon|\.svg#i';

$allSlugs = [];
foreach ($SLUG_MAP as $kw => $slugs) {
    if ($kw === '__phone') {
        continue;
    }
    foreach ($slugs as $slug) {
        if (is_string($slug)) {
            $allSlugs[$slug] = true;
        }
    }
}
$pages = $fetchAll(array_map(fn ($s) => "https://www.mi.com/{$s}/", array_keys($allSlugs)));
$okPages = array_filter($pages, fn ($h) => strlen($h) >= 5000);
echo 'A 源页面抓取: ' . count($okPages) . ' / ' . count($pages) . " 页有效\n";

$ownBySlug = [];
foreach ($pages as $url => $html) {
    if (strlen($html) < 5000) {
        continue;
    }
    $slug = preg_replace('#https://www\.mi\.com/([^/]+)/#', '$1', $url);
    preg_match_all($IMG_RE, $html, $m);
    foreach ($m[0] as $u) {
        if (preg_match($EXCLUDE_RE, $u)) {
            continue;
        }
        $u = (str_starts_with($u, '//') ? 'https:' : '') . $u;
        if (stripos($u, "/{$slug}/") !== false) {   // 路径含 slug 的页面专属图
            $ownBySlug[$slug][$u] = true;
        }
    }
}
$ownTotal = 0;
foreach ($ownBySlug as $set) {
    $ownTotal += count($set);
}
echo "A 源页面专属图: $ownTotal 张\n";

/* ---------------- B 源: bing 图片搜索 ---------------- */
$bingSearch = function (string $q) use ($curlGet): array {
    $url = 'https://cn.bing.com/images/search?q=' . urlencode($q) . '&form=HDRSC2&first=1';
    foreach ([0, 1, 2] as $try) {
        if ($try > 0) {
            usleep(1500000);
        }
        $html = $curlGet($url);
        $txt = html_entity_decode($html, ENT_QUOTES | ENT_HTML5);
        $txt = str_replace('\\/', '/', $txt);   // 还原 JSON 转义斜杠
        $cands = [];
        if (preg_match_all('/\{"sid"[^{}]*?"murl":"([^"]+)","turl":"([^"]*)"[^{}]*?"t":"(.*?)"/s', $txt, $m)) {
            foreach ($m[1] as $i => $u) {
                $cands[] = ['murl' => $u, 'turl' => $m[2][$i], 't' => $m[3][$i]];
            }
        }
        if ($cands) {
            usleep(350000); // 礼貌间隔
            return $cands;
        }
    }
    return [];
};

$hostRank = function (string $url) use ($DOMAIN_RANK): int {
    $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');
    foreach ($DOMAIN_RANK as $i => $suffix) {
        if (str_ends_with($host, $suffix)) {
            return $i;
        }
    }
    return 99;
};

/* b:x / p:<cateId> 两类搜索词分组 */
$queries = [];
foreach ($QUERY_MAP as $kw => $qs) {
    if ($kw === '__phone') {
        foreach ($qs as $cid => $ll) {
            $queries["p:$cid"] = (array)$ll;
        }
        continue;
    }
    $queries["b:$kw"] = $qs;
}

$pool = [];  // qkey => [{murl,turl,t,rank,titleOk}]
foreach ($queries as $qkey => $qs) {
    $list = [];
    foreach ($qs as $q) {
        $items = $bingSearch($q);
        $acc = 0;
        foreach ($items as $it) {
            $rank = $hostRank($it['murl']);
            if ($rank === 99) {
                continue;
            }
            $acc++;
            $list[] = $it + [
                'rank'    => $rank,
                'titleOk' => (bool)preg_match($TITLE_KEEP, $it['t']),
            ];
        }
        echo "bing[$qkey] \"$q\": " . count($items) . " 条, 白名单 $acc\n";
    }
    usort($list, fn ($a, $b) => [$a['rank'], $a['titleOk'] ? 0 : 1] <=> [$b['rank'], $b['titleOk'] ? 0 : 1]);
    $pool[$qkey] = array_slice($list, 0, 10);
}

/* ---------------- 下载清单 (A 源优先, Bing 补足) ---------------- */
$need = [];  // bucket => [{url, turl}]

/* A 源按桶归属 (每桶限 5 张, 够用且控量) */
foreach ($SLUG_MAP as $kw => $slugs) {
    if ($kw === '__phone') {
        continue;
    }
    $urls = [];
    foreach ($slugs as $slug) {
        if (!is_string($slug)) {
            continue;
        }
        $urls = array_merge($urls, array_keys($ownBySlug[$slug] ?? []));
    }
    $need["b:$kw"] = array_map(
        fn ($u) => ['url' => $u, 'turl' => ''],
        array_slice($urls, 0, 5)
    );
}
foreach ($SLUG_MAP['__phone'] as $cid => $slugs) {
    $urls = [];
    foreach ($slugs as $slug) {
        $urls = array_merge($urls, array_keys($ownBySlug[$slug] ?? []));
    }
    $need["p:$cid"] = array_map(
        fn ($u) => ['url' => $u, 'turl' => ''],
        array_slice($urls, 0, 5)
    );
}

/* Bing 补充 (桶不足 8 个候选时) */
foreach ($queries as $qkey => $qs) {
    foreach ($pool[$qkey] ?? [] as $it) {
        if (count($need[$qkey] ?? []) >= 8) {
            break;
        }
        $need[$qkey][] = ['url' => $it['murl'], 'turl' => $it['turl']];
    }
}

$dlUrls = [];
foreach ($need as $items) {
    foreach ($items as $n) {
        $dlUrls[$n['url']] = true;
    }
}
echo "下载: " . count($dlUrls) . " 张 (流式并行)...\n";

/* ---------------- 图片处理落盘 ---------------- */
$sniff = function (string $d): ?string {
    return substr($d, 0, 3) === "\xFF\xD8\xFF" ? 'jpg'
        : (substr($d, 0, 8) === "\x89PNG\r\n\x1a\n" ? 'png'
        : (substr($d, 0, 4) === 'RIFF' && substr($d, 8, 4) === 'WEBP' ? 'webp' : null));
};

$shrink = function (string $data, string $ext): array {
    // webp 原样保留 (GD 处理部分动图态 webp 不稳), 其余超宽压到 1000px
    if ($ext === 'webp' || !function_exists('imagecreatefromstring') || !function_exists('imagescale')) {
        return [$data, $ext];
    }
    $src = @imagecreatefromstring($data);
    if (!$src) {
        return [$data, $ext];
    }
    if (imagesx($src) <= 1000) {
        imagedestroy($src);
        return [$data, $ext];
    }
    $w = 1000;
    $h = (int)max(1, round(imagesy($src) * 1000 / imagesx($src)));
    $dst = imagecreatetruecolor($w, $h);
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefill($dst, 0, 0, $white);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, imagesx($src), imagesy($src));
    ob_start();
    imagejpeg($dst, null, 80);
    $out = ob_get_clean();
    imagedestroy($src);
    imagedestroy($dst);
    return [$out, 'jpg'];
};

$localOf = [];
$saveImage = function (string $url, string $data) use ($sniff, $shrink, $imDir, &$localOf): void {
    $ext = $sniff($data);
    if (strlen($data) < 5000 || $ext === null) {
        $localOf[$url] = null;
        return;
    }
    [$d2, $e2] = $shrink($data, $ext);
    $name = 'mi-' . substr(md5($url), 0, 10) . '.' . $e2;
    file_put_contents($imDir . $name, $d2);
    $localOf[$url] = '/image/upload/' . $name;
};

$fetchAll(array_keys($dlUrls), 8, $saveImage);

/* murl 失败 → Bing 缩略图兜底 (不缩放, 分辨率可能较低) */
$thumbJobs = [];
foreach ($need as $items) {
    foreach ($items as $n) {
        if ($n['turl'] && array_key_exists($n['url'], $localOf) && $localOf[$n['url']] === null) {
            $turl = $n['turl'] . '&w=1000&h=1000';
            $thumbJobs[$turl] = $n['url'];
        }
    }
}
if ($thumbJobs) {
    echo "原图失败, 用 Bing 缩略图兜底: " . count($thumbJobs) . " 张\n";
    $fetchAll(array_keys($thumbJobs), 8, function (string $turl, string $data) use ($thumbJobs, $saveImage, &$localOf) {
        $murl = $thumbJobs[$turl] ?? null;
        if (!$data || $murl === null || !empty($localOf[$murl])) {
            return;
        }
        $before = count(array_filter($localOf));
        $saveImage($murl, $data);
    });
}
$okN = count(array_filter($localOf, fn ($p) => $p !== null));
echo "下载成功: $okN / " . count($localOf) . "\n";

/* ---------------- 桶组装 ---------------- */
$FALLBACK = [
    '低音炮' => '音响', '手柄' => '默认', '电池' => '充电器', '存储卡' => '默认',
    '线材' => '默认', '保护套' => '默认', '后盖' => '默认', '贴膜' => '默认',
    '贴纸' => '默认', '防尘塞' => '默认', '放大器' => '路由器', '无人机' => '默认',
    '血压计' => '默认', '收音机' => '音箱', 'WiFi' => '路由器', '声波' => '米兔',
    '鼠标垫' => '默认', '旅行箱' => '箱包',
];

$out = ['buckets' => [], 'phone' => [], 'default' => []];

$extract = function (string $bucket) use ($need, $localOf): array {
    $list = [];
    foreach ($need[$bucket] ?? [] as $n) {
        $p = $localOf[$n['url']] ?? null;
        if ($p && !in_array($p, $list, true)) {
            $list[] = $p;
        }
    }
    return array_slice($list, 0, 4);
};

$out['default'] = $extract('b:默认');
echo '默认池: ' . count($out['default']) . " 张\n";

foreach ($buckets as [$kw, $r, $a, $c]) {
    $list = $extract("b:$kw");
    if (count($list) < 2) {
        $fb = $FALLBACK[$kw] ?? '默认';
        $extra = $fb === '默认' ? $out['default'] : $extract("b:$fb");
        $list = array_values(array_unique(array_merge($list, $extra, $out['default'])));
    }
    $out['buckets'][$kw] = $list;
    printf("桶[%s]: %d 张%s\n", $kw, count($list), count($list) < 2 ? '  <-- 兜底' : '');
}
foreach ($SLUG_MAP['__phone'] as $cid => $slugs) {
    $list = $extract("p:$cid");
    if (count($list) < 2) {
        $list = array_values(array_unique(array_merge($list, $out['default'])));
    }
    $out['phone'][(string)$cid] = $list;
    printf("手机[cate %s]: %d 张%s\n", $cid, count($list), count($list) < 2 ? '  <-- 兜底' : '');
}

file_put_contents(
    __DIR__ . '/mi-images.json',
    json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
);
echo "已写入 database/mi-images.json\n";
echo "完成\n";
