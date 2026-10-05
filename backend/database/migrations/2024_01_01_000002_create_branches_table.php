<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('name', 150);
            $table->string('code', 20)->unique();
            $table->text('address')->nullable();
            $table->string('phone', 30)->nullable();
            // No FK constraint yet: users table (which this references) is created after
            // branches. The constraint itself is added in a later migration once both
            // tables exist, to avoid a circular dependency at creation time.
            $table->uuid('manager_user_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
