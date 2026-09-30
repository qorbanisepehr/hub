<?php

namespace Tests\Fixtures\Ai;

use App\Contracts\Ai\FieldedAgent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Promptable;

/**
 * A probe for the runner's per-run field channel: it records what subset it
 * was handed so a test can read it back.
 *
 * It mutates and returns `$this` rather than cloning the way a real fielded
 * agent does, precisely so that a test which puts one instance in the
 * container is looking at the same object the runner narrowed. Asserting the
 * channel needs to observe the runner's own instance; a faked structured
 * response cannot help, because it is whatever the test wrote and would read
 * the same whether the schema had been narrowed or not.
 *
 * See {@see EchoAgent} for why fixtures live here.
 */
class FieldedEchoAgent implements FieldedAgent
{
    use Promptable;

    /** @var list<string> */
    public array $askedFor = [];

    public function withFields(array $fields): static
    {
        $this->askedFor = $fields;

        return $this;
    }

    public function instructions(): string
    {
        return 'Answer with the fields you were asked for.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return ['asked_for' => $schema->array()->items($schema->string())];
    }
}
