<?php

declare(strict_types=1);

namespace Raid\Catalog\Actions\Brand;

use Illuminate\Database\Eloquent\Model;
use Raid\Catalog\Repositories\Contracts\BrandRepository;
use Raid\Pillar\Actions\BaseAction;

class CreateBrandAction extends BaseAction
{
    public function __construct(
        private readonly BrandRepository $brandRepository,
    ) {}

    public function handle(array $values): Model
    {
        return $this->brandRepository->create($values);
    }
}
