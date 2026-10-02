<?php

declare(strict_types=1);

namespace Raid\Catalog\Actions\Region;

use Illuminate\Validation\ValidationException;
use Raid\Catalog\Repositories\Contracts\ProductRepository;
use Raid\Catalog\Repositories\Contracts\RegionRepository;
use Raid\Pillar\Actions\BaseAction;
use Throwable;

class DeleteRegionAction extends BaseAction
{
    public function __construct(
        private readonly RegionRepository $regionRepository,
        private readonly ProductRepository $productRepository,
    ) {}

    /**
     * @param  array<string>  $ids
     *
     * @throws Throwable
     */
    public function handle(array $ids): int
    {
        $this->validateRegionsHaveNoProducts($ids);

        return $this->regionRepository->deleteManyOrFail($ids);
    }

    /**
     * @param  array<string>  $ids
     *
     * @throws ValidationException
     */
    private function validateRegionsHaveNoProducts(array $ids): void
    {
        $regionsWithProducts = $this->productRepository->getRegionIdsWithProducts($ids);

        if ($regionsWithProducts !== []) {
            throw ValidationException::withMessages([
                'ids' => 'Cannot delete regions with existing products. Region IDs with products: '.implode(', ', $regionsWithProducts),
            ]);
        }
    }
}
