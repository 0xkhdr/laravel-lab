<?php

declare(strict_types=1);

namespace Raid\Pillar\Repositories\Contracts\Concerns;

use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Contract for soft-delete restore operations.
 *
 * Separated from Deletable in accordance with the Interface Segregation Principle.
 * A service that can only delete should not also gain restore capability.
 * Require this interface independently when soft-delete restoration is needed.
 *
 * ## Requirements
 *
 * Both methods require the Eloquent model to use the SoftDeletes trait.
 * They operate exclusively on trashed (soft-deleted) records.
 *
 * ## Usage
 *
 * ```php
 * // Type-hint only what you need:
 * public function __construct(Deletable&Restorable $repository) {}
 *
 * // Or via the composite Repository contract:
 * public function __construct(Repository $repository) {}
 * ```
 */
interface Restorable
{
    /**
     * Restore soft-deleted records matching conditions.
     *
     * Requires the model to use the `SoftDeletes` trait. Only searches within
     * the trashed (soft-deleted) records and restores matching ones.
     *
     * @param  array<string, mixed>  $conditions  Where conditions
     * @return int Number of restored rows
     *
     * @example
     * ```php
     * $restored = $repository->restore(['user_id' => 5]);
     * ```
     */
    public function restore(array $conditions): int;

    /**
     * Restore a single soft-deleted record by its primary key.
     *
     * Requires the model to use the `SoftDeletes` trait.
     *
     * @param  int|string  $id  The primary key value
     * @return bool True if restored, false if not found in trash
     *
     * @throws ModelNotFoundException When the record does not exist at all
     *
     * @example
     * ```php
     * if ($repository->restoreById(5)) {
     *     // Record was in trash and has been restored
     * }
     * ```
     */
    public function restoreById(int|string $id): bool;
}
