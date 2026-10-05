<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignUuid('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->string('request_number', 30);
            $table->string('status', 20)->default('draft');
            $table->foreignUuid('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'request_number']);
            $table->index('status');
        });

        DB::statement("ALTER TABLE purchase_requests ADD CONSTRAINT purchase_requests_status_check CHECK (status IN ('draft', 'submitted', 'approved', 'rejected', 'converted', 'cancelled'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_requests');
    }
};
