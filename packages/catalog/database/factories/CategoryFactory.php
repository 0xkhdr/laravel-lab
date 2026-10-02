<?php

declare(strict_types=1);

namespace Raid\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Raid\Catalog\Models\Category;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        return [
            'name' => ['en' => $this->faker->words(2, true), 'ar' => $this->faker->words(2, true)],
            'is_active' => true,
            'sort_order' => $this->faker->numberBetween(0, 100),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
