<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->morphs('documentable');
            $table->foreignId('document_category_id')->constrained()->nullOnDelete();
            $table->string('status')->default('pending')->after('document_category_id');
            $table->foreignId('current_revision_id')->nullable()->after('status');
            $table->string('record_key')->nullable()->after('current_revision_id');
            $table->text('notes')->nullable();
            $table->json('meta')->nullable()->after('notes');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('record_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
