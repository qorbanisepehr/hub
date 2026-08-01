<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_verifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('type', 50);
            $table->string('mobile', 15);
            $table->string('email', 255)->nullable();
            $table->json('payload');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index('type');
            $table->index('mobile');
            $table->index('verified_at');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_verifications');
    }
};
