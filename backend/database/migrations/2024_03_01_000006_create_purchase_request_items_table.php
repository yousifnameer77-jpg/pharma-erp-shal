<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_request_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('purchase_request_id')->constrained('purchase_requests')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->string('notes', 255)->nullable();
        });

        DB::statement('ALTER TABLE purchase_request_items ADD CONSTRAINT purchase_request_items_quantity_check CHECK (quantity > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_request_items');
    }
};
