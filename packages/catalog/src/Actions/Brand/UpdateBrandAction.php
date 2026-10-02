<?php

declare(strict_types=1);

namespace Raid\Catalog\Actions\Brand;

use Illuminate\Database\Eloquent\Model;
use Raid\Catalog\Repositories\Contracts\BrandRepository;
use Raid\Pillar\Actions\BaseAction;
use Throwable;

class UpdateBrandAction extends BaseAction
{
    public function __construct(
        private readonly BrandRepository $brandRepository,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(string $id, array $values): Model
    {
        $this->brandRepository->updateByIdOrFail($id, $values);

        return $this->brandRepository->find($id);
    }
}
