<?php

declare(strict_types=1);

namespace Raid\Catalog\Actions\Category;

use Illuminate\Database\Eloquent\Model;
use Raid\Catalog\Repositories\Contracts\CategoryRepository;
use Raid\Pillar\Actions\BaseAction;

class CreateCategoryAction extends BaseAction
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
    ) {}

    public function handle(array $values): Model
    {
        $model = $this->categoryRepository->create($values);

        return $model->load(['parent']);
    }
}
