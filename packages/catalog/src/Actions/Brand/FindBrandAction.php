<?php

declare(strict_types=1);

namespace Raid\Catalog\Actions\Brand;

use Illuminate\Database\Eloquent\Model;
use Raid\Catalog\Repositories\Contracts\BrandRepository;
use Raid\Pillar\Actions\BaseAction;

class FindBrandAction extends BaseAction
{
    public function __construct(
        private readonly BrandRepository $brandRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $conditions
     * @param  array<string>  $columns
     */
    public function handle(array $conditions, array $columns = ['*']): Model
    {
        return $this->brandRepository->getModel()
            ->query()
            ->accessibleByUser()
            ->where($conditions)
            ->firstOrFail($columns);
    }
}
