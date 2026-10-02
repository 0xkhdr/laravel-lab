<?php

declare(strict_types=1);

namespace Raid\Catalog\Actions\Region;

use Illuminate\Database\Eloquent\Model;
use Raid\Catalog\Repositories\Contracts\RegionRepository;
use Raid\Pillar\Actions\BaseAction;
use Throwable;

class UpdateRegionAction extends BaseAction
{
    public function __construct(
        private readonly RegionRepository $regionRepository,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(string $id, array $values): Model
    {
        $this->regionRepository->updateByIdOrFail($id, $values);

        return $this->regionRepository->find($id);
    }
}
