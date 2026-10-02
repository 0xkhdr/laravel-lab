<?php

declare(strict_types=1);

namespace Raid\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Raid\Catalog\Models\Brand;
use Raid\Catalog\Models\Category;
use Raid\Catalog\Models\Product;
use Raid\Catalog\Models\Region;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'brand_id' => Brand::factory(),
            'category_id' => Category::factory(),
            'region_id' => null,
            'sku' => strtoupper($this->faker->unique()->bothify('SKU-####-??')),
            'name' => ['en' => $this->faker->words(3, true), 'ar' => $this->faker->words(3, true)],
            'description' => ['en' => $this->faker->sentence(), 'ar' => $this->faker->sentence()],
            'is_active' => true,
            'sort_order' => $this->faker->numberBetween(0, 100),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }

    public function withRegion(): static
    {
        return $this->state(fn (array $attributes): array => ['region_id' => Region::factory()]);
    }
}
