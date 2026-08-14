<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->string('effect')->default('allow');
            $table->integer('priority')->default(0);
            $table->json('policy')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unique(['role_id', 'permission_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_rules');
    }
};
