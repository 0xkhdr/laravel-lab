# Pillar / Catalog / Foundry workspace

PHP **8.4**, Laravel **12**, SQLite and a private Redis service. The reference repository is mounted read-only; it supplies no vendor files, credentials or data. This is a local prototype, not a published release.

```sh
docker compose build app
docker compose up -d
docker compose exec -T app composer install --no-interaction
docker compose exec -T app php artisan test --compact
docker compose exec -T -w /workspace/packages/pillar app composer install --no-interaction
docker compose exec -T -w /workspace/packages/pillar app vendor/bin/pest
docker compose exec -T -w /workspace/packages/catalog app composer install --no-interaction
docker compose exec -T -w /workspace/packages/catalog app vendor/bin/pest
docker compose exec -T -w /workspace/packages/foundry app composer install --no-interaction
docker compose exec -T -w /workspace/packages/foundry app vendor/bin/pest
```

For a new checkout, copy `.env.example` to `.env`, create the consumer SQLite file and generate a new application key inside Docker. Existing generated development keys are untracked. Composer cache/home are workspace-private. Set LOCAL_UID/LOCAL_GID when the host user differs from 1000.

- `packages/pillar`: runtime architecture and retained module discovery.
- `packages/catalog`: generic translated Catalog and explicit permission states.
- `packages/foundry`: development-only Catalog installation/scaffolding.
- `consumer`: supplied SKU behavior.
- `consumer-example`: independent vendor/lock/database/cache; uppercase SKU policy.
- `consumer-foundry`: disposable fresh-install acceptance consumer.
- `IMPLEMENTATION.md`: stage decisions, gates and recovery boundaries.
- `evidence/`: source fingerprints, characterization harness and recorded command results.

Foundry interfaces are implemented, limited to `catalog.standard`:

```sh
docker compose exec -T app php artisan foundry:install --dry-run --no-interaction
docker compose exec -T app php artisan foundry:add catalog --dry-run --no-interaction
docker compose exec -T app php artisan foundry:add catalog --no-interaction
docker compose exec -T app composer dump-autoload --no-interaction
docker compose exec -T app php artisan foundry:doctor --no-interaction
```

`install`, `add catalog` and `make-module Catalog` reconcile the same integration, so they do not create parallel modules. Composer installs dependencies; Foundry configures installed packages. Migrations are a **separate operator step** (`php artisan migrate`), never executed by Foundry. Recipes require an application-owned UUID users schema, administrator flag, and Sanctum token schema with UUID morph keys. The consumers provide these migrations; Foundry does not infer or rewrite arbitrary identity schemas.

Source package names/namespace compatibility, public vendor ownership and any production-data migration require separate release work. No Wallet, Webhooks, billing, media, or absent starter capabilities are advertised.
