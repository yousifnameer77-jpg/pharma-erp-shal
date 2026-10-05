<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignUuid('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignUuid('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->foreignUuid('goods_receipt_id')->nullable()->constrained('goods_receipts')->nullOnDelete();
            $table->string('invoice_number', 30);
            $table->string('supplier_invoice_number', 50)->nullable();
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            // draft: editable. posted: locked, has a journal entry (Dr GRNI/Tax,
            // Cr Accounts Payable) and counts toward the supplier's balance.
            // partially_paid/paid derive from paid_amount vs total_amount as
            // payments are allocated. cancelled: excluded from the balance.
            $table->string('status', 20)->default('draft');
            $table->decimal('subtotal', 14, 3)->default(0);
            $table->decimal('tax_amount', 14, 3)->default(0);
            $table->decimal('total_amount', 14, 3)->default(0);
            $table->decimal('paid_amount', 14, 3)->default(0);
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'invoice_number']);
            $table->index('supplier_id');
            $table->index('status');
        });

        DB::statement("ALTER TABLE purchase_invoices ADD CONSTRAINT purchase_invoices_status_check CHECK (status IN ('draft', 'posted', 'partially_paid', 'paid', 'cancelled'))");
        DB::statement('ALTER TABLE purchase_invoices ADD CONSTRAINT purchase_invoices_amounts_check CHECK (subtotal >= 0 AND tax_amount >= 0 AND total_amount >= 0 AND paid_amount >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_invoices');
    }
};
