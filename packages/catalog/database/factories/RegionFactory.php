<?php

declare(strict_types=1);

namespace Raid\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Raid\Catalog\Models\Region;

/**
 * @extends Factory<Region>
 */
class RegionFactory extends Factory
{
    protected $model = Region::class;

    public function definition(): array
    {
        return [
            'name' => ['en' => $this->faker->country(), 'ar' => $this->faker->country()],
            'is_active' => true,
            'sort_order' => $this->faker->numberBetween(0, 100),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
