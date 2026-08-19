<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('personnel_code')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('gender');
            $table->date('birth_date')->nullable();
            $table->string('id_number', 10)->unique()->nullable();
            $table->string('marital_status')->nullable();
            $table->string('email', 255)->nullable()->unique();
            $table->string('mobile', 15)->nullable()->unique();
            $table->string('employment_type')->nullable();
            $table->date('hire_date')->nullable();
            $table->string('employment_status')->nullable();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('social_insurance_number', 30)->nullable()->index();
            $table->json('section_personal')->nullable();
            $table->json('section_contact_address')->nullable();
            $table->json('section_education')->nullable();
            $table->json('section_work_experience')->nullable();
            $table->json('section_skills')->nullable();
            $table->json('section_training')->nullable();
            $table->json('section_additional_info')->nullable();
            $table->json('section_social_insurance')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
