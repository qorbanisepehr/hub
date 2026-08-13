<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Move document usages onto the placement model (Phase 2 refactor):
     * category_slug/record_key/slot/custom_properties are replaced by
     * section_key/field_key/metadata. The category now lives on the document
     * record (documents.category_id), not on the usage.
     */
    public function up(): void
    {
        Schema::dropIfExists('document_usages');

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

        // A field placement always implies a section (field_key != null ⇒ section_key != null).
        // SQLite cannot add CHECK constraints via ALTER TABLE; the invariant is
        // enforced in the DocumentUsage model for every driver.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE document_usages ADD CONSTRAINT document_usages_field_requires_section CHECK (field_key IS NULL OR section_key IS NOT NULL)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('document_usages');

        Schema::create('document_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->string('category_slug');
            $table->string('record_key', 100)->nullable();
            $table->string('slot')->nullable();
            $table->json('custom_properties')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['entity_type', 'entity_id']);
            $table->index(['document_id', 'category_slug']);
        });
    }
};
