<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Ai domain (D19). Two tables, each owning one concept: a BYOK
     * credential, and a model that credential can reach. What a domain's
     * agent binds to is not one of them — see the note on `ai_agents` below.
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
     * "At most one provider is the default" is a *partial* unique index, and
     * an earlier version of this file explained why it deliberately was not:
     * "SQLite silently drops the `where` clause of an index", so the rule
     * would hold under test and not in production. That is false. SQLite has
     * supported partial indexes since 3.8, and 3.49 honours the `WHERE`
     * exactly — verified directly: a second `true` row is refused and any
     * number of `false` rows are accepted, on both engines.
     *
     * So the invariant lived in PHP alone for a while, on the strength of a
     * premise that does not hold, which is the worst reason to leave a
     * database rule to a model's `booted()` hook — the hook still runs, and
     * gives an administrator the behaviour they expect from "set as default",
     * but it is no longer the only thing standing between two requests and two
     * defaults.
     *
     * `ai_models.model_name` is the name the provider knows the model by,
     * not this row's id, which is why it is unique per provider: the same
     * string can mean different things behind two gateways.
     *
     * There is no `ai_agents` table. It held one row per domain capability,
     * binding a key to a provider, a model and a JSONB settings blob, and it
     * never earned its place: the runner already worked when the row was
     * absent, because the agent class ships in code. Worse, the row carried
     * both `provider_id` and `model_id` while the resolver read them
     * independently, so a row could name a provider and a model belonging to
     * a different one — the same "a model name means one thing" mistake the
     * index above exists to prevent, in the one place where it produced a
     * request to the wrong gateway. `AiAgentRunner` now takes the class from
     * code and resolves the model from the provider's `is_default` row, or
     * from the per-request `?model_id=` override.
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

        // At most one default provider, and nothing said about the rest.
        //
        // This has to be a *partial* index, and the distinction is the whole
        // point: a plain unique index on a boolean column indexes the false
        // rows too, so it caps the table at one default plus one non-default,
        // and the second provider anybody adds fails on insert with a unique
        // violation naming `is_default = f`.
        //
        // The model's `booted()` hook already demotes the previous default,
        // but that is PHP, and two concurrent requests can both pass it. The
        // schema is what turns "at most one default" from a convention into a
        // fact.
        DB::statement(
            'CREATE UNIQUE INDEX ai_providers_is_default_unique ON ai_providers (is_default) WHERE is_default'
        );

        Schema::create('ai_models', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('provider_id')->constrained('ai_providers')->cascadeOnDelete();
            $table->string('model_name');
            $table->string('label')->nullable();
            $table->jsonb('capabilities')->default('[]');
            $table->boolean('is_active')->default(true);
            // The provider's own default model: what a run falls back to when
            // no per-request `?model_id=` was given. Partial-indexed per
            // provider below, for the same reason the provider's own default
            // is — a plain unique index on a boolean would cap the catalog at
            // one default and one non-default per gateway.
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['provider_id', 'model_name']);
        });

        DB::statement(
            'CREATE UNIQUE INDEX ai_models_one_default_per_provider ON ai_models (provider_id) WHERE is_default'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_models');
        Schema::dropIfExists('ai_providers');
    }
};
