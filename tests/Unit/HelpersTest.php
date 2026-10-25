<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class HelpersTest extends TestCase
{
    public function test_sub_title_returns_one_of_the_builtin_slogans(): void
    {
        $slogans = [
            '轻薄机身，图书视频游戏，应有尽有。',
            '快人一步，掌握先机',
            '奇迹世界 由你掌控',
            '行走天下，有我鼎天',
            '杀伐利器 绝对神机',
            '世界任我行',
            '人生本有无数可能 让可能变成现实',
            '爱你才给你所有 让拥有变得简单',
            '享你所想 智创未来',
        ];

        for ($i = 0; $i < 20; $i++) {
            $this->assertContains(sub_title(), $slogans);
        }
    }
}
