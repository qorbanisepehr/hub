<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questionnaires', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('status', 20)->default('draft');

            // ── CV source link (draft questionnaires created from a CV) ──
            $table->foreignId('cv_id')->nullable()->constrained('cvs')->nullOnDelete();

            // ── Real columns: identity ──
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('id_number', 10)->nullable()->unique();
            $table->string('gender', 10)->nullable();
            $table->date('birth_date')->nullable();

            // ── Real columns: contact ──
            $table->string('email', 255)->nullable()->unique();
            $table->string('mobile', 15)->nullable()->unique();
            $table->string('phone', 15)->nullable();
            $table->string('emergency_phone', 15)->nullable();

            // ── Real columns: marital ──
            $table->string('marital_status', 20)->nullable();

            // ── Real columns: boolean filters ──
            $table->boolean('has_chronic_disease')->nullable();
            $table->boolean('has_major_surgery')->nullable();
            $table->boolean('has_disability')->nullable();
            $table->boolean('can_travel')->nullable();
            $table->boolean('has_criminal_record')->nullable();

            // ── Real columns: employment ──
            $table->string('employment_type', 20)->nullable();
            $table->unsignedBigInteger('expected_monthly_salary')->nullable();
            $table->unsignedSmallInteger('minimum_hours_per_month')->nullable();
            $table->unsignedBigInteger('expected_hourly_salary')->nullable();
            $table->boolean('submitted_resume_before')->nullable();
            $table->boolean('interviewed_before')->nullable();
            $table->boolean('currently_employed')->nullable();
            $table->string('available_start_date', 255)->nullable();

            // ── JSONB sections ──
            $table->json('section_personal')->nullable();
            $table->json('section_contact_address')->nullable();
            $table->json('section_education')->nullable();
            $table->json('section_work_experience')->nullable();
            $table->json('section_skills')->nullable();
            $table->json('section_training')->nullable();
            $table->json('section_additional_info')->nullable();
            $table->json('section_job_request')->nullable();

            // ── OTP verification status ──
            $table->timestamp('mobile_verified_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();

            // ── Meta ──
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->softDeletes();

            // ── Indexes ──
            $table->index('status');
            $table->index('gender');
            $table->index('marital_status');
            $table->index('employment_type');
            $table->index('birth_date');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questionnaires');
    }
};
