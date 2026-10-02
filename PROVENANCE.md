# Selected source provenance

Read-only reference: `/var/www/html/rai/up/digital-cards-distribution`, revision `6a2cf9816b7f0fd64ba8aa26192ae00352e9e77f`. Revision/status/diff and selected file hashes are recorded in `evidence/`. The original manifests identify Frontier runtime packages as MIT, author Mohamed Khedr; Catalog as MIT, author RAID Foundation. Those author/license entries are retained in the new runtime manifests. No standalone LICENSE file was supplied by the selected source directories; no existing license/copyright notice was deleted.

- Pillar runtime derives from `pkgs/frontier/action`, `pkgs/frontier/repository`, and the config wrapper in `pkgs/frontier/modular`. Installer/generator directories were excluded.
- Catalog derives from `pkgs/raid/laravel-catalog-foundation` Actions, Models, Repositories, Traits, Scopes, schema and factories. Namespace/config changes, SKU customization, explicit permission states and cache fixes are local changes. Console/Integration/stub tooling was excluded from runtime.
- Consumer HTTP adapters derive from `app-modules/catalog` requests/controllers/resources, using package actions directly rather than generating empty action shells. Their application module metadata retains proprietary ownership. Foundry's versioned blueprint reflects the verified adapters with explicit application authentication/gates, supported SKU/User hooks and deterministic schema templates from the installed Catalog package.
- Foundry's planner/journal/atomic writer and safety tests are new implementation; old unchecked subprocess installers and silent editors were not copied.

Public package names, external consumer compatibility and distribution licensing/ownership must be finalized before publication. Nothing in this workspace publishes packages or changes a deployed consumer.
