# raid/pillar

Runtime-only architecture for PHP 8.4 / Laravel 12. Direct dependencies: Laravel framework and InterNACHI Modular. Source Action/Repository authors and MIT metadata are retained; see the workspace source baseline.

`Raid\Pillar\Actions\BaseAction` preserves container-resolved `exec(...) → execute(...) → handle(...)`. Direct typed `handle()` calls remain supported. Missing handles raise BadMethodCallException; domain errors propagate. No implicit transaction is added.

`Raid\Pillar\Repositories\BaseRepository`, its contracts, Retrievable and QueryOptions retain the consumed CRUD/pagination/query semantics. Model-based updates/deletes fire Eloquent events and casts; bulk methods bypass model lifecycle. Transactions use the model connection; builders are cloned/reset explicitly. Query options are trusted application code, not a substitute for HTTP input validation. The optional filters option requires EloquentFilter and a compatible model.

`repository-cache` and `app-modules` config keys remain. BaseRepositoryCache keeps read controls and write invalidation. Cache keys can vary by consumer/user, while table tags invalidate all variants within the configured cache namespace. Unsupported stores, testing environment, active transactions and custom builders bypass caching. Transactional writes schedule invalidation after commit. Direct model writes outside a repository are outside this decorator's invalidation contract.

`PillarServiceProvider` merges runtime configuration only. InterNACHI supplies retained `app-modules/` discovery; modules are registered once through the application provider list and Composer autoload. Pillar has no domain dependencies, installers, generators or Foundry references.

Run `composer install` and `vendor/bin/pest` inside the package Docker directory. Redis gates use the workspace service and isolated random prefixes, rather than relying on the normal testing cache bypass.
