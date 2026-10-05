<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_return_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sales_return_id')->constrained('sales_returns')->cascadeOnDelete();
            // Which sold line this reverses — enables the over-return guard
            // (can't return more than was sold on that line). Nullable: a return
            // isn't required to trace back to a specific invoice line.
            $table->foreignUuid('sales_invoice_item_id')->nullable()->constrained('sales_invoice_items')->nullOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            // Required (unlike a sale): stock must go back into a specific,
            // identified batch, not an auto-allocated one.
            $table->foreignUuid('batch_id')->constrained('batches')->restrictOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 14, 3);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('line_total', 14, 3);

            $table->index('sales_invoice_item_id');
        });

        DB::statement('ALTER TABLE sales_return_items ADD CONSTRAINT sales_return_items_quantity_check CHECK (quantity > 0)');
        DB::statement('ALTER TABLE sales_return_items ADD CONSTRAINT sales_return_items_unit_price_check CHECK (unit_price >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_return_items');
    }
};
