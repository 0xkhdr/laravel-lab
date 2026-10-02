<?php

declare(strict_types=1);

namespace Raid\Catalog\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Raid\Catalog\Support\CatalogAccess;

/** Optional global Product scope sharing the fail-closed local access filter. */
class AccessibleByUserScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder and model.
     *
     * @param  Builder<Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        CatalogAccess::apply($builder);
    }
}
