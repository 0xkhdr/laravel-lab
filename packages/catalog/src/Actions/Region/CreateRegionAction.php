<?php

declare(strict_types=1);

namespace Raid\Catalog\Actions\Region;

use Illuminate\Database\Eloquent\Model;
use Raid\Catalog\Repositories\Contracts\RegionRepository;
use Raid\Pillar\Actions\BaseAction;

class CreateRegionAction extends BaseAction
{
    public function __construct(
        private readonly RegionRepository $regionRepository,
    ) {}

    public function handle(array $values): Model
    {
        return $this->regionRepository->create($values);
    }
}
