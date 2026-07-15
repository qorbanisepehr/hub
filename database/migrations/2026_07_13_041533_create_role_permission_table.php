<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_permission', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('permission_group_id')->nullable()->constrained('permission_groups')->cascadeOnDelete();
            $table->unique(['role_id', 'permission_id']);
            $table->unique(['role_id', 'permission_group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permission');
    }
};
