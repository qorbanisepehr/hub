<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The general staging/landing table for the sync pipeline (decided
     * 2026-09-27): every external fetch lands here as RAW JSON first —
     * domain projections (currently BoardMeeting only) never query the
     * SQL Server source and never touch unclean text.
     *
     * Deliberately generic: `source` (e.g. "farzin") + `source_table`
     * (e.g. "Entity_A4letter") + `external_id` identify the row; the
     * checksum enables idempotent re-processing and dedup; `status` is
     * the tiny ETL state machine (raw → processed/failed). Nothing here
     * is interpreted — mappers/projections own all vocabulary.
     */
    public function up(): void
    {
        Schema::create('integration_staging', function (Blueprint $table) {
            $table->id();
            $table->string('source');
            $table->string('source_table');
            $table->string('external_id');
            $table->jsonb('payload');
            $table->string('checksum');
            $table->string('status')->default('raw')->index();
            $table->text('error')->nullable();
            $table->timestamp('fetched_at')->index();
            $table->timestamp('processed_at')->nullable();

            $table->unique(['source', 'source_table', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_staging');
    }
};
