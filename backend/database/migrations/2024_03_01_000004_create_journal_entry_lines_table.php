<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entry_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
            $table->foreignUuid('account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->decimal('debit', 14, 3)->default(0);
            $table->decimal('credit', 14, 3)->default(0);
            $table->string('description', 255)->nullable();

            $table->index('account_id');
        });

        // Every line is either a debit or a credit, never both, never neither —
        // JournalEntryService additionally enforces that a whole entry's debits
        // and credits sum to the same total before it's ever written.
        DB::statement('ALTER TABLE journal_entry_lines ADD CONSTRAINT journal_entry_lines_side_check CHECK (
            (debit > 0 AND credit = 0) OR (credit > 0 AND debit = 0)
        )');
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entry_lines');
    }
};
