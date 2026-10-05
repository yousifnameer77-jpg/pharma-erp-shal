<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add wholesale_price and profit margin fields to products table
        if (Schema::hasTable('products') && !Schema::hasColumn('products', 'wholesale_price')) {
            Schema::table('products', function (Blueprint $table) {
                $table->decimal('wholesale_price', 12, 3)->nullable()->after('purchase_price');
            });
        }

        // 2. Controlled drug prescription records table for ministry inspection
        if (!Schema::hasTable('prescription_records')) {
            Schema::create('prescription_records', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('company_id')->constrained('companies')->restrictOnDelete();
                $table->foreignUuid('branch_id')->constrained('branches')->restrictOnDelete();
                $table->foreignUuid('sales_invoice_id')->nullable()->constrained('sales_invoices')->nullOnDelete();
                $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
                $table->foreignUuid('batch_id')->nullable()->constrained('batches')->nullOnDelete();
                
                // Patient Details
                $table->string('patient_name', 150);
                $table->string('patient_national_id', 50)->nullable();
                $table->string('patient_phone', 30)->nullable();
                
                // Doctor & Prescription Details
                $table->string('doctor_name', 150);
                $table->string('doctor_syndicate_id', 50)->nullable(); // رقم نقابة الأطباء
                $table->string('doctor_clinic', 150)->nullable();
                $table->string('prescription_number', 50);
                $table->date('prescription_date');
                $table->text('diagnosis_notes')->nullable();
                
                // Dispensation Data
                $table->decimal('quantity_dispensed', 10, 2);
                $table->string('dosage_instructions', 255)->nullable();
                $table->foreignUuid('dispensed_by')->constrained('users')->restrictOnDelete();
                $table->timestamp('dispensed_at');
                $table->timestamps();

                $table->index('prescription_number');
                $table->index('patient_national_id');
                $table->index('dispensed_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_records');
        if (Schema::hasColumn('products', 'wholesale_price')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('wholesale_price');
            });
        }
    }
};

