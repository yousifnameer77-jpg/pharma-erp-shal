<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->restrictOnDelete();
            // Stock is debited from this warehouse at posting — see SalesInvoiceService::post().
            $table->foreignUuid('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignUuid('customer_id')->constrained('customers')->restrictOnDelete();
            $table->string('invoice_number', 30);
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            // draft: editable, no stock/accounting effect. posted: stock debited
            // (FEFO unless a line names a batch), journal entry created, counts
            // toward the customer's balance. partially_paid/paid derive from
            // paid_amount vs total_amount. cancelled: excluded from the balance.
            $table->string('status', 20)->default('draft');
            $table->decimal('subtotal', 14, 3)->default(0);
            $table->decimal('discount_amount', 14, 3)->default(0);
            $table->decimal('tax_amount', 14, 3)->default(0);
            $table->decimal('total_amount', 14, 3)->default(0);
            $table->decimal('paid_amount', 14, 3)->default(0);
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'invoice_number']);
            $table->index('customer_id');
            $table->index('status');
        });

        DB::statement("ALTER TABLE sales_invoices ADD CONSTRAINT sales_invoices_status_check CHECK (status IN ('draft', 'posted', 'partially_paid', 'paid', 'cancelled'))");
        DB::statement('ALTER TABLE sales_invoices ADD CONSTRAINT sales_invoices_amounts_check CHECK (subtotal >= 0 AND discount_amount >= 0 AND tax_amount >= 0 AND total_amount >= 0 AND paid_amount >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_invoices');
    }
};
