<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_returns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignUuid('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignUuid('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignUuid('sales_invoice_id')->nullable()->constrained('sales_invoices')->nullOnDelete();
            $table->string('return_number', 30);
            $table->date('return_date');
            $table->string('status', 20)->default('draft');
            $table->decimal('subtotal', 14, 3)->default(0);
            $table->decimal('tax_amount', 14, 3)->default(0);
            $table->decimal('total_amount', 14, 3)->default(0);
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'return_number']);
            $table->index('customer_id');
            $table->index('status');
        });

        DB::statement("ALTER TABLE sales_returns ADD CONSTRAINT sales_returns_status_check CHECK (status IN ('draft', 'posted', 'cancelled'))");
        DB::statement('ALTER TABLE sales_returns ADD CONSTRAINT sales_returns_amounts_check CHECK (subtotal >= 0 AND tax_amount >= 0 AND total_amount >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_returns');
    }
};
