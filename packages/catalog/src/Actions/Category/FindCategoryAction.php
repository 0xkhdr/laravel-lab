<?php

declare(strict_types=1);

namespace Raid\Catalog\Actions\Category;

use Illuminate\Database\Eloquent\Model;
use Raid\Catalog\Repositories\Contracts\CategoryRepository;
use Raid\Pillar\Actions\BaseAction;

class FindCategoryAction extends BaseAction
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $conditions
     * @param  array<string>  $columns
     */
    public function handle(array $conditions, array $columns = ['*']): Model
    {
        return $this->categoryRepository->getModel()
            ->query()
            ->accessibleByUser()
            ->where($conditions)
            ->firstOrFail($columns)
            ->loadMissing('parent');
    }
}
