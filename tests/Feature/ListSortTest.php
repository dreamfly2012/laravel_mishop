<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 列表页排序：销量 / 价格 / 上架时间 三种排序均能正确生效。
 * 销量无独立字段，由「商品下所有 SKU 在订单中的销量合计」实时计算。
 */
class ListSortTest extends TestCase
{
    private function cateWithGoods(): int
    {
        $cateId = $this->makeCate(['name' => '排序测试分类']);

        $cheap = $this->makeGood(['title' => '便宜商品', 'cate_id' => (string) $cateId, 'price' => 100.00]);
        $dear  = $this->makeGood(['title' => '贵价商品', 'cate_id' => (string) $cateId, 'price' => 500.00]);
        $this->makeSku($cheap);
        $this->makeSku($dear);

        $old = $this->makeGood([
            'title' => '老商品',
            'cate_id' => (string) $cateId,
            'created_at' => '2020-01-01 00:00:00',
            'updated_at' => '2020-01-01 00:00:00',
        ]);
        $new = $this->makeGood([
            'title' => '新商品',
            'cate_id' => (string) $cateId,
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ]);
        $this->makeSku($old);
        $this->makeSku($new);

        return $cateId;
    }

    public function test_price_sort_asc_and_desc(): void
    {
        $cateId = $this->cateWithGoods();

        $this->get('/list/'.$cateId.'?sort=price&order=ASC')
            ->assertOk()
            ->assertSeeInOrder(['便宜商品', '贵价商品']);

        $this->get('/list/'.$cateId.'?sort=price&order=DESC')
            ->assertOk()
            ->assertSeeInOrder(['贵价商品', '便宜商品']);
    }

    public function test_new_sort_asc_and_desc(): void
    {
        $cateId = $this->cateWithGoods();

        $this->get('/list/'.$cateId.'?sort=new&order=DESC')
            ->assertOk()
            ->assertSeeInOrder(['新商品', '老商品']);

        $this->get('/list/'.$cateId.'?sort=new&order=ASC')
            ->assertOk()
            ->assertSeeInOrder(['老商品', '新商品']);
    }

    public function test_sales_sort_uses_order_goods_volume(): void
    {
        $cateId = $this->cateWithGoods();

        $high = $this->makeGood(['title' => '高销量商品', 'cate_id' => (string) $cateId]);
        $low  = $this->makeGood(['title' => '低销量商品', 'cate_id' => (string) $cateId]);
        $skuHigh = $this->makeSku($high);
        $skuLow  = $this->makeSku($low);

        $userId = $this->makeUser();
        $orderId = $this->makeOrder($userId);
        $this->makeOrderGoods($orderId, $skuHigh, ['num' => 10]);
        $this->makeOrderGoods($orderId, $skuLow, ['num' => 1]);

        $this->get('/list/'.$cateId.'?sort=sales_volume&order=DESC')
            ->assertOk()
            ->assertSeeInOrder(['高销量商品', '低销量商品']);
    }

    public function test_sort_toggle_flips_order_and_arrow(): void
    {
        $cateId = $this->makeCate(['name' => '排序测试分类']);
        $g = $this->makeGood(['title' => '任意商品', 'cate_id' => (string) $cateId, 'price' => 100.00]);
        $this->makeSku($g);

        // 价格 DESC：当前为降序，箭头应向下 ▼，再次点击切换到 ASC
        $desc = $this->get('/list/'.$cateId.'?sort=price&order=DESC');
        $desc->assertOk();
        $desc->assertSee('order=ASC');
        $desc->assertSee('▼');

        // 价格 ASC：箭头应向上 ▲，再次点击切回 DESC
        $asc = $this->get('/list/'.$cateId.'?sort=price&order=ASC');
        $asc->assertOk();
        $asc->assertSee('order=DESC');
        $asc->assertSee('▲');
    }
}
