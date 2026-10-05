<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('branches')->restrictOnDelete();
            $table->string('name', 150);
            $table->string('code', 20);
            $table->string('type', 20)->default('main');
            $table->text('location')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['branch_id', 'code']);
        });

        DB::statement("ALTER TABLE warehouses ADD CONSTRAINT warehouses_type_check CHECK (type IN ('main', 'sub', 'quarantine', 'returns'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
