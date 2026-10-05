<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only, exactly like stock_movements: no updated_at column, and
 * JournalEntry::booted() (see the model) refuses updates/deletes. Every
 * posting module (Purchasing today, Sales/Finance later) calls
 * JournalEntryService::post() rather than writing here directly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('entry_number', 30);
            $table->date('entry_date');
            $table->string('reference_type', 50)->nullable();
            $table->uuid('reference_id')->nullable();
            $table->text('description')->nullable();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['company_id', 'entry_number']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
