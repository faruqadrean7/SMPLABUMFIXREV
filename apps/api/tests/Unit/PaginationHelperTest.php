<?php

namespace Tests\Unit;

use App\Support\PaginationHelper;
use PHPUnit\Framework\TestCase;

class PaginationHelperTest extends TestCase
{
    public function test_p1_40_clamps_out_of_bounds_and_invalid_values(): void
    {
        $this->assertSame(15, PaginationHelper::clamp(null));
        $this->assertSame(15, PaginationHelper::clamp(''));
        $this->assertSame(15, PaginationHelper::clamp('   '));
        $this->assertSame(15, PaginationHelper::clamp('abc'));
        $this->assertSame(15, PaginationHelper::clamp(['10']));
        $this->assertSame(15, PaginationHelper::clamp(true));

        $this->assertSame(1, PaginationHelper::clamp(0));
        $this->assertSame(1, PaginationHelper::clamp('0'));
        $this->assertSame(1, PaginationHelper::clamp(-5));
        $this->assertSame(1, PaginationHelper::clamp('-5'));
        $this->assertSame(1, PaginationHelper::clamp('-999999999999999999999'));

        $this->assertSame(100, PaginationHelper::clamp(999999));
        $this->assertSame(100, PaginationHelper::clamp('999999'));
        $this->assertSame(100, PaginationHelper::clamp('999999999999999999999'));

        $this->assertSame(25, PaginationHelper::clamp(25));
        $this->assertSame(25, PaginationHelper::clamp('25'));
        $this->assertSame(50, PaginationHelper::clamp(999, 10, 1, 50));
        $this->assertSame(200, PaginationHelper::clamp(999, 20, 1, 200));
    }
}
