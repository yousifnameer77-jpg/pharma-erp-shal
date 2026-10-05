<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_payment_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('supplier_payment_id')->constrained('supplier_payments')->cascadeOnDelete();
            $table->foreignUuid('purchase_invoice_id')->constrained('purchase_invoices')->restrictOnDelete();
            $table->decimal('amount', 14, 3);

            $table->unique(['supplier_payment_id', 'purchase_invoice_id']);
            $table->index('purchase_invoice_id');
        });

        DB::statement('ALTER TABLE supplier_payment_allocations ADD CONSTRAINT supplier_payment_allocations_amount_check CHECK (amount > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payment_allocations');
    }
};
