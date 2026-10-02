<?php

declare(strict_types=1);

namespace Raid\Pillar\Repositories\Contracts;

use Raid\Pillar\Repositories\BaseRepository;
use Raid\Pillar\Repositories\BaseRepositoryCache;
use Raid\Pillar\Repositories\Contracts\Concerns\Aggregatable;
use Raid\Pillar\Repositories\Contracts\Concerns\Creatable;
use Raid\Pillar\Repositories\Contracts\Concerns\Deletable;
use Raid\Pillar\Repositories\Contracts\Concerns\Readable;
use Raid\Pillar\Repositories\Contracts\Concerns\RepositoryUtility;
use Raid\Pillar\Repositories\Contracts\Concerns\Restorable;
use Raid\Pillar\Repositories\Contracts\Concerns\Updatable;

/**
 * Complete Repository Contract Interface.
 *
 * This interface combines all repository operation interfaces into a single contract.
 * It follows the Interface Segregation Principle (ISP) by extending smaller,
 * focused interfaces that can be used independently.
 *
 * ## Composed Interfaces
 *
 * - {@see Creatable} - CREATE operations (create, createMany, insert, firstOrCreate)
 * - {@see Readable} - READ operations (find, get, paginate, count)
 * - {@see Updatable} - UPDATE operations (update, updateEach, updateById, upsert)
 * - {@see Deletable} - DELETE operations (delete, deleteEach, deleteById, deleteMany)
 * - {@see RepositoryUtility} - Utility operations (chunk, transaction, builder access)
 *
 * ## Usage
 *
 * You can type-hint against this interface when you need full repository functionality:
 *
 * ```php
 * public function __construct(Repository $repository) {}
 * ```
 *
 * Or use specific interfaces when you only need certain operations:
 *
 * ```php
 * use Raid\Pillar\Repositories\Contracts\Concerns\Readable;
 * use Raid\Pillar\Repositories\Contracts\Concerns\Creatable;
 *
 * public function __construct(Readable $repository) {}  // Only read operations
 * public function __construct(Creatable $repository) {} // Only create operations
 * ```
 *
 * Or combine specific interfaces:
 *
 * ```php
 * use Raid\Pillar\Repositories\Contracts\Concerns\Readable;
 * use Raid\Pillar\Repositories\Contracts\Concerns\Updatable;
 *
 * public function handle(Readable&Updatable $repository) {}
 * ```
 *
 * ## Implementations
 *
 * @see BaseRepository Default implementation
 * @see BaseRepositoryCache Cached decorator implementation
 */
interface Repository extends Aggregatable, Creatable, Deletable, Readable, RepositoryUtility, Restorable, Updatable
{
    // This interface combines all repository operations.
    // See individual Concerns interfaces for method documentation.
}
