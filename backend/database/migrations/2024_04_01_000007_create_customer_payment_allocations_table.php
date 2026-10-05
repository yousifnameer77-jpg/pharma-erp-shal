<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_payment_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_payment_id')->constrained('customer_payments')->cascadeOnDelete();
            $table->foreignUuid('sales_invoice_id')->constrained('sales_invoices')->restrictOnDelete();
            $table->decimal('amount', 14, 3);

            $table->unique(['customer_payment_id', 'sales_invoice_id']);
            $table->index('sales_invoice_id');
        });

        DB::statement('ALTER TABLE customer_payment_allocations ADD CONSTRAINT customer_payment_allocations_amount_check CHECK (amount > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_payment_allocations');
    }
};
