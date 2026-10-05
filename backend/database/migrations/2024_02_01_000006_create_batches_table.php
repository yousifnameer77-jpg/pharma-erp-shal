<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->string('batch_number', 50);
            $table->date('manufacture_date')->nullable();
            // Indexed: every FEFO query and every expiry report orders/filters by this.
            $table->date('expiry_date')->index();
            $table->foreignUuid('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            // Cost for this specific batch — can differ from products.purchase_price,
            // which is just the current default/reference cost.
            $table->decimal('purchase_price', 12, 3)->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'batch_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batches');
    }
};
