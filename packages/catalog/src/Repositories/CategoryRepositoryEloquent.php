<?php

declare(strict_types=1);

namespace Raid\Catalog\Repositories;

use Raid\Catalog\Repositories\Contracts\CategoryRepository;
use Raid\Pillar\Repositories\BaseRepository;

class CategoryRepositoryEloquent extends BaseRepository implements CategoryRepository
{
    /**
     * @param  array<string>  $parentIds
     * @return array<string>
     */
    public function getParentIdsWithSubCategories(array $parentIds): array
    {
        return $this->model->whereIn('parent_id', $parentIds)
            ->distinct()
            ->pluck('parent_id')
            ->toArray();
    }
}
