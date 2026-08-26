<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('temp_employees', function (Blueprint $table) {
            $table->string('files_directory')->nullable()->after('last_name');
        });
    }

    public function down(): void
    {
        Schema::table('temp_employees', function (Blueprint $table) {
            $table->dropColumn('files_directory');
        });
    }
};
