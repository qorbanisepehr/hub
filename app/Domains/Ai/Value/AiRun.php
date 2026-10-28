<?php

namespace App\Domains\Ai\Value;

/**
 * What one agent run produced, in the Ai domain's own vocabulary.
 *
 * Provider and model travel with the result because "which model wrote
 * this?" has to stay answerable after the fact (§6.3): the hub stores them
 * next to the generated summary, and a run whose model is unrecorded cannot
 * be re-run or audited. `providerKey` is the agent's registry key, which
 * D28 makes the stable identity of the run.
 */
final readonly class AiRun
{
    /**
     * @param  array<string, mixed>|null  $structured
     * @param  array{input_tokens: int, output_tokens: int, total_tokens: int}|null  $usage
     */
    public function __construct(
        public string $text,
        public ?array $structured,
        public string $provider,
        public ?string $model,
        public ?array $usage,
        public string $providerKey,
    ) {}

    /**
     * @return array{provider: string, model: string|null, usage: array{input_tokens: int, output_tokens: int, total_tokens: int}|null}
     */
    public function attribution(): array
    {
        return [
            'provider' => $this->provider,
            'model' => $this->model,
            'usage' => $this->usage,
        ];
    }
}
