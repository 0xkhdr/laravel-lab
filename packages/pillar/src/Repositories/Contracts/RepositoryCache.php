<?php

declare(strict_types=1);

namespace Raid\Pillar\Repositories\Contracts;

/**
 * Contract for cacheable repository implementations.
 */
interface RepositoryCache
{
    /**
     * Get the cache TTL in seconds.
     */
    public function getCacheTtl(): int;

    /**
     * Get the cache key prefix.
     */
    public function getCachePrefix(): string;

    /**
     * Get the cache driver name.
     */
    public function getCacheDriver(): ?string;

    /**
     * Determine if caching is enabled.
     */
    public function shouldCache(): bool;

    /**
     * Clear all cache for this repository.
     */
    public function clearCache(): bool;

    /**
     * Disable caching for the next query.
     */
    public function withoutCache(): static;

    /**
     * Force refresh the cache on next query.
     */
    public function refreshCache(): static;

    /**
     * Override the cache TTL for the next read operation only.
     *
     * Resets automatically after the next cached() call. All subsequent calls
     * revert to the configured default TTL.
     *
     * @param  int  $seconds  TTL in seconds for the next operation only
     * @return static Returns self for method chaining
     *
     * @example
     * ```php
     * $repo->cacheFor(30)->find($id);  // cached for 30 seconds
     * $repo->find($id);                // back to default TTL
     * ```
     */
    public function cacheFor(int $seconds): static;
}
