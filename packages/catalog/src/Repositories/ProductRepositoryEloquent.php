<?php

declare(strict_types=1);

namespace Raid\Catalog\Repositories;

use Raid\Catalog\Repositories\Contracts\ProductRepository;
use Raid\Pillar\Repositories\BaseRepository;

class ProductRepositoryEloquent extends BaseRepository implements ProductRepository
{
    /**
     * @param  array<string>  $brandIds
     * @return array<string>
     */
    public function getBrandIdsWithProducts(array $brandIds): array
    {
        return $this->model->whereIn('brand_id', $brandIds)
            ->distinct()
            ->pluck('brand_id')
            ->toArray();
    }

    /**
     * @param  array<string>  $categoryIds
     * @return array<string>
     */
    public function getCategoryIdsWithProducts(array $categoryIds): array
    {
        return $this->model->whereIn('category_id', $categoryIds)
            ->distinct()
            ->pluck('category_id')
            ->toArray();
    }

    /**
     * @param  array<string>  $regionIds
     * @return array<string>
     */
    public function getRegionIdsWithProducts(array $regionIds): array
    {
        return $this->model->whereIn('region_id', $regionIds)
            ->distinct()
            ->pluck('region_id')
            ->toArray();
    }
}
