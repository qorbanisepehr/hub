<?php

namespace Database\Factories;

use App\Domains\Site\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Site>
 */
class SiteFactory extends Factory
{
    protected $model = Site::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->city();

        return [
            'name' => $name,
            'code' => $this->faker->unique()->lexify('SITE-????'),
            'slug' => Str::slug($name).'-'.$this->faker->unique()->numberBetween(1, 9999),
            'is_active' => true,
        ];
    }
}
