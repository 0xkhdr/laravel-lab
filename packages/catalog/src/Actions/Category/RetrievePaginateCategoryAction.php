<?php

declare(strict_types=1);

namespace Raid\Catalog\Actions\Category;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Raid\Catalog\Repositories\Contracts\CategoryRepository;
use Raid\Pillar\Actions\BaseAction;

class RetrievePaginateCategoryAction extends BaseAction
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     * @return Collection<int, Model>|LengthAwarePaginator
     */
    public function handle(
        array $options = [],
        ?int $perPage = null,
        ?int $page = null,
    ): Collection|LengthAwarePaginator {
        $options += [
            'with' => ['parent'],
            'scopes' => ['accessibleByUser'],
        ];

        return $perPage === -1
            ? $this->categoryRepository->get(['*'], $options)
            : $this->categoryRepository->paginate(['*'], $options, $perPage, $page);
    }
}
