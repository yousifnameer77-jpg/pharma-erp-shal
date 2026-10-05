<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 14, 3);
            $table->decimal('tax_rate', 5, 2)->default(0);
            // Running total of what's actually arrived across every posted goods
            // receipt against this line — how PurchaseOrder status advances
            // draft -> ... -> partially_received -> received without a separate
            // "remaining" table.
            $table->decimal('received_quantity', 12, 3)->default(0);
            $table->string('notes', 255)->nullable();

            $table->index('product_id');
        });

        DB::statement('ALTER TABLE purchase_order_items ADD CONSTRAINT purchase_order_items_quantity_check CHECK (quantity > 0)');
        DB::statement('ALTER TABLE purchase_order_items ADD CONSTRAINT purchase_order_items_unit_price_check CHECK (unit_price >= 0)');
        DB::statement('ALTER TABLE purchase_order_items ADD CONSTRAINT purchase_order_items_received_quantity_check CHECK (received_quantity >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
