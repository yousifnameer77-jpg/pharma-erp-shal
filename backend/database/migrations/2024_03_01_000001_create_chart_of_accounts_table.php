<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('code', 30);
            $table->string('name', 150);
            $table->string('type', 20);
            $table->foreignUuid('parent_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        DB::statement("ALTER TABLE chart_of_accounts ADD CONSTRAINT chart_of_accounts_type_check CHECK (type IN ('asset', 'liability', 'equity', 'revenue', 'expense'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('chart_of_accounts');
    }
};
