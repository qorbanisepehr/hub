<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The generic CLEANED layer of the sync pipeline (decision
     * 2026-09-27): staging rows are normalized here once, entity-
     * agnostically, and any domain projection consumes from this table
     * instead of re-reading raw payloads. Only universally-needed
     * columns are real (user decision: what is "entity number" or
     * "status" in one system has a different name in another) —
     * everything source-specific lives in `data` JSONB, with a GIN
     * index for containment queries.
     */
    public function up(): void
    {
        Schema::create('integration_records', function (Blueprint $table) {
            $table->id();
            $table->string('source');
            $table->string('entity_type');
            $table->string('external_id');
            $table->string('title')->nullable();
            $table->date('date')->nullable();
            $table->string('pdate', 10)->nullable();
            $table->jsonb('data');
            $table->jsonb('original')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['source', 'entity_type', 'external_id']);
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
