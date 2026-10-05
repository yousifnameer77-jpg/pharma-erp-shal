<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('role_id')->constrained('roles')->cascadeOnDelete();
            // NULL branch_id/warehouse_id = the role applies company/branch-wide.
            // Postgres treats NULLs as distinct, so this composite unique constraint
            // is a best-effort safeguard only; true "one scoped assignment per user"
            // dedup happens in UserService::assignRole() via firstOrCreate().
            $table->foreignUuid('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            $table->foreignUuid('warehouse_id')->nullable()->constrained('warehouses')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'role_id', 'branch_id', 'warehouse_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_roles');
    }
};
