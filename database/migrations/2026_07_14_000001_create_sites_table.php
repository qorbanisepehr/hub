<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('site_id')->nullable()->after('active_role_id')->constrained()->nullOnDelete();
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('site_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('site_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('site_id');
        });

        Schema::dropIfExists('sites');
    }
};
