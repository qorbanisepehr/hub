<?php

namespace App\Domains\Ai\Services;

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
 * consumer is allowed to name a provider in code. The optional `?model=`
 * override is the one exception, and it exists so a user can pick a model
 * per request from the UI; it is a model *name*, never a provider, and it
 * still goes through the registry.
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
     * @param  array<string, mixed>  $settings  per-run overrides for the agent's
     *                                          own `settings` map (§۶.4)
     *
     * @throws AiNotConfigured when no active provider can be resolved
     * @throws AiAgentNotRegistered when the row names a class that is not an agent
     */
    public function run(
        string $key,
        string $prompt,
        ?string $agentClass = null,
        ?string $modelOverride = null,
        array $settings = [],
    ): AiRun {
        $agent = $this->registryRow($key, $agentClass);

        ['provider' => $provider, 'model' => $model] = $this->resolver->resolveFor($agent, $modelOverride);

        if ($provider === null) {
            throw AiNotConfigured::forAgent($key);
        }

        $instance = $this->instantiate($agent, $agentClass);

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
