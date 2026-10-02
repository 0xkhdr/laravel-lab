<?php

declare(strict_types=1);

namespace Raid\Catalog\Actions\Category;

use Illuminate\Database\Eloquent\Model;
use Raid\Catalog\Repositories\Contracts\CategoryRepository;
use Raid\Pillar\Actions\BaseAction;
use Throwable;

class UpdateCategoryAction extends BaseAction
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(string $id, array $values): Model
    {
        $this->categoryRepository->updateByIdOrFail($id, $values);

        $model = $this->categoryRepository->find($id);

        return $model->load(['parent']);
    }
}
