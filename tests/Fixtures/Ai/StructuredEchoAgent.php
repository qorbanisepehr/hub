<?php

namespace Tests\Fixtures\Ai;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * A structured fixture, so the runner's `structured` branch is exercised
 * against a real schema rather than a hand-built array. See {@see EchoAgent}
 * for why fixtures live here.
 */
class StructuredEchoAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return 'Answer with a summary.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'executive_summary' => $schema->string()->required(),
            'topics' => $schema->array()->required(),
        ];
    }
}
