<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Nullable: a category hierarchy is useful but the request that
            // drove this module didn't ask for it on the product form itself.
            $table->foreignUuid('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignUuid('manufacturer_id')->constrained('manufacturers')->restrictOnDelete();
            $table->string('code', 50)->unique();
            // Primary/base barcode for quick scanning. product_barcodes (next
            // migration) additionally supports one barcode per packaging level
            // (box/strip/piece) for products that carry more than one.
            $table->string('barcode', 50)->nullable()->unique();
            $table->string('name', 200);
            $table->string('generic_name', 200)->nullable();
            $table->string('form', 50)->nullable();
            $table->string('strength', 50)->nullable();
            $table->string('base_unit', 20)->default('piece');
            $table->unsignedInteger('pack_size')->default(1);
            $table->boolean('is_controlled_substance')->default(false);
            $table->boolean('requires_prescription')->default(false);
            $table->decimal('min_stock_level', 14, 3)->default(0);
            $table->decimal('reorder_point', 14, 3)->default(0);
            $table->decimal('purchase_price', 12, 3)->nullable();
            $table->decimal('sale_price', 12, 3)->nullable();
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::statement('ALTER TABLE products ADD CONSTRAINT products_min_stock_level_check CHECK (min_stock_level >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
