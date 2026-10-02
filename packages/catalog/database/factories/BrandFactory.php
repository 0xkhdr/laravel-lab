<?php

declare(strict_types=1);

namespace Raid\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Raid\Catalog\Models\Brand;

/**
 * @extends Factory<Brand>
 */
class BrandFactory extends Factory
{
    protected $model = Brand::class;

    public function definition(): array
    {
        return [
            'name' => ['en' => $this->faker->company(), 'ar' => $this->faker->company()],
            'is_active' => true,
            'sort_order' => $this->faker->numberBetween(0, 100),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
