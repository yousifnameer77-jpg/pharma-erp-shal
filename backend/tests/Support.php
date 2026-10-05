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

    protected function company(): \App\Models\Company
    {
        return \App\Models\Company::firstOrFail();
    }

    protected function supplier(): \App\Models\Supplier
    {
        return \App\Models\Supplier::firstOrCreate(
            ['company_id' => $this->company()->id, 'name' => 'Test Supplier'],
            ['is_active' => true],
        );
    }

    protected function customer(): \App\Models\Customer
    {
        return \App\Models\Customer::firstOrCreate(
            ['company_id' => $this->company()->id, 'name' => 'Test Customer'],
            ['is_active' => true],
        );
    }

    protected function account(string $code): \App\Models\ChartOfAccount
    {
        return \App\Models\ChartOfAccount::findByCode($this->company()->id, $code);
    }

    /** Net balance (debit - credit) of an account across all journal lines. */
    protected function accountBalance(string $code): float
    {
        $id = $this->account($code)->id;

        return (float) \App\Models\JournalEntryLine::where('account_id', $id)->sum('debit')
            - (float) \App\Models\JournalEntryLine::where('account_id', $id)->sum('credit');
    }

    protected function assertLedgerBalanced(): void
    {
        $this->assertEquals(
            round((float) \App\Models\JournalEntryLine::sum('debit'), 3),
            round((float) \App\Models\JournalEntryLine::sum('credit'), 3),
            'Journal debits must equal credits.',
        );
    }
}
