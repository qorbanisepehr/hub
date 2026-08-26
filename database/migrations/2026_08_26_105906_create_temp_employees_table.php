<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('temp_employees', function (Blueprint $table) {
            $table->id();
            $table->string('personnel_code')->unique();
            $table->string('id_number', 10)->nullable();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('temp_employees');
    }
};
