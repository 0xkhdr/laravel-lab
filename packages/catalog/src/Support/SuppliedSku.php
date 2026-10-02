<?php

declare(strict_types=1);

namespace Raid\Catalog\Support;

use Raid\Catalog\Contracts\SkuPolicy;

class SuppliedSku implements SkuPolicy
{
    public function apply(string $sku): string
    {
        return $sku;
    }
}
