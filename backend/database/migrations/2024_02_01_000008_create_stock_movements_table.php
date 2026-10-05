<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 20);
            $table->foreignUuid('batch_id')->constrained('batches')->restrictOnDelete();
            $table->foreignUuid('from_warehouse_id')->nullable()->constrained('warehouses')->restrictOnDelete();
            $table->foreignUuid('to_warehouse_id')->nullable()->constrained('warehouses')->restrictOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->string('reference_type', 30)->nullable();
            $table->uuid('reference_id')->nullable();
            $table->decimal('unit_cost', 12, 3)->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('performed_by')->constrained('users')->restrictOnDelete();
            // Append-only ledger: no updated_at column at all (see StockMovement
            // model's `const UPDATED_AT = null` and its updating()/deleting() guards).
            $table->timestamp('created_at')->useCurrent();

            $table->index(['batch_id', 'created_at']);
            $table->index('type');
        });

        DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_type_check CHECK (type IN ('purchase_in', 'sale_out', 'transfer', 'return_in', 'return_out', 'damage_out', 'expired_out', 'adjustment_in', 'adjustment_out'))");
        DB::statement('ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_quantity_check CHECK (quantity > 0)');
        DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_transfer_warehouses_check CHECK (type <> 'transfer' OR (from_warehouse_id IS NOT NULL AND to_warehouse_id IS NOT NULL))");
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
