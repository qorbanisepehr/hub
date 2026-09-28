<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The fresh BoardMeeting domain (decision 2026-09-28, design doc
     * 060): an EDITABLE aggregate — the source importer (Farzin) is one
     * writer among others (UI CRUD, future AI), never the owner of the
     * shape. Concepts, not sources, define the table. ONE table, hub
     * ADR-003 style: real columns for what is queried, JSONB for the
     * structured-but-unqueried sub-structure (agenda, attendees,
     * summary, meta) — no extra tables without profit.
     *
     * - No `pdate`: dates are Gregorian-canonical hub-wide; the Jalali
     *   rendering is client-side. The importer's grouping key is data,
     *   not a column.
     * - No `documents` JSONB: derivable data lives where it belongs
     *   (archive disk → hub Document system, K4).
     * - `number` is the meeting's registry number as plain text
     *   (user decision B — the مصوبه entity is deliberately deferred).
     * - `summary` is the AI seam: empty today, consumed by agents once
     *   the BYOK decision table closes.
     */
    public function up(): void
    {
        Schema::create('board_meetings', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('title')->nullable();
            $table->string('status')->default('held')->index();
            $table->string('number')->nullable();
            $table->text('minutes_text')->nullable();
            $table->jsonb('agenda')->nullable();
            $table->jsonb('attendees')->nullable();
            $table->jsonb('summary')->nullable();
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
