<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 商品搜索：标题 / 副标题 / 分类名模糊匹配，以及空关键词、无匹配的处理。
 * 测试环境 SCOUT_DRIVER=database，走 List_Search 的数据库回退路径。
 * 搜索结果区用 .goods-item 包裹（侧栏「最新」用 <dd> 包裹），据此区分结果是否命中。
 */
class SearchTest extends TestCase
{
    private function body($response): string
    {
        return $response->getContent();
    }

    public function test_search_by_title_returns_matching_good(): void
    {
        $this->makeGood(['title' => 'MI/小米 红米 Turbo 特供版']);
        $this->makeGood(['title' => '华为 路由器 Pro']);

        $response = $this->get('/list-search?kWord=' . urlencode('红米'));
        $response->assertOk();
        $response->assertSee('红米 Turbo 特供版');
        // 搜索结果区确实有卡片
        $this->assertStringContainsString('goods-item', $this->body($response));
    }

    public function test_search_by_sub_title_matches(): void
    {
        $this->makeGood(['title' => '子标题商品', 'sub_title' => '长续航 大电池 快充']);

        $response = $this->get('/list-search?kWord=' . urlencode('快充'));
        $response->assertOk();
        $response->assertSee('子标题商品');
    }

    public function test_search_by_category_name_matches(): void
    {
        // 创建一个分类并把商品挂到它下面，验证「按分类名搜」能命中该商品
        $cateId = $this->makeCate(['name' => '智能穿戴手环专区']);
        $this->makeGood(['title' => '仅分类可见的商品', 'cate_id' => (string) $cateId]);

        $response = $this->get('/list-search?kWord=' . urlencode('手环'));
        $response->assertOk();
        $response->assertSee('仅分类可见的商品');
    }

    public function test_search_no_match_shows_no_result_cards(): void
    {
        $this->makeGood(['title' => '某个正常商品']);

        $response = $this->get('/list-search?kWord=' . urlencode('完全不存在的关键词zzz'));
        $response->assertOk();
        // 搜索结果区不应出现任何商品卡片（侧栏「最新」不受影响）
        $this->assertStringNotContainsString('goods-item', $this->body($response));
    }

    public function test_empty_keyword_shows_default_list_without_error(): void
    {
        $this->makeGood(['title' => '默认列表商品']);

        $response = $this->get('/list-search');
        $response->assertOk();
        $response->assertSee('默认列表商品');
    }
}
