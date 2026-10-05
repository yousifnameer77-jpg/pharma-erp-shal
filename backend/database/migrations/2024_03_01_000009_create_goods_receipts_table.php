<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignUuid('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignUuid('purchase_order_id')->constrained('purchase_orders')->restrictOnDelete();
            $table->string('receipt_number', 30);
            $table->date('receipt_date');
            // draft: editable, no stock/accounting effect yet.
            // posted: irreversible — creates batches/stock movements and a journal
            // entry (see GoodsReceiptService::post()); correct mistakes with a
            // compensating Inventory movement (damage/return), never by editing this.
            $table->string('status', 20)->default('draft');
            $table->foreignUuid('received_by')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'receipt_number']);
            $table->index('purchase_order_id');
            $table->index('status');
        });

        DB::statement("ALTER TABLE goods_receipts ADD CONSTRAINT goods_receipts_status_check CHECK (status IN ('draft', 'posted', 'cancelled'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipts');
    }
};
