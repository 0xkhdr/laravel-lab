<?php

declare(strict_types=1);

namespace Raid\Catalog\Repositories\Contracts;

use Raid\Pillar\Repositories\Contracts\Repository;

interface ProductRepository extends Repository
{
    /**
     * Return the subset of the given brand IDs that have at least one product.
     *
     * @param  array<string>  $brandIds
     * @return array<string>
     */
    public function getBrandIdsWithProducts(array $brandIds): array;

    /**
     * Return the subset of the given category IDs that have at least one product.
     *
     * @param  array<string>  $categoryIds
     * @return array<string>
     */
    public function getCategoryIdsWithProducts(array $categoryIds): array;

    /**
     * Return the subset of the given region IDs that have at least one product.
     *
     * @param  array<string>  $regionIds
     * @return array<string>
     */
    public function getRegionIdsWithProducts(array $regionIds): array;
}
