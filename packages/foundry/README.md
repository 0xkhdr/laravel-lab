# raid/foundry

Development tooling for PHP 8.4 / Laravel 12. Install with Composer as a development dependency. Runtime dependencies are Laravel, Pillar and Composer runtime metadata; Catalog/Sanctum are explicit integration prerequisites, installed separately by the consumer. Generated modules declare their own runtime dependencies.

Implemented interfaces:

- `foundry:install [--module=Catalog] [--dry-run]`
- `foundry:add catalog [--module=Catalog] [--dry-run]`
- `foundry:make-module Catalog [--blueprint=catalog.standard] [--dry-run]`
- `foundry:doctor [--module=Catalog]`
- `foundry:rollback [--module=Catalog]` (files only)

Only Catalog is supported. Module names and configured app-modules paths/namespaces are validated; escaping paths and symlink destinations fail. Existing installed versions, authentication schema prerequisites, syntax, duplicate schema identities and registration anchors are inspected before mutations. Unknown PHP layouts receive a manual registration patch rather than a guessed edit. Composer performs installation/autoload generation; Foundry never runs Composer or migrations.

Plans contain recipe/blueprint versions, prerequisites, exact relative paths, expected/desired SHA-256 hashes, ownership, conflicts, verification and a separate migration report. Customized files are preserved with nonzero results and proposed content for review. The current blueprint v1.1.0 supplies four CRUD adapters, bearer-token auth integration, the SKU hook, user permission adapter and meaningful application tests.

Apply uses an exclusive lock, atomic file replacement and `.foundry/installations.json`. Each stable operation ID records before/desired/completed hashes, template identity and status. A checkpoint follows output verification; a process exit after writing but before its checkpoint leaves a pending record. Retry checks existing output hashes and resumes without duplicate migration identities. Successful identical reruns leave files and journal unchanged.

Backups under `.foundry/backups` restore owned file edits. Recovery preflight refuses customized files and missing/corrupt backups. Rerun of recovery resumes restored operations. This is **not database rollback**. After any separately executed database migration, review database recovery independently; do not remove applied migration history as an upgrade mechanism. Runtime/schema upgrades require new migration identities and reviewed application changes, never timestamp regeneration of an old template.

After an apply or file recovery, run `composer dump-autoload`, clear application caches, then run doctor in a new process. Generated code is application-owned and contains no Foundry imports. Production `composer install --no-dev` must exclude this package. No optional Media/Wallet/Webhooks integrations or generic generator catalog are supplied.

Run `composer install` and `vendor/bin/pest` inside this package's Docker directory. The suite includes write-free preview, conflicts/preflight, custom layouts, stale plans, deterministic migration IDs, interruption/resume, versioned blueprint upgrades and file restoration.
