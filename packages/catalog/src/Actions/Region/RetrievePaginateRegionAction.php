<?php

declare(strict_types=1);

namespace Raid\Catalog\Actions\Region;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Raid\Catalog\Repositories\Contracts\RegionRepository;
use Raid\Pillar\Actions\BaseAction;

class RetrievePaginateRegionAction extends BaseAction
{
    public function __construct(
        private readonly RegionRepository $regionRepository,
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
        return $perPage === -1
            ? $this->regionRepository->get(['*'], $options)
            : $this->regionRepository->paginate(['*'], $options, $perPage, $page);
    }
}
