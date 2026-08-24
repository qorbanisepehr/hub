<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // JSONB section for the dependents repeater (ADR-003: variable,
            // section-specific data that is read/written as one group).
            $table->json('section_dependents')->nullable()
                ->after('section_social_insurance');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('section_dependents');
        });
    }
};
