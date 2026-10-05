<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_invoice_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('purchase_invoice_id')->constrained('purchase_invoices')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 14, 3);
            $table->decimal('tax_rate', 5, 2)->default(0);
            // quantity * unit_price * (1 + tax_rate/100), stored so a later change
            // to the product's price never reshapes a posted invoice's history.
            $table->decimal('line_total', 14, 3);

            $table->index('product_id');
        });

        DB::statement('ALTER TABLE purchase_invoice_items ADD CONSTRAINT purchase_invoice_items_quantity_check CHECK (quantity > 0)');
        DB::statement('ALTER TABLE purchase_invoice_items ADD CONSTRAINT purchase_invoice_items_unit_price_check CHECK (unit_price >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_invoice_items');
    }
};
