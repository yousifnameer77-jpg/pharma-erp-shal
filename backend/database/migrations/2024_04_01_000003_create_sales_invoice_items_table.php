<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_invoice_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sales_invoice_id')->constrained('sales_invoices')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            // Null = FEFO auto-allocate across every non-expired batch at posting
            // (may draw from more than one — see the stock_movements this line
            // produces, linked back via reference_type/reference_id). Set = sell
            // this exact batch, still blocked if it's expired — both exactly as
            // StockMovementService::recordSale() already behaves for Inventory.
            $table->foreignUuid('batch_id')->nullable()->constrained('batches')->restrictOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 14, 3);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('discount_rate', 5, 2)->default(0);
            $table->decimal('line_total', 14, 3);
            // Running total of what's been returned against this line — see
            // SalesReturnService and OverReturnException.
            $table->decimal('returned_quantity', 12, 3)->default(0);

            $table->index('product_id');
        });

        DB::statement('ALTER TABLE sales_invoice_items ADD CONSTRAINT sales_invoice_items_quantity_check CHECK (quantity > 0)');
        DB::statement('ALTER TABLE sales_invoice_items ADD CONSTRAINT sales_invoice_items_unit_price_check CHECK (unit_price >= 0)');
        DB::statement('ALTER TABLE sales_invoice_items ADD CONSTRAINT sales_invoice_items_returned_quantity_check CHECK (returned_quantity >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_invoice_items');
    }
};
