<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipt_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('goods_receipt_id')->constrained('goods_receipts')->cascadeOnDelete();
            $table->foreignUuid('purchase_order_item_id')->constrained('purchase_order_items')->restrictOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->string('batch_number', 50);
            $table->date('manufacture_date')->nullable();
            $table->date('expiry_date');
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_cost', 14, 3);

            $table->index('purchase_order_item_id');
        });

        DB::statement('ALTER TABLE goods_receipt_items ADD CONSTRAINT goods_receipt_items_quantity_check CHECK (quantity > 0)');
        DB::statement('ALTER TABLE goods_receipt_items ADD CONSTRAINT goods_receipt_items_unit_cost_check CHECK (unit_cost >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_items');
    }
};
