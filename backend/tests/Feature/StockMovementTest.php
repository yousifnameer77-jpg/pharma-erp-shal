<?php

namespace Tests\Feature;

use App\Exceptions\Inventory\InsufficientStockException;
use App\Models\StockMovement;
use App\Services\StockMovementService;
use Tests\Support;
use Tests\TestCase;

class StockMovementTest extends TestCase
{
    use Support;

    public function test_receiving_creates_batch_stock_and_movement(): void
    {
        $wh = $this->warehouse();
        $batch = $this->receive($this->product(), $wh, 'B1', now()->addYear()->toDateString(), 50);

        $this->assertSame(50.0, $this->onHand($batch, $wh));
        $this->assertSame(1, StockMovement::where('batch_id', $batch->id)->where('type', 'purchase_in')->count());
    }

    public function test_sale_follows_fefo_and_splits_across_batches(): void
    {
        $wh = $this->warehouse();
        $p = $this->product();
        $late = $this->receive($p, $wh, 'LATE', now()->addYears(2)->toDateString(), 10);
        $soon = $this->receive($p, $wh, 'SOON', now()->addMonths(3)->toDateString(), 4);

        app(StockMovementService::class)->recordSale(
            ['product_id' => $p->id, 'warehouse_id' => $wh->id, 'quantity' => 6],
            $this->admin(),
        );

        $this->assertSame(0.0, $this->onHand($soon, $wh));
        $this->assertSame(8.0, $this->onHand($late, $wh));
    }

    public function test_expired_batch_is_never_sold(): void
    {
        $wh = $this->warehouse();
        $p = $this->product();
        $expired = $this->receive($p, $wh, 'OLD', now()->subDay()->toDateString(), 10);

        try {
            app(StockMovementService::class)->recordSale(
                ['product_id' => $p->id, 'warehouse_id' => $wh->id, 'quantity' => 1],
                $this->admin(),
            );
            $this->fail('Selling from expired stock should have been rejected.');
        } catch (\Throwable $e) {
            $this->assertNotSame('', $e->getMessage());
        }

        $this->assertSame(10.0, $this->onHand($expired, $wh));
    }

    public function test_cannot_sell_more_than_on_hand(): void
    {
        $wh = $this->warehouse();
        $p = $this->product();
        $batch = $this->receive($p, $wh, 'B1', now()->addYear()->toDateString(), 5);

        $this->expectException(InsufficientStockException::class);

        try {
            app(StockMovementService::class)->recordSale(
                ['product_id' => $p->id, 'warehouse_id' => $wh->id, 'quantity' => 6],
                $this->admin(),
            );
        } finally {
            $this->assertSame(5.0, $this->onHand($batch, $wh));
        }
    }

    public function test_transfer_moves_quantity_between_warehouses(): void
    {
        $a = $this->warehouse('WHA');
        $b = $this->warehouse('WHB');
        $batch = $this->receive($this->product(), $a, 'B1', now()->addYear()->toDateString(), 20);

        app(StockMovementService::class)->recordTransfer([
            'batch_id' => $batch->id, 'from_warehouse_id' => $a->id, 'to_warehouse_id' => $b->id, 'quantity' => 7,
        ], $this->admin());

        $this->assertSame(13.0, $this->onHand($batch, $a));
        $this->assertSame(7.0, $this->onHand($batch, $b));
    }

    public function test_movements_are_immutable(): void
    {
        $wh = $this->warehouse();
        $batch = $this->receive($this->product(), $wh, 'B1', now()->addYear()->toDateString(), 5);
        $movement = StockMovement::where('batch_id', $batch->id)->firstOrFail();

        $this->expectException(\App\Exceptions\Inventory\StockMovementImmutableException::class);
        $movement->update(['quantity' => 999]);
    }

    public function test_zero_or_negative_sale_quantity_is_rejected(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(StockMovementService::class)->recordSale(
            ['product_id' => $this->product()->id, 'warehouse_id' => $this->warehouse()->id, 'quantity' => 0],
            $this->admin(),
        );
    }

    public function test_stock_api_requires_permission_and_lists_for_admin(): void
    {
        $this->getJson('/api/v1/stock')->assertUnauthorized();
        $this->actingAsAdmin();
        $this->getJson('/api/v1/stock')->assertOk();
    }
}
