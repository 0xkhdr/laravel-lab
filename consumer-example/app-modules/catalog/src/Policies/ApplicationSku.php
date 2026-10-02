<?php

declare(strict_types=1);

namespace Modules\Catalog\Policies;

use Illuminate\Validation\ValidationException;
use Raid\Catalog\Contracts\SkuPolicy;

class ApplicationSku implements SkuPolicy
{
    public function apply(string $sku): string
    {
        $sku = strtoupper(trim($sku));
        if (! preg_match('/^[A-Z0-9-]+$/', $sku)) {
            throw ValidationException::withMessages(['sku' => 'Use letters, numbers and hyphens.']);
        }

        return $sku;
    }
}
