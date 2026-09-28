<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * BoardMeeting domain storage (decision A6, ADR-003 split). The ONLY
     * BoardMeeting-owned table is the meeting aggregate itself: letters
     * and session forms live in the generic Ingest layer
     * (`integration_records`) — letters may serve other consumers later
     * and the meeting input entity may change; only the projection binds
     * them. Meeting↔record membership is derived from the Jalali meeting
     * date (TCK parity), so no pivot table.
     *
     * No `documents` column: the archive listing is DERIVED at read time
     * from the archive disk (decision 2026-09-28 — a stored copy of
     * derivable data was a TCK-ism; the hub stores what it owns, and
     * meeting files belong to the hub Document system, K4). The meeting
     * number is likewise derived from the archive filenames at sync
     * time, not stored twice.
     */
    public function up(): void
    {
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
            $table->jsonb('meta')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('board_meetings');
    }
};
