<?php

declare(strict_types=1);

namespace Raid\Catalog\Contracts;

interface SkuPolicy
{
    public function apply(string $sku): string;
}
