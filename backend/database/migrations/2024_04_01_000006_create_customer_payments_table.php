<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignUuid('customer_id')->constrained('customers')->restrictOnDelete();
            $table->string('payment_number', 30);
            $table->date('payment_date');
            $table->decimal('amount', 14, 3);
            $table->string('method', 20);
            // The cash/bank asset account debited (money coming in).
            $table->foreignUuid('received_into_account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            // Append-only, like supplier_payments: see CustomerPaymentImmutableException.
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['company_id', 'payment_number']);
            $table->index('customer_id');
        });

        DB::statement("ALTER TABLE customer_payments ADD CONSTRAINT customer_payments_method_check CHECK (method IN ('cash', 'bank_transfer', 'cheque', 'card', 'other'))");
        DB::statement('ALTER TABLE customer_payments ADD CONSTRAINT customer_payments_amount_check CHECK (amount > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_payments');
    }
};
