<?php

namespace App\Domains\Ai\Services;

use App\Contracts\Ai\FieldedAgent;
use App\Domains\Ai\Exceptions\AiAgentNotRegistered;
use App\Domains\Ai\Exceptions\AiAgentStopped;
use App\Domains\Ai\Exceptions\AiNotConfigured;
use App\Domains\Ai\Value\AiAgent;
use App\Domains\Ai\Value\AiRegistryKey;
use App\Domains\Ai\Value\AiRun;
use App\Domains\Settings\Repositories\SystemSettingsRepositoryInterface;
use Closure;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Responses\Data\FinishReason;
use Laravel\Ai\Responses\Data\TextUsage;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Laravel\Ai\Streaming\Events\StreamEnd;
use Laravel\Ai\Streaming\Events\TextDelta;
use RuntimeException;

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
 *
 * Agents that return prose are streamed rather than prompted in one block.
 * Nothing is handed to a socket — the stream is consumed here and the whole
 * answer returned — so callers see no difference, but the run is read as it is
 * written rather than in one silent minute at the end, and the domain's own
 * stop condition is checked between model steps. A structured agent is still
 * prompted: the SDK does not stream schema-bound output.
 *
 * A stream that ends without its final event is an error, not a short answer.
 * The alternative is a run that was cut off writing a partial text over a good
 * record with nothing to say it never finished.
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

        $instance = $this->focusOn($instance, $settings);

        $instance = $this->stopOn($instance, $settings);

        return $this->ask($instance, $prompt, $resolved->name(), $resolvedModel, $key, $agent, $settings);
    }

    /**
     * Ask the agent, by the one transport its output shape allows.
     *
     * A structured agent cannot be streamed: the SDK refuses it outright, because
     * a schema is answered field by field and a provider streams a finished
     * document. So the split is by what was asked for, not by preference —
     * structured runs prompt, everything else streams. The stop middleware runs
     * inside both paths, since both are step loops.
     *
     * @param  array<string, mixed>  $settings
     */
    private function ask(Agent $instance, string $prompt, string $provider, ?string $model, string $key, AiAgent $agent, array $settings): AiRun
    {
        if ($instance instanceof HasStructuredOutput) {
            $response = $instance->prompt(
                $prompt,
                provider: $provider,
                model: $model,
                timeout: $this->timeout($agent, $settings),
            );

            return new AiRun(
                text: $response->text,
                structured: $response instanceof StructuredAgentResponse ? $response->toArray() : null,
                provider: $response->meta->provider ?? $provider,
                model: $response->meta->model ?? $model,
                usage: $this->usage($response->usage),
                providerKey: $key,
                reasoning: $response->reasoning,
            );
        }

        $stream = $instance->stream(
            $prompt,
            provider: $provider,
            model: $model,
            timeout: $this->timeout($agent, $settings),
        );

        [$text, $reasoning] = $this->consume($stream, $key);

        return new AiRun(
            text: $text,
            structured: null,
            provider: $stream->meta?->provider ?? $provider,
            model: $stream->meta?->model ?? $model,
            usage: $this->usage($stream->usage),
            providerKey: $key,
            reasoning: $reasoning,
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
     * Hand the agent the subject this run was asked to look at.
     *
     * A third optional capability beside the other two, for the same reason:
     * most agents are told everything they need by the prompt itself, and only
     * one whose *rule* changes per run needs a channel to say so. The runner
     * passes the stored setting through untouched; what a `strategy` key means
     * is the owning domain's business, and an agent that does not speak it is
     * left alone.
     *
     * @param  array<string, mixed>  $settings
     */
    private function focusOn(Agent $instance, array $settings): Agent
    {
        if (! isset($settings['strategy']) || ! method_exists($instance, 'withFocus')) {
            return $instance;
        }

        return $instance->withFocus(
            $settings['strategy'],
            is_string($settings['term'] ?? null) ? $settings['term'] : null,
        );
    }

    /**
     * Read the whole stream once, keeping only what a run is described by.
     *
     * One pass, because the SDK remembers its events on re-iteration — which
     * would cost a second model call per replay — and because there is nothing
     * to show until the answer is complete anyway.
     *
     * No end is a torn stream, and a stream cut off mid-answer must never be
     * read as a short one: the caller would take the part for the whole and
     * write it over a good record, with nothing saying the run did not finish.
     *
     * @return array{0: string, 1: string}
     */
    private function consume(StreamableAgentResponse $stream, string $key): array
    {
        $text = '';
        $end = null;

        foreach ($stream as $event) {
            if ($event instanceof TextDelta) {
                $text .= $event->delta;
            } elseif ($event instanceof StreamEnd) {
                $end = $event;
            }
        }

        if ($end === null) {
            throw new RuntimeException("The stream for agent [{$key}] ended before it finished.");
        }

        if ($end->reason === FinishReason::Cancelled->value) {
            throw AiAgentStopped::forAgent($key);
        }

        return [$text, $stream->reasoning];
    }

    /**
     * Hand an agent the question "should this run still happen?", asked before
     * every model step.
     *
     * The agent declares the SDK's `Stop` middleware and answers it through
     * {@see StoppableAgent::stopWhen()}; the runner only passes the stored
     * answer in. It is the same boundary as {@see focusOn()}: the Ai domain has
     * no notion of a cancelled summary or an abandoned tag run, only of a
     * caller that gave it a condition.
     *
     * @param  array<string, mixed>  $settings
     */
    private function stopOn(Agent $instance, array $settings): Agent
    {
        $stop = $settings['stop'] ?? null;

        if (! $stop instanceof Closure || ! method_exists($instance, 'stopping')) {
            return $instance;
        }

        return $instance->stopping($stop);
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
    private function usage(TextUsage $usage): ?array
    {
        return [
            'input_tokens' => $usage->inputTokens,
            'output_tokens' => $usage->outputTokens,
            'total_tokens' => $usage->totalTokens(),
        ];
    }
}
