<?php

namespace Tests;

use App\Models\Batch;
use App\Models\Branch;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/**
 * Shared fixtures: a freshly seeded DB (permissions, roles, admin, chart of
 * accounts) plus helpers to build a warehouse/product/batch.
 */
trait Support
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = DatabaseSeeder::class;

    protected function admin(): User
    {
        return User::where('username', 'admin')->firstOrFail();
    }

    protected function actingAsAdmin(): User
    {
        $admin = $this->admin();
        Sanctum::actingAs($admin);

        return $admin;
    }

    protected function warehouse(string $code = 'WH1'): Warehouse
    {
        return Warehouse::firstOrCreate(
            ['branch_id' => Branch::firstOrFail()->id, 'code' => $code],
            ['name' => "Warehouse {$code}", 'type' => 'main', 'is_active' => true],
        );
    }

    protected function product(string $code = 'P001'): Product
    {
        $manufacturer = Manufacturer::firstOrCreate(['name' => 'Test Mfr'], ['is_active' => true]);

        return Product::firstOrCreate(
            ['code' => $code],
            [
                'manufacturer_id' => $manufacturer->id,
                'name' => "Product {$code}",
                'purchase_price' => 1000,
                'sale_price' => 1500,
                'is_active' => true,
            ],
        );
    }

    protected function receive(Product $product, Warehouse $warehouse, string $batchNumber, string $expiry, float $qty): Batch
    {
        return app(\App\Services\BatchService::class)->receive([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'batch_number' => $batchNumber,
            'expiry_date' => $expiry,
            'quantity' => $qty,
            'purchase_price' => 1000,
        ], $this->admin());
    }

    protected function onHand(Batch $batch, Warehouse $warehouse): float
    {
        return (float) \App\Models\Stock::where('batch_id', $batch->id)
            ->where('warehouse_id', $warehouse->id)
            ->value('quantity_on_hand');
    }
}
