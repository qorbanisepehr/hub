<?php

namespace App\Domains\Ai\Services;

use App\Contracts\Ai\FieldedAgent;
use App\Domains\Ai\Exceptions\AiAgentNotRegistered;
use App\Domains\Ai\Exceptions\AiNotConfigured;
use App\Domains\Ai\Value\AiAgent;
use App\Domains\Ai\Value\AiRegistryKey;
use App\Domains\Ai\Value\AiRun;
use App\Domains\Settings\Repositories\SystemSettingsRepositoryInterface;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\TextUsage;
use Laravel\Ai\Responses\StructuredAgentResponse;

/**
 * The Ai domain's whole public capability: run the agent registered under a
 * stable key. Everything else — the credential, the model registry, the binding
 * — is detail behind this one method, which is what lets another domain depend
 * on Ai without owning any of it (D19).
 *
 * The caller passes a key, not a provider or a model: swapping models is a click
 * in Settings, not a deploy (D28). The optional `$provider` / `$model` pair is
 * the one exception, so a user can pick per request from the UI. Both halves are
 * optional — naming only a model means "this model on whichever provider I would
 * otherwise use". The pair must be validated by the caller via
 * {@see unusableChoice()} before it gets here, because a name that no longer
 * exists has to be refused to a person rather than quietly replaced.
 *
 * This class holds no knowledge of how a provider is faked. Faking is the SDK's
 * `Agent::fake()` facility, keyed by agent class, and belongs to the test that
 * wants a deterministic answer — a runner that faked on its own would quietly
 * overwrite a test's own stub.
 */
class AiAgentRunner
{
    public function __construct(
        private readonly AiProviderResolver $resolver,
        private readonly SystemSettingsRepositoryInterface $store,
    ) {}

    /**
     * Run the agent registered under `$key`.
     *
     * @param  class-string<Agent>  $agentClass  a fallback for keys with no stored
     *                                           binding yet, so a domain can ship its
     *                                           agent before an administrator creates one
     * @param  array<string, mixed>  $settings  per-run overrides: `timeout` here,
     *                                          `fields` for a {@see FieldedAgent},
     *                                          `previous` for one that reconciles against a
     *                                          stored record, anything else stays the
     *                                          agent's own business (§۶.4)
     *
     * @throws AiNotConfigured when no active provider can be resolved
     * @throws AiAgentNotRegistered when the binding names a class that is not an agent
     */
    public function run(
        string $key,
        string $prompt,
        ?string $agentClass = null,
        ?string $provider = null,
        ?string $model = null,
        array $settings = [],
    ): AiRun {
        $agent = $this->registryRow($key, $agentClass);

        ['provider' => $resolved, 'model' => $resolvedModel] = $this->resolver->resolveFor(
            $agent,
            $provider,
            $model,
        );

        if ($resolved === null) {
            throw AiNotConfigured::forAgent($key);
        }

        $instance = $this->instantiate($agent, $agentClass);

        $instance = $this->narrowToFields($instance, $settings);

        $instance = $this->reconcileAgainst($instance, $settings);

        $response = $instance->prompt(
            $prompt,
            provider: $resolved->name(),
            model: $resolvedModel,
            timeout: $this->timeout($agent, $settings),
        );

        return new AiRun(
            text: $response->text,
            structured: $response instanceof StructuredAgentResponse ? $response->toArray() : null,
            provider: $response->meta->provider ?? $resolved->name(),
            model: $response->meta->model ?? $resolvedModel,
            usage: $this->usage($response),
            providerKey: $key,
        );
    }

    /**
     * The stored binding for a key, or null when nothing is stored yet.
     *
     * Split out of {@see registryRow()} because the two callers want opposite
     * answers about a missing binding: a run needs somewhere to point an agent
     * class, a check only needs to know which agent-specific rungs apply.
     */
    private function storedAgent(string $key): ?AiAgent
    {
        return $this->store->get(AiAgent::class, AiRegistryKey::AGENTS.$key);
    }

    /**
     * Whether a request's explicit provider/model pair could be served, and
     * which half is at fault if not.
     *
     * Lives here rather than being asked of the resolver directly because this
     * is the only class in the Ai domain a consumer may depend on — so the
     * strict answer has to come from the same place as the lenient one, or one
     * narrow dependency becomes two.
     *
     * @return 'provider'|'model'|null null means the pair is usable
     */
    public function unusableChoice(string $key, ?string $provider, ?string $model): ?string
    {
        return $this->resolver->unusableChoice($this->storedAgent($key), $provider, $model);
    }

    /**
     * The stored binding for a key, or a transient one when nothing is stored
     * yet. A missing binding is not an error on day one: the hub has to boot
     * with an empty AI registry, and D24 makes that a supported state.
     */
    private function registryRow(string $key, ?string $agentClass): AiAgent
    {
        $agent = $this->storedAgent($key);

        if ($agent !== null) {
            return $agent;
        }

        if ($agentClass === null) {
            throw AiAgentNotRegistered::forKey($key);
        }

        return new AiAgent(
            key: $key,
            label: $key,
            agentClass: $agentClass,
        );
    }

    /**
     * @param  class-string<Agent>|null  $agentClass
     */
    private function instantiate(AiAgent $agent, ?string $agentClass): Agent
    {
        $class = $agent->agentClass();

        if (! is_subclass_of($class, Agent::class)) {
            throw AiAgentNotRegistered::forClass($class);
        }

        return app($class);
    }

    /**
     * Hand a requested field subset to an agent that has fields to narrow.
     *
     * The runner names the contract, never the vocabulary: `fields` is a list of
     * opaque strings here, and what they mean belongs to whichever domain wrote
     * the agent. An agent that does not speak the contract is left alone rather
     * than rejected, so the simplest agents still need only {@see Agent}.
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
        $timeout = $settings['timeout'] ?? $agent->settings()['timeout'] ?? null;

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
