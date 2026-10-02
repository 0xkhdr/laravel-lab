<?php

declare(strict_types=1);

namespace Raid\Catalog\Actions\Product;

use Raid\Catalog\Repositories\Contracts\ProductRepository;
use Raid\Pillar\Actions\BaseAction;
use Throwable;

class DeleteProductAction extends BaseAction
{
    public function __construct(
        private readonly ProductRepository $productRepository,
    ) {}

    /**
     * @param  array<string>  $ids
     *
     * @throws Throwable
     */
    public function handle(array $ids): int
    {
        return $this->productRepository->deleteManyOrFail($ids);
    }
}
