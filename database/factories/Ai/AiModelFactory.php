<?php

namespace Database\Factories\Ai;

use App\Domains\Ai\Enums\AiCapability;
use App\Domains\Ai\Models\AiModel;
use App\Domains\Ai\Models\AiProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiModel>
 */
class AiModelFactory extends Factory
{
    protected $model = AiModel::class;

    public function definition(): array
    {
        return [
            'provider_id' => AiProvider::factory(),
            'model_name' => 'model-'.fake()->unique()->word(),
            'label' => null,
            'capabilities' => [AiCapability::Text, AiCapability::StructuredOutput],
            'is_active' => true,
        ];
    }

    /**
     * @param  list<AiCapability>  $capabilities
     */
    public function capabilities(array $capabilities): static
    {
        return $this->state(fn (): array => ['capabilities' => $capabilities]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
