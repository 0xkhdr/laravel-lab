<?php

declare(strict_types=1);

namespace Raid\Catalog\Actions\Product;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Raid\Catalog\Repositories\Contracts\ProductRepository;
use Raid\Pillar\Actions\BaseAction;

class RetrievePaginateProductAction extends BaseAction
{
    public function __construct(
        private readonly ProductRepository $productRepository,
    ) {}

    /**
     * @param  array<string>  $columns
     * @param  array<string, mixed>  $options
     * @return Collection<int, Model>|LengthAwarePaginator
     */
    public function handle(
        array $columns = ['*'],
        array $options = [],
        ?int $perPage = null,
        ?int $page = null,
    ): Collection|LengthAwarePaginator {
        $options += [
            'with' => ['brand', 'category', 'region'],
            'scopes' => ['accessibleByUser'],
        ];

        return $perPage === -1
            ? $this->productRepository->get($columns, $options)
            : $this->productRepository->paginate($columns, $options, $perPage, $page);
    }
}
