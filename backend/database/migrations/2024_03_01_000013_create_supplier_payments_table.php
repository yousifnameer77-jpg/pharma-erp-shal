<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignUuid('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->string('payment_number', 30);
            $table->date('payment_date');
            $table->decimal('amount', 14, 3);
            $table->string('method', 20);
            // The cash/bank asset account credited — chosen explicitly per payment
            // rather than assumed, since a company may hold several.
            $table->foreignUuid('paid_from_account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            // Append-only, like stock_movements/journal_entries: a payment with no
            // accounting effect is meaningless, so it posts atomically on creation
            // and is never edited — see SupplierPayment::booted().
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['company_id', 'payment_number']);
            $table->index('supplier_id');
        });

        DB::statement("ALTER TABLE supplier_payments ADD CONSTRAINT supplier_payments_method_check CHECK (method IN ('cash', 'bank_transfer', 'cheque', 'card', 'other'))");
        DB::statement('ALTER TABLE supplier_payments ADD CONSTRAINT supplier_payments_amount_check CHECK (amount > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payments');
    }
};
