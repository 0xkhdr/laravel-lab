# Stage 0 source baseline

Revision: `6a2cf9816b7f0fd64ba8aa26192ae00352e9e77f`. Only the supplied planning files are untracked. Selected diff is empty; full selected-source SHA-256 manifest is in `reference-sha256.txt`.

Authoritative paths are relative to the read-only reference:

| Behavior | Source | Target |
| --- | --- | --- |
| exec → execute → typed handle, container resolution, no automatic transaction | pkgs/frontier/action/src/BaseAction.php, Contracts/Action.php | Pillar Actions |
| fresh/clone builder, model updates fire events/casts, bulk updates bypass them, connection-owned transactions | pkgs/frontier/repository/src/BaseRepository.php | Pillar Repositories |
| consumed retrieve/paginate options | src/Traits/Retrievable.php, ValueObjects/QueryOptions.php in repository package | Retain signatures |
| cache testing bypass, tags required, user prefixes | BaseRepositoryCache.php and Catalog Repositories/Cache/* | Real Redis tests; shared table invalidation across users |
| generic translated CRUD, nullable region, SKU max 100/unique | Catalog Actions/*, Models/*, database/migrations/*; consumer requests/resources/tests | Catalog runtime + application HTTP adapters |
| guest/missing trait unrestricted, empty rows resolve null | Catalog Product.php, Brand.php, Category.php, HasCatalogPermissions.php | Explicit persisted states, fail closed |
| delete/reinsert permission pivots + invalidate cache | Catalog Actions/Permissions/* | Retain atomic union semantics; clearing denies type |
| module paths/provider/route discovery | Frontier Modular provider/config; app-modules/catalog/composer.json and routes | Pillar + InterNACHI, app-owned module |
| generator omits models; media targets absent models | InstallCatalogModuleCommand.php, MediaLibraryIntegrationHandler.php | No unsupported media recipe |
| silent edits, unchecked operations, automatic migrations | Starter FileModifier.php, IntegrationRunner.php; Catalog install commands | Foundry preflight/postconditions/journal; no migration execution |

Runtime dependency mapping: Frontier Actions/Repositories → explicitly declared raid/pillar. Pillar → Laravel 12 + InterNACHI. Catalog → Pillar + Laravel + Spatie Translatable. Foundry → Laravel/Pillar + Composer runtime metadata API. Auth and HTTP are consumer-owned, not mandatory Catalog domain dependencies.

Config: preserve repository-cache and app-modules; catalog-foundation → catalog (user_model, auth_guard, permission_cache_ttl). Existing catalog:install/module/integrate and starter registry are references only; proposed foundry commands require implementation. Retain package authors/license metadata; do not declare Composer replace.

Schema owners: consumer owns users/auth schema; Catalog module owns brands/categories/regions/products and three user permission pivots. New explicit permission state storage is Catalog-owned. No automatic migration loading/publication at runtime. Deterministic copies will be tracked by template identity in Foundry. No Wallet/Webhooks schema selected.

Test limits: historical consumer CRUD 20/44 is documented evidence, not rerun here. No independent Catalog package tests exist. New disposable characterization harness runs against read-only Action/Repository source; the new package harnesses will prove portability, permissions and Redis behavior independently.
