<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * BoardMeeting domain storage (decisions A5/A6, ADR-003 split):
     *
     * - Letters are an INTERNAL entity (K5/C6): sync-fed, read-only — they
     *   exist to build meetings and have no standalone UI. Real columns for
     *   the fields we query on; raw source text rides in JSONB.
     * - BoardMeeting is the real aggregate (pdate is the natural key —
     *   one meeting per Jalali date, same as the source).
     */
    public function up(): void
    {
        Schema::create('bm_letters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('source_id')->unique();
            $table->string('subject')->nullable();
            $table->text('text')->nullable();
            $table->date('date')->nullable()->index();
            $table->string('pdate', 10)->nullable()->index();
            $table->string('entity_number')->nullable();
            $table->string('export_entity_number')->nullable();
            $table->string('r_title')->nullable();
            $table->string('r_name')->nullable();
            $table->string('company_name')->nullable();
            $table->date('meeting_date')->nullable();
            $table->string('meeting_pdate', 10)->nullable()->index();
            $table->boolean('has_meeting')->default(false)->index();
            $table->unsignedBigInteger('creator_role_id')->nullable();
            $table->jsonb('original')->nullable();
            $table->timestamp('source_last_edit_date')->nullable();
            $table->timestamp('synced_at')->nullable();

            $table->index(['has_meeting', 'meeting_pdate']);
        });

        Schema::create('board_meetings', function (Blueprint $table) {
            $table->id();
            $table->date('date')->nullable();
            $table->string('pdate', 10)->unique();
            $table->string('number')->nullable();
            $table->text('agenda')->nullable();
            $table->text('minutes')->nullable();
            $table->jsonb('minutes_from_letters')->nullable();
            $table->string('minutes_hash', 64)->nullable();
            $table->jsonb('minutes_from_heyat_modire')->nullable();
            $table->jsonb('documents')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        // Optional bridge for archives where the source stores explicit
        // letter↔meeting membership; projections may leave it empty when
        // membership is derived from meeting_pdate (TCK behavior).
        Schema::create('bm_board_meeting_letter', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bm_letter_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['board_meeting_id', 'bm_letter_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bm_board_meeting_letter');
        Schema::dropIfExists('board_meetings');
        Schema::dropIfExists('bm_letters');
    }
};
