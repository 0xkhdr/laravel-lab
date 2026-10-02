<?php

declare(strict_types=1);

namespace Raid\Catalog\Actions\Product;

use Illuminate\Database\Eloquent\Model;
use Raid\Catalog\Repositories\Contracts\ProductRepository;
use Raid\Pillar\Actions\BaseAction;
use Throwable;

class UpdateProductAction extends BaseAction
{
    public function __construct(
        private readonly ProductRepository $productRepository,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(string $id, array $values): Model
    {
        $this->productRepository->updateByIdOrFail($id, $values);

        $model = $this->productRepository->find($id);

        return $model->load(['brand', 'category', 'region']);
    }
}
