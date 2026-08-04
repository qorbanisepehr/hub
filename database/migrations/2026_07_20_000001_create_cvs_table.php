<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cvs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('status', 20)->default('draft');

            // ── Real columns: identity ──
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();

            // ── Real columns: contact ──
            $table->string('email', 255)->nullable();
            $table->string('mobile', 15)->nullable()->unique();

            // ── JSONB sections ──
            $table->json('section_personal')->nullable();
            $table->json('section_contact_address')->nullable();
            $table->json('section_education')->nullable();
            $table->json('section_work_experience')->nullable();
            $table->json('section_skills')->nullable();
            $table->json('section_training')->nullable();
            $table->json('section_additional_info')->nullable();

            // ── OTP verification status ──
            $table->timestamp('mobile_verified_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();

            // ── Meta ──
            // Lifecycle history (submit/review/reject events with snapshots).
            $table->json('lifecycle')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->softDeletes();

            // ── Indexes ──
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cvs');
    }
};
