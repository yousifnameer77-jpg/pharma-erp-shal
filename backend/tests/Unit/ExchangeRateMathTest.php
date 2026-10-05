<?php

namespace Tests\Unit;

use App\Services\ExchangeRateService;
use PHPUnit\Framework\TestCase;

class ExchangeRateMathTest extends TestCase
{
    public function test_to_base_converts_and_rounds_to_three_decimals(): void
    {
        $svc = new ExchangeRateService();

        $this->assertSame(150000.0, $svc->toBase(100, 1500));
        $this->assertSame(1495.5, $svc->toBase(1, 1495.5));
        $this->assertSame(333.333, $svc->toBase(1, 333.3333));
        $this->assertSame(0.0, $svc->toBase(0, 1500));
    }

    public function test_base_currency_rate_is_one_without_touching_the_database(): void
    {
        $this->assertSame(1.0, (new ExchangeRateService())->rateFor('any-company', 'IQD', '2026-01-01'));
    }
}
