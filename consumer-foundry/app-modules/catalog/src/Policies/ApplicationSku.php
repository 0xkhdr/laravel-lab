<?php

declare(strict_types=1);

namespace Modules\Catalog\Policies;

use Raid\Catalog\Contracts\SkuPolicy;

class ApplicationSku implements SkuPolicy
{
    public function apply(string $sku): string
    {
        return $sku;
    }
}
