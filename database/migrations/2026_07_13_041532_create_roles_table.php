<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('matrix_managers')->nullable();
            $table->json('requirements')->nullable();
            $table->timestamps();
        });

        Schema::create('role_inheritances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('parent_role_id')->constrained('roles')->cascadeOnDelete();
            $table->unique(['role_id', 'parent_role_id']);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('active_role_id')->nullable()->after('is_active')->constrained('roles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('active_role_id');
        });

        Schema::dropIfExists('role_inheritances');
        Schema::dropIfExists('roles');
    }
};
