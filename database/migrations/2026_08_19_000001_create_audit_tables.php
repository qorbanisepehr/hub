<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->string('event');
            $table->string('category');
            $table->string('actor_type')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->foreignId('actor_role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->string('actor_role_name')->nullable();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('subject_snapshot')->nullable();
            $table->text('description')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('url')->nullable();
            $table->string('method', 10)->nullable();
            $table->string('request_id')->nullable();
            $table->string('trace_id')->nullable();
            $table->timestamp('created_at');

            $table->index('event');
            $table->index('category');
            $table->index(['actor_type', 'actor_id']);
            $table->index('actor_role_id');
            $table->index(['subject_type', 'subject_id']);
            $table->index('request_id');
        });

        Schema::create('audit_retention_policies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('event')->nullable();
            $table->integer('retention_days');
            $table->integer('archive_after_days')->nullable();
            $table->boolean('archive_enabled')->default(false);
            $table->boolean('delete_after_archive')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['category', 'event']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_retention_policies');
        Schema::dropIfExists('audit_logs');
    }
};
