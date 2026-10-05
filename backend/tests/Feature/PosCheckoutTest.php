<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\SalesInvoice;
use App\Models\Warehouse;
use Tests\Support;
use Tests\TestCase;

class PosCheckoutTest extends TestCase
{
    use Support;

    private function payload(string $productId, float $qty, string $method = 'cash'): array
    {
        return [
            'items' => [['product_id' => $productId, 'quantity' => $qty, 'unit_price' => 1500]],
            'pricing_tier' => 'retail',
            'payment_method' => $method,
        ];
    }

    public function test_checkout_creates_invoice_and_reduces_stock(): void
    {
        $this->actingAsAdmin();
        $wh = $this->warehouse();
        $p = $this->product();
        $batch = $this->receive($p, $wh, 'B1', now()->addYear()->toDateString(), 10);

        $this->postJson('/api/v1/pos/checkout', $this->payload($p->id, 3), ['X-Warehouse-Id' => $wh->id])
            ->assertSuccessful();

        $this->assertSame(1, SalesInvoice::count());
        $this->assertSame(7.0, $this->onHand($batch, $wh));
        $this->assertSame(1, \App\Models\StockMovement::where('type', 'sale_out')->count());
        // The sale must also hit the ledger, balanced.
        $this->assertGreaterThan(0, \App\Models\JournalEntry::count());
        $this->assertEquals(
            \App\Models\JournalEntryLine::sum('debit'),
            \App\Models\JournalEntryLine::sum('credit'),
        );
    }

    public function test_checkout_fails_without_enough_stock_and_changes_nothing(): void
    {
        $this->actingAsAdmin();
        $wh = $this->warehouse();
        $p = $this->product();
        $batch = $this->receive($p, $wh, 'B1', now()->addYear()->toDateString(), 2);

        $res = $this->postJson('/api/v1/pos/checkout', $this->payload($p->id, 5), ['X-Warehouse-Id' => $wh->id]);

        $this->assertGreaterThanOrEqual(400, $res->status());
        $this->assertSame(0, SalesInvoice::count());
        $this->assertSame(2.0, $this->onHand($batch, $wh));
    }

    public function test_checkout_requires_authentication_and_valid_payload(): void
    {
        $this->postJson('/api/v1/pos/checkout', [])->assertUnauthorized();

        $this->actingAsAdmin();
        $this->postJson('/api/v1/pos/checkout', [])->assertStatus(422);
    }
}
