<?php

namespace Database\Factories\Ai;

use App\Domains\Ai\Models\AiAgent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiAgent>
 */
class AiAgentFactory extends Factory
{
    protected $model = AiAgent::class;

    public function definition(): array
    {
        return [
            'key' => 'agent.'.fake()->unique()->slug(),
            'label' => fake()->words(2, true),
            'agent_class' => 'App\\Domains\\Ai\\Agents\\FakeAgent',
            'provider_id' => null,
            'model_id' => null,
            'settings' => null,
        ];
    }

    public function key(string $key): static
    {
        return $this->state(fn (): array => ['key' => $key]);
    }
}
