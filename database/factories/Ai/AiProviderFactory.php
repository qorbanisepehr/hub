<?php

namespace Database\Factories\Ai;

use App\Domains\Ai\Enums\AiDriver;
use App\Domains\Ai\Models\AiProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiProvider>
 */
class AiProviderFactory extends Factory
{
    protected $model = AiProvider::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            // Ollama is the zero-credential driver, so it is the default row
            // rather than a hosted one: a provider built from this factory
            // needs no key and no `base_url` to be a usable configuration.
            'driver' => AiDriver::Ollama,
            'base_url' => null,
            'api_key' => null,
            'headers' => null,
            'is_active' => true,
            'is_default' => false,
        ];
    }

    public function driver(AiDriver $driver): static
    {
        return $this->state(fn (): array => ['driver' => $driver]);
    }

    public function withApiKey(string $apiKey = 'test-key'): static
    {
        return $this->state(fn (): array => ['api_key' => $apiKey]);
    }

    public function default(): static
    {
        return $this->state(fn (): array => ['is_default' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
