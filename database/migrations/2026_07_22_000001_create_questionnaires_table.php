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
            $table->string('status')->default('draft'); // draft, submitted, reviewed

            // Core fields (searchable/reportable)
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('mobile');

            // Section data (JSON for flexibility during draft)
            $table->json('personal_info')->nullable();
            $table->json('education')->nullable();
            $table->json('work_experience')->nullable();
            $table->json('skills')->nullable();
            $table->json('training')->nullable();
            $table->json('additional_info')->nullable();
            $table->json('job_request')->nullable();
            $table->json('review')->nullable();

            // Verification
            $table->string('mobile_otp', 6)->nullable();
            $table->timestamp('mobile_verified_at')->nullable();
            $table->string('email_otp', 6)->nullable();
            $table->timestamp('email_verified_at')->nullable();

            // Review
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questionnaires');
    }
};
