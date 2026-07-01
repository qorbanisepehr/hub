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
            $table->string('education_level')->nullable();
            $table->string('education_field')->nullable();
            $table->string('employment_type')->nullable();
            $table->date('hire_date')->nullable();
            $table->string('employment_status')->nullable();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
