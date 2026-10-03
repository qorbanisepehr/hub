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
            'is_default' => false,
        ];
    }

    /**
     * @param  list<AiCapability>  $capabilities
     */
    public function capabilities(array $capabilities): static
    {
        return $this->state(fn (): array => ['capabilities' => $capabilities]);
    }

    /**
     * The model runs fall back to for this provider.
     *
     * Named `default` for symmetry with {@see AiProviderFactory::default()};
     * `AiModelFactory` and `AiProviderFactory` are separate classes so the
     * state cannot be reached by accident on the wrong one.
     */
    public function default(): static
    {
        return $this->state(fn (): array => ['is_default' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
