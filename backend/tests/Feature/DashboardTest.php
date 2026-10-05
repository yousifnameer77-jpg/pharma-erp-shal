<?php

namespace Tests\Feature;

use Tests\Support;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use Support;

    public function test_empty_system_shows_zeros_not_invented_numbers(): void
    {
        $this->actingAsAdmin();

        $data = $this->getJson('/api/v1/analytics/executive')->assertOk()->json('data');

        $this->assertSame(0.0, (float) $data['kpis']['total_revenue']);
        $this->assertSame(0.0, (float) $data['kpis']['gross_profit']);
        $this->assertSame(0.0, (float) $data['kpis']['receivables']);
        $this->assertSame(0.0, (float) $data['kpis']['payables']);
        $this->assertSame([], $data['top_products']);
        $this->assertSame([], $data['payment_split']);
        $this->assertCount(7, $data['sales_trend']);
        $this->assertSame(0.0, (float) array_sum(array_column($data['sales_trend'], 'sales')));
    }

    public function test_kpis_reflect_real_sales_and_ledger_profit(): void
    {
        $this->actingAsAdmin();
        $wh = $this->warehouse();
        $p = $this->product();
        $this->receive($p, $wh, 'D1', now()->addYear()->toDateString(), 10); // cost 1000 each

        $this->postJson('/api/v1/pos/checkout', [
            'items' => [['product_id' => $p->id, 'quantity' => 4, 'unit_price' => 1500]],
            'pricing_tier' => 'retail',
            'payment_method' => 'cash',
        ], ['X-Warehouse-Id' => $wh->id])->assertSuccessful();

        $data = $this->getJson('/api/v1/analytics/executive')->assertOk()->json('data');

        $this->assertSame(6000.0, (float) $data['kpis']['total_revenue']);
        $this->assertSame(2000.0, (float) $data['kpis']['gross_profit']); // 6000 - 4 x 1000
        $this->assertSame(33.3, (float) $data['kpis']['profit_margin']);
        $this->assertSame(0.0, (float) $data['kpis']['receivables']);
        $this->assertSame($p->name, $data['top_products'][0]['name']);
        $this->assertSame(6000.0, (float) $data['top_products'][0]['revenue']);
        $this->assertSame(100.0, (float) $data['payment_split'][0]['value']);
    }
}
