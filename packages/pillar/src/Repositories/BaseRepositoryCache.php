<?php

declare(strict_types=1);

namespace Raid\Pillar\Repositories;

use Closure;
use Illuminate\Contracts\Cache\Repository as CacheContract;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\LazyCollection;
use Raid\Pillar\Repositories\Contracts\Repository as RepositoryContract;
use Raid\Pillar\Repositories\Contracts\RepositoryCache as RepositoryCacheContract;
use Raid\Pillar\Repositories\ValueObjects\QueryOptions;
use ReflectionException;
use ReflectionFunction;

/**
 * Cacheable repository decorator.
 *
 * Wraps any repository and adds caching for reads, invalidation on writes.
 *
 * ## Configuration
 *
 * All behaviour is driven by the `repository-cache` config file:
 *   - `enabled`             — global toggle (REPOSITORY_CACHE_ENABLED)
 *   - `driver`              — cache store name (REPOSITORY_CACHE_DRIVER)
 *   - `ttl`                 — default TTL seconds (REPOSITORY_CACHE_TTL)
 *   - `cache_empty_results` — whether to cache null / empty collections
 *   - `excluded_methods`    — method names that bypass the cache
 *   - `model_ttl`           — per-table TTL overrides
 *
 * ## TTL Priority (highest → lowest)
 *
 *   1. `cacheFor(int $seconds)` — per-call override (resets after one use)
 *   2. `model_ttl[$tableName]`  — per-model config
 *   3. Constructor `$ttl`       — per-repository override
 *   4. Config `ttl`             — global default
 *
 * ## Usage in ServiceProvider
 *
 * ```php
 * $this->app->bind(UserRepositoryInterface::class, fn ($app) =>
 *     new UserRepositoryCache($app->make(UserRepository::class))
 * );
 * ```
 */
class BaseRepositoryCache implements RepositoryCacheContract, RepositoryContract
{
    protected bool $skipCache = false;

    protected bool $forceRefresh = false;

    private bool $hasCustomBuilder = false;

    /** Per-call TTL override — consumed after one cached() call. */
    protected ?int $onceTtl = null;

    /** Resolved TTL in seconds (from constructor arg or config). */
    protected int $ttl;

    /** Resolved cache driver name (from constructor arg or config). */
    protected ?string $resolvedDriver;

    /** Whether caching is globally enabled — resolved once at construction. */
    private bool $globalCacheEnabled;

    /** Memoised tagged (or plain) cache store for read operations. */
    private ?CacheContract $resolvedStore = null;

    /**
     * Create a new cacheable repository decorator.
     *
     * @param  RepositoryContract  $repository  The repository to wrap
     * @param  int  $ttl  Cache TTL in seconds (0 = read from config)
     * @param  string|null  $driver  Cache driver name (null = read from config)
     * @param  string|null  $prefix  Cache key prefix (null = use model table name)
     */
    public function __construct(
        protected RepositoryContract $repository,
        int $ttl = 0,
        ?string $driver = null,
        protected ?string $prefix = null,
    ) {
        $this->ttl = $ttl > 0 ? $ttl : (int) config('repository-cache.ttl', 3600);
        $this->resolvedDriver = $driver ?? config('repository-cache.driver');
        $this->globalCacheEnabled = (bool) config('repository-cache.enabled', true);
    }

    /**
     * Get the cache TTL in seconds.
     */
    public function getCacheTtl(): int
    {
        return $this->ttl;
    }

    /**
     * Get the cache key prefix.
     */
    public function getCachePrefix(): string
    {
        return $this->prefix ?? $this->repository->getTable();
    }

    /**
     * Get the resolved cache driver name.
     */
    public function getCacheDriver(): ?string
    {
        return $this->resolvedDriver;
    }

    /**
     * Determine if caching should be used.
     *
     * Checks the global config at runtime so environment overrides (e.g.
     * `config()->set('repository-cache.enabled', false)` in test tearDown
     * or Orchestra Testbench's getEnvironmentSetUp) take effect immediately.
     */
    public function shouldCache(): bool
    {
        // Automatically disable in the 'testing' environment. This ensures
        // test suites that set APP_ENV=testing (the Testbench default) never
        // hit tagged-cache drivers like Redis that may not be available or
        // configured during CI runs.
        if (app()->environment('testing')) {
            return false;
        }

        // Use the current config value at call time so runtime overrides
        // (e.g. `config()->set('repository-cache.enabled', false)` in tests
        // or Orchestra Testbench's getEnvironmentSetUp) take effect immediately.
        $configValue = config('repository-cache.enabled');

        $enabled = $configValue !== null
            ? (bool) $configValue
            : $this->globalCacheEnabled;

        return ! $this->skipCache && ! $this->hasCustomBuilder && $enabled
            && $this->repository->getModel()->getConnection()->transactionLevel() === 0
            && Cache::store($this->resolvedDriver)->supportsTags();
    }

    /**
     * Clear all cache for this repository.
     *
     * Returns true when the tagged cache was successfully flushed (Redis, Memcached).
     * Returns false for drivers without tag support (file, array, database) — cache
     * entries cannot be invalidated and a warning is logged. Use a tag-aware driver
     * in production, or disable caching entirely via REPOSITORY_CACHE_ENABLED=false.
     */
    public function clearCache(): bool
    {
        $connection = $this->repository->getModel()->getConnection();
        if ($connection->transactionLevel() > 0) {
            $store = Cache::store($this->resolvedDriver);
            if (config('repository-cache.enabled', true) && $store->supportsTags()) {
                $connection->afterCommit(fn () => $store->tags([$this->repository->getTable()])->flush());
            }

            return true;
        }
        if (! (bool) config('repository-cache.enabled', $this->globalCacheEnabled)) {
            // Reset memoised store so the next shouldCache() = true call
            // picks up any runtime driver changes.
            $this->resolvedStore = null;

            return true;
        }

        // Resolve the driver at call time to respect runtime config changes.
        $driverName = $this->resolvedDriver ?? config('repository-cache.driver');
        $store = Cache::store($driverName);

        if (! $store->supportsTags()) {
            Log::warning('Repository cache could not be cleared: the configured cache driver does not support tags. Switch to a tag-aware driver (Redis, Memcached) or disable caching via REPOSITORY_CACHE_ENABLED=false.', [
                'driver' => $this->resolvedDriver ?? 'default',
                'prefix' => $this->getCachePrefix(),
            ]);

            return false;
        }

        return $store->tags([$this->repository->getTable()])->flush();
    }

    /**
     * Skip cache for the next query.
     */
    public function withoutCache(): static
    {
        $this->skipCache = true;

        return $this;
    }

    /**
     * Force refresh the cache on the next query.
     */
    public function refreshCache(): static
    {
        $this->forceRefresh = true;

        return $this;
    }

    /**
     * Override the cache TTL for the next read operation only.
     *
     * Resets automatically after the next cached() call.
     *
     *   $repo->cacheFor(30)->find($id);   // cached for 30 seconds
     *   $repo->find($id);                  // back to default TTL
     */
    public function cacheFor(int $seconds): static
    {
        $this->onceTtl = $seconds;

        return $this;
    }

    // -------------------------------------------------------------------------
    // READ operations (all use cached())
    // -------------------------------------------------------------------------

    /**
     * Get all records (cached).
     *
     * @param  array<int, string>  $columns
     * @param  array<string, mixed>|QueryOptions  $options
     */
    public function get(array $columns = ['*'], array|QueryOptions $options = []): Collection
    {
        $normalizedOptions = $this->normalizeOptions($options);

        return $this->cached('get', ['columns' => $columns, 'options' => $normalizedOptions], fn () => $this->repository->get($columns, $options));
    }

    /**
     * Get records by conditions (cached).
     *
     * @param  array<string, mixed>  $conditions
     * @param  array<int, string>  $columns
     * @param  array<string, mixed>|QueryOptions  $options
     */
    public function getBy(array $conditions, array $columns = ['*'], array|QueryOptions $options = []): Collection
    {
        $normalizedOptions = $this->normalizeOptions($options);

        return $this->cached('getBy', ['conditions' => $conditions, 'columns' => $columns, 'options' => $normalizedOptions], fn () => $this->repository->getBy($conditions, $columns, $options));
    }

    /**
     * Get records matching any condition group — OR logic (cached).
     *
     * @param  array<int, array<string, mixed>>  $conditionGroups
     * @param  array<int, string>  $columns
     * @param  array<string, mixed>|QueryOptions  $options
     * @return Collection<int, Model>
     */
    public function getByOr(array $conditionGroups, array $columns = ['*'], array|QueryOptions $options = []): Collection
    {
        $normalizedOptions = $this->normalizeOptions($options);

        return $this->cached('getByOr', ['conditionGroups' => $conditionGroups, 'columns' => $columns, 'options' => $normalizedOptions], fn () => $this->repository->getByOr($conditionGroups, $columns, $options));
    }

    /**
     * Paginate with total count (cached).
     *
     * @param  array<int, string>  $columns
     * @param  array<string, mixed>|QueryOptions  $options
     */
    public function paginate(
        array $columns = ['*'],
        array|QueryOptions $options = [],
        ?int $perPage = null,
        ?int $page = null,
    ): LengthAwarePaginator {
        $normalizedOptions = $this->normalizeOptions($options);

        return $this->cached('paginate', ['columns' => $columns, 'options' => $normalizedOptions, 'perPage' => $perPage, 'page' => $page], fn () => $this->repository->paginate($columns, $options, $perPage, $page));
    }

    /**
     * Paginate records by conditions with total count (cached).
     *
     * @param  array<string, mixed>  $conditions
     * @param  array<int, string>  $columns
     * @param  array<string, mixed>|QueryOptions  $options
     */
    public function paginateBy(
        array $conditions,
        array $columns = ['*'],
        array|QueryOptions $options = [],
        ?int $perPage = null,
        ?int $page = null,
    ): LengthAwarePaginator {
        $normalizedOptions = $this->normalizeOptions($options);

        return $this->cached('paginateBy', ['conditions' => $conditions, 'columns' => $columns, 'options' => $normalizedOptions, 'perPage' => $perPage, 'page' => $page], fn () => $this->repository->paginateBy($conditions, $columns, $options, $perPage, $page));
    }

    /**
     * Simple pagination without total count (cached).
     *
     * @param  array<int, string>  $columns
     * @param  array<string, mixed>|QueryOptions  $options
     */
    public function simplePaginate(
        array $columns = ['*'],
        array|QueryOptions $options = [],
        ?int $perPage = null,
        ?int $page = null,
    ): Paginator {
        $normalizedOptions = $this->normalizeOptions($options);

        return $this->cached('simplePaginate', ['columns' => $columns, 'options' => $normalizedOptions, 'perPage' => $perPage, 'page' => $page], fn () => $this->repository->simplePaginate($columns, $options, $perPage, $page));
    }

    /**
     * Cursor-based pagination for large datasets (cached).
     *
     * @param  array<int, string>  $columns
     * @param  array<string, mixed>|QueryOptions  $options
     */
    public function cursorPaginate(
        array $columns = ['*'],
        array|QueryOptions $options = [],
        ?int $perPage = null,
        ?string $cursor = null,
    ): CursorPaginator {
        $normalizedOptions = $this->normalizeOptions($options);

        return $this->cached('cursorPaginate', ['columns' => $columns, 'options' => $normalizedOptions, 'perPage' => $perPage, 'cursor' => $cursor], fn () => $this->repository->cursorPaginate($columns, $options, $perPage, $cursor));
    }

    /**
     * Find a record by its primary key (cached).
     *
     * @param  array<int, string>  $columns
     */
    public function find(int|string $id, array $columns = ['*']): ?Model
    {
        return $this->cached('find', ['id' => $id, 'columns' => $columns], fn () => $this->repository->find($id, $columns));
    }

    /**
     * Find a record by its primary key or throw exception (cached).
     *
     * @param  array<int, string>  $columns
     *
     * @throws ModelNotFoundException
     */
    public function findOrFail(int|string $id, array $columns = ['*']): Model
    {
        return $this->cached('findOrFail', ['id' => $id, 'columns' => $columns], fn () => $this->repository->findOrFail($id, $columns));
    }

    /**
     * Find multiple records by their primary keys (cached).
     *
     * @param  array<int, int|string>  $ids
     * @param  array<int, string>  $columns
     * @return Collection<int, Model>
     */
    public function findMany(array $ids, array $columns = ['*']): Collection
    {
        return $this->cached('findMany', ['ids' => $ids, 'columns' => $columns], fn () => $this->repository->findMany($ids, $columns));
    }

    /**
     * Find multiple records by primary keys or throw if any are missing (cached).
     *
     * @param  array<int, int|string>  $ids
     * @param  array<int, string>  $columns
     * @return Collection<int, Model>
     *
     * @throws ModelNotFoundException
     */
    public function findManyOrFail(array $ids, array $columns = ['*']): Collection
    {
        return $this->cached('findManyOrFail', ['ids' => $ids, 'columns' => $columns], fn () => $this->repository->findManyOrFail($ids, $columns));
    }

    /**
     * Find a single record by conditions (cached).
     *
     * @param  array<string, mixed>  $conditions
     * @param  array<int, string>  $columns
     */
    public function findBy(array $conditions, array $columns = ['*']): ?Model
    {
        return $this->cached('findBy', ['conditions' => $conditions, 'columns' => $columns], fn () => $this->repository->findBy($conditions, $columns));
    }

    /**
     * Find a record by conditions or throw exception (cached).
     *
     * @param  array<string, mixed>  $conditions
     * @param  array<int, string>  $columns
     *
     * @throws ModelNotFoundException
     */
    public function findByOrFail(array $conditions, array $columns = ['*']): Model
    {
        return $this->cached('findByOrFail', ['conditions' => $conditions, 'columns' => $columns], fn () => $this->repository->findByOrFail($conditions, $columns));
    }

    /**
     * Find a single record matching any condition group — OR logic (cached).
     *
     * @param  array<int, array<string, mixed>>  $conditionGroups
     * @param  array<int, string>  $columns
     */
    public function findByOr(array $conditionGroups, array $columns = ['*']): ?Model
    {
        return $this->cached('findByOr', ['conditionGroups' => $conditionGroups, 'columns' => $columns], fn () => $this->repository->findByOr($conditionGroups, $columns));
    }

    /**
     * Count records (cached).
     *
     * @param  array<string, mixed>  $conditions
     */
    public function count(array $conditions = []): int
    {
        return $this->cached('count', ['conditions' => $conditions], fn () => $this->repository->count($conditions));
    }

    /**
     * Check if records exist (cached).
     *
     * @param  array<string, mixed>  $conditions
     */
    public function exists(array $conditions): bool
    {
        return $this->cached('exists', ['conditions' => $conditions], fn () => $this->repository->exists($conditions));
    }

    // -------------------------------------------------------------------------
    // AGGREGATE operations (all use cached())
    // -------------------------------------------------------------------------

    /**
     * Sum a column's values (cached).
     *
     * @param  array<string, mixed>  $conditions
     */
    public function sum(string $column, array $conditions = []): int|float
    {
        return $this->cached('sum', ['column' => $column, 'conditions' => $conditions], fn () => $this->repository->sum($column, $conditions));
    }

    /**
     * Average a column's values (cached).
     *
     * @param  array<string, mixed>  $conditions
     */
    public function avg(string $column, array $conditions = []): ?float
    {
        return $this->cached('avg', ['column' => $column, 'conditions' => $conditions], fn () => $this->repository->avg($column, $conditions));
    }

    /**
     * Find the minimum column value (cached).
     *
     * @param  array<string, mixed>  $conditions
     */
    public function min(string $column, array $conditions = []): mixed
    {
        return $this->cached('min', ['column' => $column, 'conditions' => $conditions], fn () => $this->repository->min($column, $conditions));
    }

    /**
     * Find the maximum column value (cached).
     *
     * @param  array<string, mixed>  $conditions
     */
    public function max(string $column, array $conditions = []): mixed
    {
        return $this->cached('max', ['column' => $column, 'conditions' => $conditions], fn () => $this->repository->max($column, $conditions));
    }

    // -------------------------------------------------------------------------
    // WRITE operations (all invalidate cache)
    // -------------------------------------------------------------------------

    /**
     * Create a new record (invalidates cache).
     *
     * @param  array<string, mixed>  $values
     */
    public function create(array $values): Model
    {
        return tap($this->repository->create($values), fn (): bool => $this->clearCache());
    }

    /**
     * Create multiple records (invalidates cache).
     *
     * @param  array<int, array<string, mixed>>  $records
     */
    public function createMany(array $records): Collection
    {
        return tap($this->repository->createMany($records), fn (): bool => $this->clearCache());
    }

    /**
     * Update records (invalidates cache).
     *
     * @param  array<string, mixed>  $conditions
     * @param  array<string, mixed>  $values
     */
    public function update(array $conditions, array $values): int
    {
        return tap($this->repository->update($conditions, $values), fn (): bool => $this->clearCache());
    }

    /**
     * Update records or throw if none found (invalidates cache).
     *
     * @param  array<string, mixed>  $conditions
     * @param  array<string, mixed>  $values
     *
     * @throws ModelNotFoundException
     */
    public function updateOrFail(array $conditions, array $values): int
    {
        return tap($this->repository->updateOrFail($conditions, $values), fn (): bool => $this->clearCache());
    }

    /**
     * Update records using Eloquent models (invalidates cache).
     *
     * @param  array<string, mixed>  $conditions
     * @param  array<string, mixed>  $values
     * @return Collection<int, Model>
     */
    public function updateEach(array $conditions, array $values): Collection
    {
        return tap($this->repository->updateEach($conditions, $values), fn (): bool => $this->clearCache());
    }

    /**
     * Update records using Eloquent models or throw if none found (invalidates cache).
     *
     * @param  array<string, mixed>  $conditions
     * @param  array<string, mixed>  $values
     * @return Collection<int, Model>
     *
     * @throws ModelNotFoundException
     */
    public function updateEachOrFail(array $conditions, array $values): Collection
    {
        return tap($this->repository->updateEachOrFail($conditions, $values), fn (): bool => $this->clearCache());
    }

    /**
     * Update a record by its primary key (invalidates cache).
     *
     * @param  array<string, mixed>  $values
     */
    public function updateById(int|string $id, array $values): ?Model
    {
        return tap($this->repository->updateById($id, $values), fn (): bool => $this->clearCache());
    }

    /**
     * Update a record by its primary key or throw exception (invalidates cache).
     *
     * @param  array<string, mixed>  $values
     *
     * @throws ModelNotFoundException
     */
    public function updateByIdOrFail(int|string $id, array $values): Model
    {
        return tap($this->repository->updateByIdOrFail($id, $values), fn (): bool => $this->clearCache());
    }

    /**
     * Delete records (invalidates cache).
     *
     * @param  array<string, mixed>  $conditions
     */
    public function delete(array $conditions): int
    {
        return tap($this->repository->delete($conditions), fn (): bool => $this->clearCache());
    }

    /**
     * Delete records or throw if none found (invalidates cache).
     *
     * @param  array<string, mixed>  $conditions
     *
     * @throws ModelNotFoundException
     */
    public function deleteOrFail(array $conditions): int
    {
        return tap($this->repository->deleteOrFail($conditions), fn (): bool => $this->clearCache());
    }

    /**
     * Delete records using Eloquent models (invalidates cache).
     *
     * @param  array<string, mixed>  $conditions
     * @return Collection<int, Model>
     */
    public function deleteEach(array $conditions): Collection
    {
        return tap($this->repository->deleteEach($conditions), fn (): bool => $this->clearCache());
    }

    /**
     * Delete records using Eloquent models or throw if none found (invalidates cache).
     *
     * @param  array<string, mixed>  $conditions
     * @return Collection<int, Model>
     *
     * @throws ModelNotFoundException
     */
    public function deleteEachOrFail(array $conditions): Collection
    {
        return tap($this->repository->deleteEachOrFail($conditions), fn (): bool => $this->clearCache());
    }

    /**
     * Delete a record by its primary key (invalidates cache).
     */
    public function deleteById(int|string $id): bool
    {
        return tap($this->repository->deleteById($id), fn (): bool => $this->clearCache());
    }

    /**
     * Delete a record by its primary key or throw exception (invalidates cache).
     *
     * @throws ModelNotFoundException
     */
    public function deleteByIdOrFail(int|string $id): bool
    {
        return tap($this->repository->deleteByIdOrFail($id), fn (): bool => $this->clearCache());
    }

    /**
     * Delete multiple records by primary keys (invalidates cache).
     *
     * @param  array<int, int|string>  $ids
     */
    public function deleteMany(array $ids): int
    {
        return tap($this->repository->deleteMany($ids), fn (): bool => $this->clearCache());
    }

    /**
     * Delete multiple records by primary keys or throw if none found (invalidates cache).
     *
     * @param  array<int, int|string>  $ids
     *
     * @throws ModelNotFoundException
     */
    public function deleteManyOrFail(array $ids): int
    {
        return tap($this->repository->deleteManyOrFail($ids), fn (): bool => $this->clearCache());
    }

    /**
     * Update or create a record.
     *
     * Only invalidates cache when a record was actually created or changed.
     * When the existing record already matches the values, cache is preserved.
     *
     * @param  array<string, mixed>  $conditions
     * @param  array<string, mixed>  $values
     */
    public function updateOrCreate(array $conditions, array $values): Model
    {
        $model = $this->repository->updateOrCreate($conditions, $values);

        if ($model->wasRecentlyCreated || $model->wasChanged()) {
            $this->clearCache();
        }

        return $model;
    }

    /**
     * Insert records (invalidates cache).
     *
     * @param  array<int|string, mixed>  $values
     */
    public function insert(array $values): bool
    {
        return tap($this->repository->insert($values), fn (): bool => $this->clearCache());
    }

    /**
     * Insert a record and get the ID (invalidates cache).
     *
     * @param  array<string, mixed>  $values
     */
    public function insertGetId(array $values): int
    {
        return tap($this->repository->insertGetId($values), fn (): bool => $this->clearCache());
    }

    /**
     * Insert multiple records in chunks (invalidates cache).
     *
     * @param  array<int, array<string, mixed>>  $records
     */
    public function insertMany(array $records, int $chunkSize = 500): bool
    {
        return tap($this->repository->insertMany($records, $chunkSize), fn (): bool => $this->clearCache());
    }

    /**
     * Restore soft-deleted records matching conditions (invalidates cache).
     *
     * @param  array<string, mixed>  $conditions
     */
    public function restore(array $conditions): int
    {
        return tap($this->repository->restore($conditions), fn (): bool => $this->clearCache());
    }

    /**
     * Restore a single soft-deleted record by its primary key (invalidates cache).
     */
    public function restoreById(int|string $id): bool
    {
        return tap($this->repository->restoreById($id), fn (): bool => $this->clearCache());
    }

    /**
     * Find or create a record (invalidates cache only when a new record is created).
     *
     * @param  array<string, mixed>  $conditions
     * @param  array<string, mixed>  $values
     */
    public function firstOrCreate(array $conditions, array $values = []): Model
    {
        $model = $this->repository->firstOrCreate($conditions, $values);

        if ($model->wasRecentlyCreated) {
            $this->clearCache();
        }

        return $model;
    }

    /**
     * Upsert records (invalidates cache).
     *
     * @param  array<int, array<string, mixed>>  $values
     * @param  array<int, string>  $uniqueBy
     * @param  array<int, string>|null  $update
     */
    public function upsert(array $values, array $uniqueBy, ?array $update = null): int
    {
        return tap($this->repository->upsert($values, $uniqueBy, $update), fn (): bool => $this->clearCache());
    }

    // -------------------------------------------------------------------------
    // STREAMING operations (bypass cache — not cacheable)
    // -------------------------------------------------------------------------

    /**
     * Process records in chunks (bypasses cache).
     */
    public function chunk(int $count, callable $callback): bool
    {
        return $this->repository->chunk($count, $callback);
    }

    /**
     * Process records using keyset (ID-based) chunking (bypasses cache).
     */
    public function chunkById(int $count, callable $callback, ?string $column = null): bool
    {
        return $this->repository->chunkById($count, $callback, $column);
    }

    /**
     * Return a lazy collection (bypasses cache — streaming, not cacheable).
     *
     * @return LazyCollection<int, Model>
     */
    public function lazy(int $chunkSize = 1000): LazyCollection
    {
        return $this->repository->lazy($chunkSize);
    }

    /**
     * Return a lazy collection using keyset chunking (bypasses cache — streaming).
     *
     * @return LazyCollection<int, Model>
     */
    public function lazyById(int $chunkSize = 1000, ?string $column = null): LazyCollection
    {
        return $this->repository->lazyById($chunkSize, $column);
    }

    /**
     * Execute operations within a transaction (bypasses cache).
     */
    public function transaction(callable $callback): mixed
    {
        return $this->repository->transaction($callback);
    }

    // -------------------------------------------------------------------------
    // BUILDER access
    // -------------------------------------------------------------------------

    /**
     * Get the underlying model.
     */
    public function getModel(): Model
    {
        return $this->repository->getModel();
    }

    /**
     * Get the model's table name.
     */
    public function getTable(): string
    {
        return $this->repository->getTable();
    }

    /**
     * Get the current query builder.
     */
    public function getBuilder(): Builder
    {
        return $this->repository->getBuilder();
    }

    /**
     * Reset the query builder.
     */
    public function resetBuilder(): static
    {
        $this->hasCustomBuilder = false;
        $this->repository->resetBuilder();

        return $this;
    }

    /**
     * Set a base builder for queries.
     */
    public function withBuilder(Builder $builder): static
    {
        $this->hasCustomBuilder = true;
        $this->repository->withBuilder($builder);

        return $this;
    }

    // -------------------------------------------------------------------------
    // Cache internals
    // -------------------------------------------------------------------------

    /**
     * Cache a query result.
     *
     * Behaviour is governed by three config keys:
     *   - `excluded_methods`:    method names that always bypass caching
     *   - `cache_empty_results`: when false, null / empty collections are not cached
     *   - `model_ttl`:           per-table TTL overrides
     *
     * Flags ($skipCache, $forceRefresh, $onceTtl) are reset unconditionally via
     * try/finally — even if the callback or the cache driver throws — preventing
     * flag leakage into subsequent calls.
     *
     * @param  array<string, mixed>  $params
     */
    protected function cached(string $method, array $params, callable $callback): mixed
    {
        try {
            $excluded = config('repository-cache.excluded_methods', []);

            if (! $this->shouldCache() || in_array($method, $excluded, true)) {
                return $callback();
            }

            $key = $this->key($method, $params);
            $store = $this->store();
            $ttl = $this->resolveEffectiveTtl();

            if ($this->forceRefresh) {
                $store->forget($key);
            }

            $cacheEmpty = (bool) config('repository-cache.cache_empty_results', true);

            if (! $cacheEmpty) {
                if ($store->has($key)) {
                    return $store->get($key);
                }

                $result = $callback();

                $isEmpty = $result === null
                    || ($result instanceof Collection && $result->isEmpty());

                if (! $isEmpty) {
                    $store->put($key, $result, $ttl);
                }

                return $result;
            }

            return $store->remember($key, $ttl, $callback);
        } finally {
            $this->resetFlags();
        }
    }

    /**
     * Resolve the effective TTL for the current operation.
     *
     * Priority (highest → lowest):
     *   1. cacheFor() per-call override
     *   2. model_ttl config keyed by table name
     *   3. Constructor TTL (already resolved from config at construction time)
     */
    protected function resolveEffectiveTtl(): int
    {
        if ($this->onceTtl !== null) {
            return $this->onceTtl;
        }

        /** @var array<string, int> $modelTtls */
        $modelTtls = config('repository-cache.model_ttl', []);
        $table = $this->repository->getTable();

        return isset($modelTtls[$table]) ? (int) $modelTtls[$table] : $this->ttl;
    }

    /**
     * Generate a closure-safe cache key.
     *
     * Replaces Closure instances with a stable fingerprint (file and line range)
     * before serialization, preventing "Serialization of 'Closure' is not allowed".
     *
     * @param  array<string, mixed>  $params
     *
     * @throws ReflectionException
     */
    protected function key(string $method, array $params = []): string
    {
        $this->replaceClosures($params);

        $this->ksortRecursive($params);

        return $this->getCachePrefix().':'.$method.':'.md5(serialize($params));
    }

    /**
     * Get the memoised cache store instance, tagged when the driver supports it.
     *
     * Resolves once per decorator lifetime — avoids repeated IoC resolution
     * on every read operation in high-throughput APIs.
     */
    protected function store(): CacheContract
    {
        // Resolve the driver name at call time so runtime config overrides
        // (e.g. `config()->set('cache.default', 'array')` in tests) take effect.
        $currentDriver = $this->resolvedDriver ?? config('repository-cache.driver');

        // Invalidate the memoised store when the driver changes (e.g. test
        // environments swap the driver after the decorator was constructed).
        if ($this->resolvedStore === null || $this->resolvedDriver !== $currentDriver) {
            $baseStore = Cache::store($currentDriver);

            $this->resolvedStore = $baseStore->supportsTags()
                ? $baseStore->tags([$this->repository->getTable()])
                : $baseStore;
        }

        return $this->resolvedStore;
    }

    /**
     * Reset all cache control flags.
     *
     * Called unconditionally in the cached() finally block to prevent
     * flag leakage when the callback or cache driver throws an exception.
     */
    protected function resetFlags(): void
    {
        $this->skipCache = false;
        $this->forceRefresh = false;
        $this->onceTtl = null;
    }

    /**
     * Normalize options to a plain array for stable cache key generation.
     *
     * Ensures that passing `['sort' => 'name']` and `new QueryOptions(sort: 'name')`
     * produce the same cache key — both represent the same query intent.
     *
     * @param  array<string, mixed>|QueryOptions  $options
     * @return array<string, mixed>
     */
    private function normalizeOptions(array|QueryOptions $options): array
    {
        return $options instanceof QueryOptions ? $options->toArray() : $options;
    }

    /**
     * Recursively sort an array by keys.
     *
     * Ensures cache key stability regardless of the order in which option keys
     * are provided to the method call.
     */
    protected function ksortRecursive(array &$array): void
    {
        ksort($array);

        foreach ($array as &$value) {
            if (is_array($value)) {
                $this->ksortRecursive($value);
            }
        }
    }

    /**
     * Recursively replace Closure instances with deterministic string fingerprints.
     *
     * @param  array<string, mixed>  $params
     *
     * @throws ReflectionException
     */
    private function replaceClosures(array &$params): void
    {
        foreach ($params as &$value) {
            if ($value instanceof Closure) {
                $value = $this->closureFingerprint($value);
            } elseif (is_array($value)) {
                $this->replaceClosures($value);
            }
        }
    }

    /**
     * Generate a deterministic fingerprint for a Closure.
     *
     * @throws ReflectionException
     */
    private function closureFingerprint(Closure $closure): string
    {
        $ref = new ReflectionFunction($closure);

        return '__closure@'.$ref->getFileName().':'.$ref->getStartLine().'-'.$ref->getEndLine();
    }
}
