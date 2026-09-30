<?php

namespace App\Domains\Ai\Services;

use App\Domains\Ai\Exceptions\AiProviderUnreachable;
use App\Domains\Ai\Models\AiProvider;
use Laravel\Ai\AnonymousAgent;
use Throwable;

/**
 * Answers "does this credential actually work?" for the Settings screen.
 *
 * A real prompt, not a reachability ping, and the difference matters: a
 * provider can list its models over an unauthenticated endpoint and still
 * reject the key on the one request that matters. Asking the model to say one
 * word exercises the whole path — driver, URL, key, model name, structured
 * request, response — so a green result means the next real request will work
 * too. It costs a handful of tokens, which is the cheapest possible way to
 * avoid a failed paid request later.
 *
 * The prompt goes through the SDK's `AnonymousAgent` rather than the registry,
 * so testing a provider needs no `ai_agents` row: the row an administrator is
 * looking at is a credential, and requiring a registered agent to probe it
 * would make the two concepts look related when they are not.
 */
class AiConnectionTester
{
    /**
     * Enough for a model that is not resident yet.
     *
     * Ollama loads a model on first use and evicts it when memory runs short,
     * so a probe against a cold model spends most of its time waiting rather
     * than generating. A timeout tight enough to feel snappy would report a
     * slow machine as a broken credential, which is the one wrong answer this
     * button must not give. Long enough that a genuinely wrong URL still
     * fails visibly rather than hanging a request open.
     */
    public const TIMEOUT_SECONDS = 90;

    private const PROBE_INSTRUCTIONS = 'You are a connectivity probe. Reply with the single word: ok';

    public function __construct(
        private readonly AiProviderResolver $resolver,
    ) {}

    /**
     * @return array{ok: true, model: string|null, reply: string, tokens: int|null}
     *
     * @throws AiProviderUnreachable when the provider cannot answer
     */
    public function test(AiProvider $provider, ?string $modelOverride = null): array
    {
        $this->resolver->hydrateFor($provider);

        $model = $this->resolver->firstActiveModelFor($provider) ?? $modelOverride;

        $agent = new AnonymousAgent(
            instructions: self::PROBE_INSTRUCTIONS,
            messages: [],
            tools: [],
        );

        try {
            $response = $agent->prompt(
                'ok',
                provider: $provider->name,
                model: $model,
                timeout: self::TIMEOUT_SECONDS,
            );
        } catch (Throwable $e) {
            throw AiProviderUnreachable::because($provider, $e);
        }

        $usage = $response->usage;

        return [
            'ok' => true,
            'model' => $response->meta->model ?? $model,
            'reply' => $response->text,
            'tokens' => is_object($usage) && method_exists($usage, 'totalTokens')
                ? $usage->totalTokens()
                : null,
        ];
    }
}
