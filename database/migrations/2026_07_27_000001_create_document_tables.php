<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('parent_id')->nullable()->constrained('document_categories')->nullOnDelete();
            $table->string('type')->default('personnel')->index();
            $table->timestamps();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('category_id')->nullable()->after('uuid')->constrained('document_categories')->nullOnDelete();
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->string('disk', 50)->default('local');
            $table->string('path');
            $table->string('hash', 64)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('category_id');
        });

        Schema::create('document_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->string('section_key', 100)->nullable();
            $table->string('field_key', 100)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['entity_type', 'entity_id']);
            $table->index('document_id');
            $table->index('section_key');
            $table->index('field_key');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE document_usages ADD CONSTRAINT document_usages_field_requires_section CHECK (field_key IS NULL OR section_key IS NOT NULL)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('document_usages');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('document_categories');
    }
};
