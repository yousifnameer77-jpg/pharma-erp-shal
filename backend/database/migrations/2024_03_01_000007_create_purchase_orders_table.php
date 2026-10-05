<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignUuid('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignUuid('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignUuid('purchase_request_id')->nullable()->constrained('purchase_requests')->nullOnDelete();
            $table->string('order_number', 30);
            $table->string('status', 20)->default('draft');
            $table->date('order_date');
            $table->date('expected_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'order_number']);
            $table->index('status');
            $table->index('supplier_id');
        });

        DB::statement("ALTER TABLE purchase_orders ADD CONSTRAINT purchase_orders_status_check CHECK (status IN ('draft', 'submitted', 'approved', 'partially_received', 'received', 'cancelled', 'closed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
