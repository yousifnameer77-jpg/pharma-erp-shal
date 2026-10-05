<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('username', 50)->unique();
            $table->string('email', 150)->nullable()->unique();
            // Laravel convention (`password`, hashed via an Eloquent cast) is kept instead
            // of the doc's `password_hash`, so the framework's built-in auth guard works
            // out of the box.
            $table->string('password');
            $table->string('full_name', 150);
            $table->string('phone', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
