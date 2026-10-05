<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One payment can cover several expense categories at once (a single
 * receipt for both office supplies and cleaning, say) — each line's
 * `account_id` must be a `type = expense` account in the chart of accounts,
 * validated in StoreExpenseRequest/UpdateExpenseRequest.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('expense_id')->constrained('expenses')->cascadeOnDelete();
            $table->foreignUuid('account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->decimal('amount', 14, 3);
            $table->string('description', 255)->nullable();

            $table->index('account_id');
        });

        DB::statement('ALTER TABLE expense_items ADD CONSTRAINT expense_items_amount_check CHECK (amount > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_items');
    }
};
