<?php

declare(strict_types=1);

namespace Raid\Catalog\Actions\Product;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Raid\Catalog\Contracts\SkuPolicy;
use Raid\Catalog\Repositories\Contracts\ProductRepository;
use Raid\Pillar\Actions\BaseAction;

class CreateProductAction extends BaseAction
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly SkuPolicy $skuPolicy,
    ) {}

    public function handle(array $values): Model
    {
        Validator::make($values, ['sku' => ['required', 'string', 'max:100']])->validate();
        $values['sku'] = $this->skuPolicy->apply($values['sku']);
        Validator::make($values, ['sku' => ['required', 'string', 'max:100', 'unique:products,sku']])->validate();
        $model = $this->productRepository->create($values);

        return $model->load(['brand', 'category', 'region']);
    }
}
