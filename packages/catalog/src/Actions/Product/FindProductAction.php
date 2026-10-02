<?php

declare(strict_types=1);

namespace Raid\Catalog\Actions\Product;

use Illuminate\Database\Eloquent\Model;
use Raid\Catalog\Repositories\Contracts\ProductRepository;
use Raid\Pillar\Actions\BaseAction;

class FindProductAction extends BaseAction
{
    public function __construct(
        private readonly ProductRepository $productRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $conditions
     * @param  array<string>  $columns
     */
    public function handle(array $conditions, array $columns = ['*']): Model
    {
        return $this->productRepository->getModel()
            ->query()
            ->accessibleByUser()
            ->where($conditions)
            ->firstOrFail($columns)
            ->loadMissing(['brand', 'category', 'region']);
    }
}
