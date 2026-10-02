<?php

declare(strict_types=1);

namespace Raid\Catalog\Actions\Category;

use Illuminate\Validation\ValidationException;
use Raid\Catalog\Repositories\Contracts\CategoryRepository;
use Raid\Catalog\Repositories\Contracts\ProductRepository;
use Raid\Pillar\Actions\BaseAction;
use Throwable;

class DeleteCategoryAction extends BaseAction
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
        private readonly ProductRepository $productRepository,
    ) {}

    /**
     * @param  array<string>  $ids
     *
     * @throws Throwable
     */
    public function handle(array $ids): int
    {
        $this->validateCategoriesHaveNoProducts($ids);
        $this->validateCategoriesHaveNoSubCategories($ids);

        return $this->categoryRepository->deleteManyOrFail($ids);
    }

    /**
     * @param  array<string>  $ids
     *
     * @throws ValidationException
     */
    private function validateCategoriesHaveNoProducts(array $ids): void
    {
        $categoriesWithProducts = $this->productRepository->getCategoryIdsWithProducts($ids);

        if ($categoriesWithProducts !== []) {
            throw ValidationException::withMessages([
                'ids' => 'Cannot delete categories with existing products. Category IDs with products: '.implode(', ', $categoriesWithProducts),
            ]);
        }
    }

    /**
     * @param  array<string>  $ids
     *
     * @throws ValidationException
     */
    private function validateCategoriesHaveNoSubCategories(array $ids): void
    {
        $categoriesWithSubCategories = $this->categoryRepository->getParentIdsWithSubCategories($ids);

        if ($categoriesWithSubCategories !== []) {
            throw ValidationException::withMessages([
                'ids' => 'Cannot delete categories with existing subcategories. Category IDs with subcategories: '.implode(', ', $categoriesWithSubCategories),
            ]);
        }
    }
}
