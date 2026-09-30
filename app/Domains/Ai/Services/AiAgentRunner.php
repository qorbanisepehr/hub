<?php

namespace App\Domains\Ai\Services;

use App\Contracts\Ai\FieldedAgent;
use App\Domains\Ai\Exceptions\AiAgentNotRegistered;
use App\Domains\Ai\Exceptions\AiNotConfigured;
use App\Domains\Ai\Models\AiAgent;
use App\Domains\Ai\Value\AiRun;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\TextUsage;
use Laravel\Ai\Responses\StructuredAgentResponse;

/**
 * The Ai domain's whole public capability: run the agent registered under a
 * stable key. Everything else in the domain — the credential, the model
 * registry, the binding — is detail behind this one method, which is what
 * lets another domain depend on Ai without owning any of it (D19).
 *
 * The caller passes a key, not a provider or a model. That is D28's whole
 * point: swapping models is a click in Settings, not a deploy, so no
 * consumer is allowed to name a provider in code. The optional `?modelId=`
 * override is the one exception, and it exists so a user can pick a model
 * per request from the UI; it is a registry row id, never a provider name,
 * and the row it names also decides the provider — a model name is unique
 * per provider, not globally, so a name would be ambiguous.
 *
 * This class holds no knowledge of how a provider is faked. Faking is the
 * SDK's `Agent::fake()` facility, keyed by agent class, and it belongs to the
 * test that wants a deterministic answer — a runner that faked on its own
 * would quietly overwrite a test's own stub, which is the opposite of what
 * the test is for. Development without a credential needs no special case
 * either: an Ollama provider row answers for real on the developer's machine.
 */
class AiAgentRunner
{
    public function __construct(
        private readonly AiProviderResolver $resolver,
    ) {}

    /**
     * Run the agent registered under `$key`.
     *
     * @param  class-string<Agent>  $agentClass  a fallback for keys that have
     *                                           no registry row yet; the registry
     *                                           row is what production uses, and this
     *                                           exists so a domain can ship its
     *                                           agent before an administrator has
     *                                           created the row.
     * @param  int|null  $modelId  a model chosen for this one run from the
     *                             UI. An id and not a name, because a model
     *                             name is only unique per provider
     *                             ({@see AiProviderResolver}); the row it
     *                             names also decides the provider.
     * @param  array<string, mixed>  $settings  per-run overrides: `timeout` here,
     *                                          `fields` for a {@see FieldedAgent},
     *                                          `previous` for one that can
     *                                          reconcile against a stored
     *                                          record, anything else stays the
     *                                          agent's own business (§۶.4)
     *
     * @throws AiNotConfigured when no active provider can be resolved
     * @throws AiAgentNotRegistered when the row names a class that is not an agent
     */
    public function run(
        string $key,
        string $prompt,
        ?string $agentClass = null,
        ?int $modelId = null,
        array $settings = [],
    ): AiRun {
        $agent = $this->registryRow($key, $agentClass);

        ['provider' => $provider, 'model' => $model] = $this->resolver->resolveFor($agent, $modelId);

        if ($provider === null) {
            throw AiNotConfigured::forAgent($key);
        }

        $instance = $this->instantiate($agent, $agentClass);

        $instance = $this->narrowToFields($instance, $settings);

        $instance = $this->reconcileAgainst($instance, $settings);

        $response = $instance->prompt(
            $prompt,
            provider: $provider->name,
            model: $model,
            timeout: $this->timeout($agent, $settings),
        );

        return new AiRun(
            text: $response->text,
            structured: $response instanceof StructuredAgentResponse ? $response->toArray() : null,
            provider: $response->meta->provider ?? $provider->name,
            model: $response->meta->model ?? $model,
            usage: $this->usage($response),
            providerKey: $key,
        );
    }

    /**
     * The registry row for a key, or a transient one when the row does not
     * exist yet. A missing row is not an error on day one: the hub has to
     * boot with an empty AI registry, and D24 makes that a supported state.
     */
    private function registryRow(string $key, ?string $agentClass): AiAgent
    {
        $agent = AiAgent::query()->where('key', $key)->first();

        if ($agent !== null) {
            return $agent;
        }

        if ($agentClass === null) {
            throw AiAgentNotRegistered::forKey($key);
        }

        return new AiAgent([
            'key' => $key,
            'label' => $key,
            'agent_class' => $agentClass,
        ]);
    }

    /**
     * @param  class-string<Agent>  $agentClass
     */
    private function instantiate(AiAgent $agent, ?string $agentClass): Agent
    {
        $class = $agent->exists ? $agent->agent_class : ($agentClass ?? $agent->agent_class);

        if (! is_subclass_of($class, Agent::class)) {
            throw AiAgentNotRegistered::forClass($class);
        }

        return app($class);
    }

    /**
     * Hand a requested field subset to an agent that has fields to narrow.
     *
     * The runner names the contract, never the vocabulary: `fields` is a list
     * of opaque strings on this side, and what they mean belongs to whichever
     * domain wrote the agent. An agent that does not speak the contract is
     * left alone rather than rejected, so the simplest agents — one prompt,
     * one answer — still need nothing but {@see Agent}.
     *
     * @param  array<string, mixed>  $settings
     */
    private function narrowToFields(Agent $instance, array $settings): Agent
    {
        if (! $instance instanceof FieldedAgent || ! isset($settings['fields'])) {
            return $instance;
        }

        return $instance->withFields((array) $settings['fields']);
    }

    /**
     * Hand an agent the stored values it should reconcile against.
     *
     * A second, optional capability beside `FieldedAgent` rather than a
     * requirement of it: most agents read a document and say what is in it, and
     * forcing them all to accept a "previous" argument would put a reconciling
     * idea into a contract that has nothing to do with it. An agent that does
     * not implement it is left alone, exactly as one that does not speak
     * `FieldedAgent` is.
     *
     * @param  array<string, mixed>  $settings
     */
    private function reconcileAgainst(Agent $instance, array $settings): Agent
    {
        $previous = $settings['previous'] ?? null;

        if (! is_array($previous) || $previous === []) {
            return $instance;
        }

        if (! method_exists($instance, 'withPrevious')) {
            return $instance;
        }

        return $instance->withPrevious($previous);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function timeout(AiAgent $agent, array $settings): ?int
    {
        $timeout = $settings['timeout'] ?? $agent->settings['timeout'] ?? null;

        return is_numeric($timeout) ? (int) $timeout : null;
    }

    /**
     * @return array{input_tokens: int, output_tokens: int, total_tokens: int}|null
     */
    private function usage(AgentResponse $response): ?array
    {
        $usage = $response->usage;

        if (! $usage instanceof TextUsage) {
            return null;
        }

        return [
            'input_tokens' => $usage->inputTokens,
            'output_tokens' => $usage->outputTokens,
            'total_tokens' => $usage->totalTokens(),
        ];
    }
}
