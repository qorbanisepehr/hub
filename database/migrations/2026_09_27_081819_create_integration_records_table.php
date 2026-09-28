<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The generic synced layer of the integration pipeline (decisions
     * 2026-09-27 + 2026-09-28): every external row lands here RAW and is
     * normalized in the same step — one general table, one sync pass.
     * Identity is the source triple (source + source_table +
     * external_id); the checksum makes re-syncing idempotent and
     * `status` is the tiny sync state machine (processed / failed).
     * Only universally-needed columns are real (what is "entity number"
     * or "status" in one system has a different name in another) —
     * everything source-specific lives in `data` JSONB with a GIN index
     * for containment queries; `original` keeps the untouched payload
     * for diagnosis.
     */
    public function up(): void
    {
        Schema::create('integration_records', function (Blueprint $table) {
            $table->id();
            $table->string('source');
            $table->string('source_table');
            $table->string('entity_type');
            $table->string('external_id');
            $table->string('title')->nullable();
            $table->date('date')->nullable();
            $table->string('pdate', 10)->nullable();
            $table->jsonb('data');
            $table->jsonb('original')->nullable();
            $table->string('checksum');
            $table->string('status')->default('processed')->index();
            $table->text('error')->nullable();
            $table->timestamp('fetched_at')->index();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['source', 'source_table', 'external_id']);
            $table->index(['entity_type', 'pdate']);
            $table->index(['entity_type', 'date']);
        });

        // JSONB containment search is a Postgres capability; sqlite test
        // runs skip the GIN index gracefully.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                'CREATE INDEX integration_records_data_gin ON integration_records USING GIN (data jsonb_path_ops)',
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_records');
    }
};
