<?php

declare(strict_types=1);

namespace Raid\Catalog\Actions\Brand;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Raid\Catalog\Repositories\Contracts\BrandRepository;
use Raid\Pillar\Actions\BaseAction;

class RetrievePaginateBrandAction extends BaseAction
{
    public function __construct(
        private readonly BrandRepository $brandRepository,
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
            'scopes' => ['accessibleByUser'],
        ];

        return $perPage === -1
            ? $this->brandRepository->get(['*'], $options)
            : $this->brandRepository->paginate(['*'], $options, $perPage, $page);
    }
}
