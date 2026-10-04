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

## Development environment consolidation completed (2026-10-04)

Changes/decisions: `consumer/` is the primary development sandbox. Other consumers remain acceptance fixtures; `build/` remains ignored and disposable. README documents authoritative package sources, Composer symlinks, application ownership, simulated release versions and separate blueprint versions. Added a localhost-only web service sharing the PHP image and sandbox with the CLI service. Default host port is 8088 because 8080 is occupied. Primary environment example and existing local environment use file sessions, synchronous queues and the matching APP_URL, allowing the welcome page to boot without persistent schema execution. Removed automatic migration commands from setup and project-creation hooks in all three consumer manifests. Dependencies retain their existing runtime/development separation.

Validation: Docker image build and Compose configuration passed; running PHP is 8.4.26. Composer install from the primary lockfile and strict validation of all three consumer manifests passed. Primary consumer tests: 9 passed / 62 assertions. Foundry doctor verified files, registration, bindings, authentication and routes with no conflicts. GET http://localhost:8088 returned HTTP 200. Git whitespace check passed. No PHP source changed, so Pint was not required. No persistent migrations executed; behavior tests use their disposable test database.

Recovery: revert the tracked configuration/documentation changes and restore local environment values if needed; stop services with `docker compose down`. Existing application key and persistent database were preserved. Catalog remains the only supported slice.

## Infrastructure review and preparation completed (2026-10-04)

Changes/decisions: added `INFRASTRUCTURE_REVIEW.md` documenting intended Raid/Pillar/domain/module/Foundry ownership, the reference package inventory, missing local registry capabilities, source-backed extraction risks, and a proposed preparation sequence. Identified undeclared TwoFactor/Auth inheritance, stale application idempotency prerequisites, and the need to reconcile shared Composer/provider ownership before a second Foundry recipe. Distinguished the proven Catalog slice from the older module's permission-management HTTP and idempotency features. Recommended Identity as a future domain candidate after Catalog-bounded preparation; no additional packages, recipes or runtime changes were implemented.

Validation: Docker package suites passed: Pillar 8 tests / 37 assertions; Catalog 11 / 65; Foundry 11 / 40. Primary consumer passed 9 / 62. Existing selected reference SHA-256 manifest verified successfully; reference revision/status matched the documented baseline. Other reference package test files were inventoried, not executed; production/upgrade fixtures were not rebuilt. No persistent migrations or reference writes occurred. No PHP source changed, so Pint was not required.

Recovery: remove the review document and this stage entry. Next: review shared-file reconciliation/recovery acceptance cases and Catalog authentication integration ownership before widening scope to another foundation.


## Catalog shared-file and authentication preparation completed (2026-10-04)

Changes/decisions: Catalog shared configuration now records its exact PSR-4 key/value and provider registration rather than treating the complete Composer/provider files as exclusively owned. A Catalog-specific token parser supports literal provider arrays, simple imports/aliases, optional strict types, comments and optional trailing commas without executing application PHP. Absolute new provider names prevent import collisions. Additional providers/autoload mappings and unrelated settings/comments survive reconciliation and file recovery. Generated application files retain their existing ownership/hash conflict rules, including customized SKU policies/resources. No additional recipe, domain package, Identity implementation or generic orchestration engine was added.

Compatibility: journal schema 1, `installations.catalog`, path SHA-256 operation IDs and template identities remain stable. Additive `contribution.version = 1` metadata records ownership and the original output hash. Legacy records infer ownership from verified before-file/backup evidence; missing/corrupt evidence produces a reviewable conflict. Dry-run reads this compatibility information without writing it. Apply checkpoints inferred metadata once; subsequent unchanged reruns leave the journal unchanged. Unsupported journal/contribution versions are rejected. Recovery must use the updated tooling; older Foundry versions do not understand the new contribution boundaries.

Authentication contract: documented in `packages/foundry/README.md`, linked from the workspace README. One application owner supplies/adopts users and token schemas. The application owns its authenticatable model, Laravel/Sanctum guard/provider configuration and Catalog adapter wiring. Endpoint authentication/read-write gates remain separate from row visibility and do not grant implicit unrestricted Catalog access. Missing/malformed integrations deny visible rows; token deletion, permission revocation and inherited/cache revocation must remain effective. Current generated UUID/Sanctum/admin wiring is explicit application code, not a future Identity schema or general authorization engine. Identity remains the recommended future foundation.

Validation: all PHP tooling ran inside Docker with PHP 8.4.26 / Laravel 12. Foundry **24 tests / 137 assertions passed** (13 added focused behaviors); Pillar **8 / 37**, Catalog **11 / 65**, primary consumer **9 / 62** passed. Foundry strict Composer validation and Laravel-preset Pint checks passed. Tests cover later registrations across reconciliation/checkpoint refresh/recovery/reinstall, legacy conversion and direct recovery, unprovable legacy ownership, interrupted shared writes/resume, recovery before its checkpoint, adopted registrations, aliases/comments/duplicates, unsupported metadata/layouts, customized SKU/resources, stale plans and path/symlink rejection. Actual CLI Catalog dry-run and runtime doctor passed; all **9,497 consumer file hashes remained unchanged** across both commands. Selected read-only reference source hashes verified. Git whitespace checks passed. No persistent migrations or reference writes occurred; production/no-dev and upgrade fixtures were not rebuilt.

Recovery boundaries: all outputs preflight under the exclusive lock; destination hashes are rechecked before mutation. Modern shared-file recovery removes only an unchanged owned registration, leaves adopted entries alone and treats already absent entries as safe recovery no-ops. The original verified backup can restore exact formatting only when current bytes still match the original output hash; a refreshed journal hash cannot authorize restoring an older whole-file snapshot. Otherwise recovery patches current content, preserving later registrations/application values. Composer JSON may be reformatted; PHP outside the removed registration stays intact. Modern inverse patches need no backup, while legacy ownership inference and generated-file restoration require verified evidence when the before-file is unavailable. Dynamic/ambiguous PHP, modified Catalog mappings/provider expressions and customized generated files stop with reviewable conflicts before application writes. Checkpointed file recovery can resume; it never rolls back database changes. Clear application caches and refresh Composer autoload explicitly after apply/recovery. Existing user edits to this record and the infrastructure review were preserved.

## Pillar foundation planning completed

Changes/decisions: added `PILLAR_FOUNDATION_PLAN.md` at the project root. The proposed sequence covers public API inventory and a fresh baseline, Action execution, Eloquent repository correctness, cache safety/write ownership, configuration/module integration, and downstream Catalog acceptance. Each stage has an exit gate. Public API changes require compatibility review; no new domain or speculative framework layer is proposed.

Validation: reviewed the plan against the current Pillar README and workspace constraints. This is documentation-only work; no PHP, Composer, Artisan, tests, migrations, or reference writes were executed. Runtime hardening stages remain proposed, not completed.

Recovery: remove the plan and this planning entry. Next: inventory Pillar's public API and establish a fresh Docker baseline before prioritizing implementation fixes.
