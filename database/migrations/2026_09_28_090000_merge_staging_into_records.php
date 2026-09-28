<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Decision 2026-09-28: staging was a separate landing table whose
     * only real job was buffering raw payloads between fetch and
     * normalize. The record upserts were already idempotent, so the
     * merge is safe: `integration_records` gains the source-side
     * identity (`source_table`) and sync bookkeeping (checksum/status/
     * error/timestamps); the raw payload lives on as `original`, which
     * it always was. The previous unique key (source, entity_type,
     * external_id) is subsumed by (source, source_table, external_id).
     *
     * Backfill is a PHP loop (not driver-specific SQL) so the sqlite
     * test suite runs the same migration as production Postgres.
     */
    public function up(): void
    {
        Schema::table('integration_records', function (Blueprint $table) {
            $table->string('source_table')->after('source');
            $table->string('checksum')->nullable()->after('original');
            $table->string('status')->default('processed')->index()->after('checksum');
            $table->text('error')->nullable()->after('status');
            $table->timestamp('fetched_at')->nullable()->index()->after('error');
            $table->timestamp('processed_at')->nullable()->after('fetched_at');

            $table->unique(['source', 'source_table', 'external_id']);
            $table->dropUnique(['source', 'entity_type', 'external_id']);
        });

        // Existing rows predate the merge: identity came from the
        // normalizer's entity_type. The farzin mapping is the only one
        // in the wild; unknown pairs keep the entity type so the new
        // unique key stays total. Checksums are recomputed by the next
        // sync (self-healing), so a cheap stand-in is fine here.
        $farzinTables = [
            'letter' => 'Entity_A4letter',
            'session' => 'Entity_heyatModire',
        ];

        DB::table('integration_records')->orderBy('id')->chunkById(500, function ($records) use ($farzinTables): void {
            foreach ($records as $record) {
                DB::table('integration_records')->where('id', $record->id)->update([
                    'source_table' => $record->source === 'farzin'
                        ? ($farzinTables[$record->entity_type] ?? $record->entity_type)
                        : $record->entity_type,
                    'status' => 'processed',
                    'fetched_at' => $record->synced_at,
                    'processed_at' => $record->synced_at,
                ]);
            }
        });

        Schema::drop('integration_staging');
    }

    public function down(): void
    {
        Schema::table('integration_records', function (Blueprint $table) {
            $table->dropUnique(['source', 'source_table', 'external_id']);
            $table->unique(['source', 'entity_type', 'external_id']);
            $table->dropColumn(['source_table', 'checksum', 'status', 'error', 'fetched_at', 'processed_at']);
        });

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
};
