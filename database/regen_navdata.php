<?php
/*
 * 重新生成 public/data/indexNav.js (首页导航商品数据)。
 * 逻辑与 Admin/IndexController::getAjaxcreate() 一致, 便于不进后台也能刷:
 * navigations.sku_id_group → 对应 skus (status=1) → var navData = [...]
 *
 * 用法: php database/regen_navdata.php
 */

$db = new PDO('mysql:host=127.0.0.1;dbname=xm;charset=utf8mb4', 'root', '123456', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$data = [];
$navs = $db->query("SELECT nav_name, sku_id_group FROM navigations WHERE status = '1'")->fetchAll(PDO::FETCH_ASSOC);
$stmtSku = $db->prepare('SELECT id, title, price, img, good_id FROM skus WHERE status = 1 AND id = :id');
foreach ($navs as $nav) {
    $temp = ['title' => $nav['nav_name']];
    $info = [];
    foreach (explode(',', $nav['sku_id_group']) as $skuId) {
        $stmtSku->execute(['id' => trim($skuId)]);
        $row = $stmtSku->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $info[] = $row;
        }
    }
    $temp['info'] = $info ?: null;
    $data[] = $temp;
}

$str = 'var navData = ' . json_encode($data);
if (!file_put_contents(__DIR__ . '/../public/data/indexNav.js', $str)) {
    fwrite(STDERR, "indexNav.js 写入失败\n");
    exit(1);
}
echo "indexNav.js 已重新生成 (" . count($data) . " 个导航栏目)\n";
