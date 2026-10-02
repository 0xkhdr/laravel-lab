<?php

declare(strict_types=1);

namespace Raid\Pillar\Repositories\Contracts\Concerns;

/**
 * Contract for aggregate database operations.
 *
 * Provides typed aggregate methods so callers never need to reach around the
 * repository abstraction and issue raw DB:: queries for reporting or dashboards.
 *
 * ## Usage
 *
 * ```php
 * // Type-hint the full contract:
 * public function __construct(Repository $repository) {}
 *
 * // Or narrow the contract to only aggregates:
 * public function __construct(Aggregatable $repository) {}
 * ```
 *
 * @example
 * ```php
 * $total   = $repository->sum('amount', ['status' => 'completed']);
 * $average = $repository->avg('amount', ['merchant_id' => $id]);
 * $min     = $repository->min('price');
 * $max     = $repository->max('price', ['category_id' => 5]);
 * ```
 */
interface Aggregatable
{
    /**
     * Sum the values of a column, optionally filtered by conditions.
     *
     * Returns 0 when no records match the conditions.
     *
     * @param  string  $column  The column to sum
     * @param  array<string, mixed>  $conditions  Where conditions
     * @return int|float The sum value (0 if no records match)
     *
     * @example
     * ```php
     * $revenue = $repository->sum('amount', ['status' => 'completed']);
     * ```
     */
    public function sum(string $column, array $conditions = []): int|float;

    /**
     * Calculate the average of a column, optionally filtered by conditions.
     *
     * Returns null when no records match the conditions.
     *
     * @param  string  $column  The column to average
     * @param  array<string, mixed>  $conditions  Where conditions
     * @return float|null The average value or null if no records match
     *
     * @example
     * ```php
     * $avgOrder = $repository->avg('total', ['merchant_id' => $id]);
     * ```
     */
    public function avg(string $column, array $conditions = []): ?float;

    /**
     * Find the minimum value of a column, optionally filtered by conditions.
     *
     * Returns null when no records match the conditions.
     *
     * @param  string  $column  The column to find the minimum of
     * @param  array<string, mixed>  $conditions  Where conditions
     * @return mixed The minimum value or null if no records match
     *
     * @example
     * ```php
     * $minPrice = $repository->min('price', ['status' => 'active']);
     * ```
     */
    public function min(string $column, array $conditions = []): mixed;

    /**
     * Find the maximum value of a column, optionally filtered by conditions.
     *
     * Returns null when no records match the conditions.
     *
     * @param  string  $column  The column to find the maximum of
     * @param  array<string, mixed>  $conditions  Where conditions
     * @return mixed The maximum value or null if no records match
     *
     * @example
     * ```php
     * $maxPrice = $repository->max('price', ['status' => 'active']);
     * ```
     */
    public function max(string $column, array $conditions = []): mixed;
}
