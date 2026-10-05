<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignUuid('batch_id')->constrained('batches')->restrictOnDelete();
            $table->decimal('quantity_on_hand', 14, 3)->default(0);
            $table->decimal('reserved_quantity', 14, 3)->default(0);
            $table->timestamp('updated_at')->useCurrent();

            // One row per (warehouse, batch): every stock change is an update to
            // this row's quantity, never a new row — StockMovementService::debit()/
            // credit() rely on this for row-level locking during concurrent sales.
            $table->unique(['warehouse_id', 'batch_id']);
        });

        DB::statement('ALTER TABLE stock ADD CONSTRAINT stock_quantity_on_hand_check CHECK (quantity_on_hand >= 0)');
        DB::statement('ALTER TABLE stock ADD CONSTRAINT stock_reserved_quantity_check CHECK (reserved_quantity >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('stock');
    }
};
