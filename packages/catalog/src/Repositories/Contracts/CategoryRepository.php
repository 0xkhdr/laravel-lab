<?php

declare(strict_types=1);

namespace Raid\Catalog\Repositories\Contracts;

use Raid\Pillar\Repositories\Contracts\Repository;

interface CategoryRepository extends Repository
{
    /**
     * Return the subset of the given parent IDs that have at least one subcategory.
     *
     * @param  array<string>  $parentIds
     * @return array<string>
     */
    public function getParentIdsWithSubCategories(array $parentIds): array;
}
