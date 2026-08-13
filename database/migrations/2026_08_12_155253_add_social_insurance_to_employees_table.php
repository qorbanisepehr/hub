<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('social_insurance_number', 30)
                ->nullable()
                ->index();

            $table->json('section_social_insurance')
                ->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex(['social_insurance_number']);
            $table->dropColumn([
                'social_insurance_number',
                'section_social_insurance',
            ]);
        });
    }
};
