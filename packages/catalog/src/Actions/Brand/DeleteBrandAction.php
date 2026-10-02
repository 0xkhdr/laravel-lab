<?php

declare(strict_types=1);

namespace Raid\Catalog\Actions\Brand;

use Illuminate\Validation\ValidationException;
use Raid\Catalog\Repositories\Contracts\BrandRepository;
use Raid\Catalog\Repositories\Contracts\ProductRepository;
use Raid\Pillar\Actions\BaseAction;
use Throwable;

class DeleteBrandAction extends BaseAction
{
    public function __construct(
        private readonly BrandRepository $brandRepository,
        private readonly ProductRepository $productRepository,
    ) {}

    /**
     * @param  array<string>  $ids
     *
     * @throws Throwable
     */
    public function handle(array $ids): int
    {
        $this->validateBrandsHaveNoProducts($ids);

        return $this->brandRepository->deleteManyOrFail($ids);
    }

    /**
     * @param  array<string>  $ids
     *
     * @throws ValidationException
     */
    private function validateBrandsHaveNoProducts(array $ids): void
    {
        $brandsWithProducts = $this->productRepository->getBrandIdsWithProducts($ids);

        if ($brandsWithProducts !== []) {
            throw ValidationException::withMessages([
                'ids' => 'Cannot delete brands with existing products. Brand IDs with products: '.implode(', ', $brandsWithProducts),
            ]);
        }
    }
}
