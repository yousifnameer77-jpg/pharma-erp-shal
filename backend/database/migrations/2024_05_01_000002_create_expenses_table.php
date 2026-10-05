<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->restrictOnDelete();
            // Nullable: some expenses are company-wide overhead, not tied to one branch.
            $table->foreignUuid('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('expense_number', 30);
            $table->date('expense_date');
            // The cash/bank asset account credited — an expense here is always
            // paid immediately (petty cash / operating expense pattern); an
            // unpaid/accrued expense would need an Accounts Payable-style
            // liability and a separate settle-later flow — not implemented,
            // documented as a simplification in the README.
            $table->foreignUuid('paid_from_account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->decimal('total_amount', 14, 3)->default(0);
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('draft');
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'expense_number']);
            $table->index('status');
        });

        DB::statement("ALTER TABLE expenses ADD CONSTRAINT expenses_status_check CHECK (status IN ('draft', 'posted', 'cancelled'))");
        DB::statement('ALTER TABLE expenses ADD CONSTRAINT expenses_total_amount_check CHECK (total_amount >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
