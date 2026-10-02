<?php

declare(strict_types=1);

namespace Raid\Catalog\Actions\Region;

use Illuminate\Database\Eloquent\Model;
use Raid\Catalog\Repositories\Contracts\RegionRepository;
use Raid\Pillar\Actions\BaseAction;

class FindRegionAction extends BaseAction
{
    public function __construct(
        private readonly RegionRepository $regionRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $conditions
     * @param  array<string>  $columns
     */
    public function handle(array $conditions, array $columns = ['*']): Model
    {
        return $this->regionRepository->findByOrFail($conditions, $columns);
    }
}
