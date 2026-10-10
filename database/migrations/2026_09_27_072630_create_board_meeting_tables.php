<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The board meeting registry (ADR-011). One table, hub ADR-003 style:
     * real columns for what is queried, JSONB for the structured-but-unqueried
     * sub-structure that the value objects in `App\Domains\BoardMeeting\Value`
     * describe as a contract.
     *
     * Identity is the registry key, not the meeting: `held_on_jalali` (the
     * date the registry is filed under) plus `sequence` (which meeting of that
     * day). A meeting is not a date — the same day can hold an extraordinary
     * session — so `date UNIQUE` was the wrong constraint.
     *
     * Ownership is columnar and explicit: `origin`/`provenance`/`imported_at`
     * plus the registry columns are source-owned, `title`/`minutes`/`attendees`
     * are human-owned, and `source_locked_at` is set by the model on the first
     * human edit so a later re-import can never revert it.
     *
     * A مصوبه's registry number is deliberately not a column here (vault idea
     * 567): it lives where the scan does — on the meeting's document-usage
     * `metadata` as `registry_number`/`title` (next to `notes`/`source_path`).
     * The retired `approvals` JSONB column existed only briefly in dev and is
     * folded into this create migration: fresh schemas never see it.
     *
     * `held_on` is the Gregorian canonical date (hub-wide convention);
     * `held_on_jalali` is the registry key in `yyyy-MM-dd` and is NOT a second
     * source of truth for the same value — it is the identity under which the
     * archive files the meeting.
     */
    public function up(): void
    {
        Schema::create('board_meetings', function (Blueprint $table) {
            $table->id();
            $table->date('held_on');
            $table->char('held_on_jalali', 10);
            $table->unsignedSmallInteger('sequence')->default(1);
            $table->string('title')->nullable();
            $table->text('minutes')->nullable();
            $table->jsonb('agenda')->nullable();
            $table->jsonb('attendees')->nullable();
            $table->jsonb('summary')->nullable();
            $table->string('origin', 32);
            $table->jsonb('provenance')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamp('source_locked_at')->nullable();
            $table->timestamps();

            $table->unique(['held_on_jalali', 'sequence']);
            $table->index('held_on');
            $table->index(['origin', 'imported_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('board_meetings');
    }
};
