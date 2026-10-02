# Pillar / Foundry implementation record

Reference is mounted read-only at `/reference`. No production data or credentials are copied.

## Checklist (sequential gates)

- [x] Stage 0: source revision/diff/hashes; dependency, API, migration and behavior inventory.
- [x] Stage 1: PHP 8.4 Docker, fresh Laravel 12 consumer, isolated packages and tests.
- [x] Stage 2: extracted Action/Repository/module runtime; lifecycle, rollback and real cache checks.
- [x] Stage 3: generic Catalog, SKU hook and two configurations, explicit union permission tests.
- [x] Stage 4: implemented Foundry preview/apply/verify, deterministic ownership and conflict gates.
- [x] Stage 5: interruption/recovery/upgrade and independent copied no-dev production consumer.

## Decisions

Clean namespace break in this workspace only. Keep `app-modules/`, `Modules\\`, InterNACHI discovery, `repository-cache` and `app-modules` config keys. Catalog config becomes `catalog`. SQLite is consumer-owned; Redis is workspace-owned with separate consumer prefixes. Foundry never installs dependencies or executes migrations. Only Catalog is advertised. Wallet/Webhooks and absent registry entries are deferred.

## Stage 0 completed

Changes/decisions: `evidence/BASELINE.md`, revision/status/diff/hash manifest and runnable disposable source characterization harness. Executable schema has nullable region and translates name/description; stale documentation mentioning instructions is not ported. Source AGENTS files are reference conventions, not copied instructions for the new workspace.

Tests: Docker PHP 8.4.19, baseline PHPUnit: **2 tests / 12 assertions passed**. Historical Catalog CRUD result remains a separate 20/44 review result. Independent permission/cache/install tests are required in subsequent gates.

Blockers: none. Recovery: all work is isolated; delete new workspace artifacts to undo, no reference writes. Next: fresh Laravel 12 boot, isolated manifests/vendor/lockfiles and baseline tests.

## Stage 1 completed

Changes/decisions: fresh Laravel skeleton constrained to ^12, locked framework 12.69.3; PHP restricted to 8.4. Consumer has independent vendor/lockfile/SQLite/.env key, isolated Redis service and prefixes, memory DB/array cache test config. Package manifests and independent Testbench/Pest harness provisioned. Dockerfile builds a portable CLI image; available PHP 8.4.19 image used for bootstrap while build runs. Reference filesystem remains read-only.

Tests: consumer **2 tests / 2 assertions passed**; isolated Pillar boot **1 test / 1 assertion passed**; consumer strict Composer validation and config cache/clear passed. Local Pillar path resolution installed independently of reference vendor.

Blockers: none. Recovery: discard fresh consumer/package vendors and reinstall from their lockfiles; no reference effects. Next: extract characterized runtime and prove actual Redis cache behavior.

## Stage 2 completed

Changes/decisions: BaseAction/Action contract and consumed Repository implementation/contracts/query options extracted mechanically to Raid\\Pillar, retaining exec/execute/typed handle and lifecycle semantics. Runtime provider only merges repository-cache/app-modules; InterNACHI remains declared. Generators and command registration excluded. Source author/license provenance retained. Optional EloquentFilter must be installed by consumers choosing filters; no replacement filtering engine.

Tests: isolated Pillar **6 tests / 32 assertions passed**, including an actual Redis cached hit, bypass reset, update/delete invalidation. Consumer **2/2 passed**, config cache/clear and intended path resolution passed; Pint applied and tests rerun.

Blockers: none. Recovery: package code and independent lockfiles can be restored/reinstalled; no schema changes in this stage. Next: Catalog runtime tests, SKU consumer variants and permission/cache safety.

## Stage 3 completed

Changes/decisions: portable Catalog runtime copied without installers/integration handlers. SkuPolicy runs at create boundary, supplied SKU default, validation before/after policy. Consumer A preserves lowercase SKU; separately installed Consumer B normalizes uppercase and rejects invalid characters. Application modules own requests/resources/routes/gates/User adapter. HTTP retains Laravel resources (create 201; read/update/delete 200; validation 422), uses web authentication and explicit read/write gates, no Identity/RBAC foundation port. Users use UUID schema; administrator flag is application-owned, not an implicit domain bypass.

Permission storage: absent state = unconfigured/deny; explicit unrestricted = all; denied = none; restricted = brand OR category OR product whitelist; inherited = explicit parent hook with cycle/missing-parent denial. Empty sync denies that axis, preserves other grants, sets restricted. New state table and original Catalog tables have deterministic module-owned migration copies. Changes/invalidation occur after commit; effective permission fingerprints isolate cached reads and revocation. Pillar shares table invalidation tags across user variants, skips unsupported stores/active transactions.

Tests: independent Catalog **10/59 passed**; Pillar **6/32 passed**; each independent consumer **8/60 passed**, covering all four CRUD HTTP adapters, guest/client/admin denial, SKU behavior and invalid inputs. Consumer config/route/event caches passed. Pint applied before reruns. Tests exposed and fixed permission cache staleness, callback recursion under test transactions and missing provider registration.

Blockers: none. Recovery: application-owned new migrations are unapplied in persistent consumer DB; test schemas are disposable. Database execution remains a separate explicit operator action. Next: Foundry must emit the proven module, protect custom files, and verify planned outputs; no Composer installs or automatic migrations.

## Stage 4 completed

Changes/decisions: implemented foundry:install, add catalog, make-module (catalog.standard), doctor and file-only rollback. Recipe/blueprint v1.1.0 emits the tested module/adapters/tests, resolves configured module directory/namespaces, inspects Composer-installed packages and authentication schema prerequisites. Composer remains responsible for installation and autoload refresh. Unsupported recipes, unsafe paths/symlinks, duplicate migration identities, missing anchors/dependencies and customized files fail preflight. Planned PHP/JSON syntax is checked before writes. Atomic writes, generated-file hashes, stable operation/template IDs, backups and completed/pending journal states are verified. Dry-run creates no lock/journal.

HTTP correction: consumer-only Sanctum ^4 supplies bearer-token authentication with UUID token morph keys; Catalog remains independent of Sanctum/Identity. Application-owned users, administrator flag and token migrations are explicit recipe prerequisites. Read-only doctor verifies runtime bindings/auth/routes without applying database operations. Guest/client/admin and actual token revocation checks pass.

Tests: isolated Foundry **11/40 passed**; actual CLI dry-run source/config/lock/database/cache/journal snapshots unchanged; fresh compatible consumer apply → manual Composer autoload → doctor → **9/62 HTTP tests passed**; make-module rerun snapshot unchanged. Main consumer and normalized consumer each **9/62 passed**. Independent interrupted-write tests include exception and separate process exit before checkpoint, conflict preservation, duplicate schema detection, versioned blueprint upgrade and file recovery.

Blockers: none. Recovery: .foundry/backups and journal support file-only restoration; changed files or corrupt/missing backups stop recovery. Matching manual changes are adopted without granting permission to undo them. Database migrations are listed separately and never executed by Foundry. Next: copied packages without workspace symlinks, clean --no-dev install, cached authenticated HTTP behavior and Composer/runtime upgrade preservation.

## Stage 5 completed

Changes/decisions: portable Docker image runs PHP 8.4.26. Release fixture mounts only `build/`, mirrors runtime packages into its independent vendor directory, and removes Foundry source before `composer install --no-dev`. Separate environment/key/database/cache prefix and explicit operator migration establish the disposable production proof. Config, route and event caches build successfully. No production data or credentials were imported.

Upgrade proof: local release fixture versions 0.1.0 → 0.1.1 exercise actual Composer replacement; these are test labels, not published releases. Application-owned SKU policy and resource hashes survive unchanged. Cached bearer-token HTTP create/read returns 201/200 with the customized SITE- SKU and preserved resource marker. Foundry is absent from installed packages and runtime classes. Evidence: `evidence/production-proof.log`, `runtime-upgrade.log`, `production-upgrade-proof.log`, `production-final-proof.log` and upgrade hash snapshots.

Tests: final independent Pillar **8 tests / 37 assertions**, Catalog **11 / 65**, Foundry **11 / 40**, all passed. Each of the three isolated consumers passes **9 / 62**. Catalog additionally denies malformed permission adapters; Pillar scoped mutations invalidate shared cached reads. Installer gates cover write-free preview, unchanged rerun, customized-file conflicts, abrupt process interruption/resume, deterministic migrations, blueprint upgrade and file-only rollback. Six strict Composer manifest validations, Pint checks and runtime doctor pass. Reference revision/status remain unchanged and the selected source hash manifest verifies successfully.

Blockers: none for this vertical slice. Recovery: reinstall isolated dependencies from lockfiles; Foundry restores only unchanged owned files from verified backups and never rolls back database changes. The only persistent schema execution in this proof was an explicit command against the disposable release database. Database migration execution and any data cutover remain separate operator actions.

Next prerequisites: the first vertical slice is complete. Package publication and deployment to another application's data are outside this workspace acceptance. Wallet, Webhooks and absent starter packages remain deferred; no placeholders were introduced.
