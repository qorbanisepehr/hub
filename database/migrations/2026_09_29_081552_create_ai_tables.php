<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Ai domain (D19). Three tables, each owning one concept: a BYOK
     * credential, a model that credential can reach, and the registry row
     * that binds a domain's agent to one of those.
     *
     * `driver` is the laravel/ai driver name rather than a provider brand,
     * because an OpenAI-compatible gateway and OpenAI itself are the same
     * driver with a different `base_url` — and `base_url` is what makes the
     * row portable. `api_key` is encrypted at rest by the model cast, so a
     * reader may know that a key is set without ever reading it back; that
     * is the point of splitting `ai.provider.view` from `ai.provider.manage`
     * (R4).
     *
     * `ai_agents.key` is unique, and it is the whole identity, because
     * callers name the agent, never the row (D28). The same reasoning gives
     * `ai_models` a `(provider_id, model_name)` unique index: the same model
     * name can mean different things behind two gateways, but never two
     * things behind one.
     *
     * "At most one provider is the default" is deliberately NOT a partial
     * unique index. SQLite — which the test suite runs on — silently drops
     * the `where` clause of an index, so that constraint would be enforced
     * in tests and not in Postgres: a stricter rule under test than in
     * production. The model enforces it instead, which also gives an
     * administrator the behaviour they expect from "set as default".
     *
     * `ai_models.model_name` is the name the provider knows the model by,
     * not this row's id, which is why it is unique per provider: the same
     * string can mean different things behind two gateways. It is
     * deliberately not called `model_id` — on `ai_agents` that name is the
     * foreign key to this table, and one name must not mean two things.
     *
     * Both foreign keys on `ai_agents` are nullable by design. An agent
     * with no binding inherits the active default provider, and a binding
     * whose provider is deleted falls back the same way (A2 resolves it),
     * so one disabled credential degrades one agent instead of breaking
     * every caller.
     */
    public function up(): void
    {
        Schema::create('ai_providers', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('driver');
            $table->string('base_url')->nullable();
            $table->text('api_key')->nullable();
            $table->jsonb('headers')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('ai_models', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('provider_id')->constrained('ai_providers')->cascadeOnDelete();
            $table->string('model_name');
            $table->string('label')->nullable();
            $table->jsonb('capabilities')->default('[]');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['provider_id', 'model_name']);
        });

        Schema::create('ai_agents', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->string('agent_class');
            $table->foreignId('provider_id')->nullable()->constrained('ai_providers')->nullOnDelete();
            $table->foreignId('model_id')->nullable()->constrained('ai_models')->nullOnDelete();
            $table->jsonb('settings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_agents');
        Schema::dropIfExists('ai_models');
        Schema::dropIfExists('ai_providers');
    }
};
