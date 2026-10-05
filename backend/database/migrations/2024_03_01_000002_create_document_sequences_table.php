<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A single reusable counter per (company, document key) — e.g. 'purchase_order',
 * 'goods_receipt', 'purchase_invoice', 'supplier_payment', 'journal_entry' — used
 * by DocumentSequenceService to hand out gapless-per-key, concurrency-safe
 * document numbers (PO-000001, GR-000001, ...) via SELECT ... FOR UPDATE.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('key', 30);
            $table->unsignedInteger('next_number')->default(1);

            $table->unique(['company_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
