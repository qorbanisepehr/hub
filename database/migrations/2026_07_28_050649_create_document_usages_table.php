<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->string('category_slug', 100);
            $table->string('record_key', 100)->nullable();
            $table->string('slot', 100)->nullable();
            $table->json('custom_properties')->nullable();
            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
            $table->index('category_slug');
            $table->index('record_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_usages');
    }
};
