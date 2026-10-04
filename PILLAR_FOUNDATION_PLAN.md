# Pillar foundation audit and hardening plan

Status: proposed work; implementation has not started.

## Goal

Make `raid/pillar` a small, dependable runtime foundation for subsequent Laravel
projects. Establish its supported contracts, find correctness gaps, and improve
the areas that concrete consumers need before expanding the ecosystem.

This is not a promise that an audit can prove the absence of every defect.
Completion means that the supported behavior, limitations, and failure modes are
explicit, backed by focused tests, and verified through Catalog.

## Scope and constraints

- PHP 8.4 and Laravel 12.
- Work on Pillar Actions, Repositories, caching, runtime configuration, and
  retained module integration.
- Use Catalog and the existing consumers as downstream acceptance cases.
- Keep runtime packages independent of Foundry.
- Keep the reference at `/reference` read-only.
- Run Composer, Artisan, PHP tooling, formatters, and tests inside Docker.
- Never execute migrations from tooling. Tests may use disposable test schemas;
  persistent schema execution remains a separate operator action.
- Preserve explicit application ownership of generated code.
- Record each completed implementation stage in `IMPLEMENTATION.md`.
- Do not add another domain, general recipe engine, generic service layer,
  module feature-toggle system, or speculative infrastructure.

## Current baseline

The package already provides:

- Container-resolved `exec(...) -> execute(...) -> handle(...)` action execution,
  direct typed `handle()` calls, and no implicit action transaction.
- Eloquent-oriented repository contracts, retrieval helpers, and query options.
- Model-connection transactions and distinct model/bulk mutation semantics.
- Cache decorators with query/user variants, table-tag invalidation, cache
  bypass rules, and after-commit invalidation.
- The retained `repository-cache` and `app-modules` configuration keys.
- A provider that merges configuration; InterNACHI supplies module discovery.

Sources: `packages/pillar/README.md`, package source/tests, and
`IMPLEMENTATION.md`. Recorded test counts are historical evidence; establish a
fresh baseline before changing runtime behavior.

## Recommended sequence

### Stage 1 — Inventory and fresh baseline

First inspect applicable package instructions, source, manifests, configuration,
tests, and Catalog call sites. Do not start with a rewrite.

Create a contract inventory covering every public class, interface, method, and
configuration option. For each entry, record:

| Field | Required evidence |
| --- | --- |
| Purpose | Concrete package or consumer use, or retained compatibility reason |
| Contract | Inputs, outputs, defaults, lifecycle, exceptions, and side effects |
| Dependencies | Laravel services, optional packages, cache stores, and connections |
| Coverage | Existing behavior tests and missing cases |
| Disposition | Keep, clarify, fix, or propose deprecation |

Run the existing Pillar and Catalog suites in Docker. Capture environment
versions and results. Separate confirmed defects from design choices and
unsupported use cases.

**Exit gate:** a complete inventory, reproducible baseline, and prioritized
findings with source references. No API removal or expansion merely because a
method has no Catalog call site.

### Stage 2 — Action execution contract

Review and test:

- Container resolution, constructor injection, and parameter forwarding.
- The behavior of `exec`, `execute`, and direct `handle` calls.
- Typed returns, missing handlers, and unchanged domain exception propagation.
- Any lifecycle hooks or mutable state, including repeated execution.
- Action composition and explicit transaction ownership.

Recommend a normal entry point while explaining any supported alternatives.
Preserve the existing execution chain unless a demonstrated problem justifies
a reviewed compatibility change. Do not wrap every action in a transaction.

**Exit gate:** entry points have predictable, documented semantics and focused
tests. Catalog actions continue to work without additional wrapper layers.

### Stage 3 — Repository contract and correctness

Position repositories as Eloquent-oriented infrastructure, not storage-neutral
ports. Inspect the complete API, but prioritize correctness over adding methods.

Review and test:

- Fresh query state between calls, cloned builders, and option leakage.
- CRUD, retrieval, pagination, not-found behavior, and empty results.
- Model-based versus bulk updates/deletes, including casts and Eloquent events.
- Soft deletes, restore/force-delete behavior where supported, and scope behavior.
- Eager loading, sorting, grouping, joins, filters, and custom builders.
- Model-connection transactions, rollback, nested transaction behavior, and
  exception propagation.
- Binding/substitution of concrete repository implementations.
- Optional EloquentFilter installation and the failure mode when it is absent.

Document which operations bypass model lifecycle. Query options are trusted
application inputs: request adapters must validate and allowlist them rather
than forwarding arbitrary HTTP input.

For broad inherited APIs, choose explicitly between supported behavior,
documented limitations, and a future deprecation. Avoid silent removals and
avoid duplicating every Eloquent capability without a consumer need.

**Exit gate:** supported repository operations have explicit semantics and tests,
with no query-state leakage or unintended connection/lifecycle behavior.

### Stage 4 — Cache safety and write ownership

Audit key construction, cache controls, decorator forwarding, and invalidation
as one coherent contract.

Review and test using the real workspace Redis service with isolated prefixes:

- Equivalent/different query options produce the intended cache identity.
- User, permission, pagination, and repository variants do not share wrong data.
- Mutations invalidate every affected variant within the cache namespace.
- Create, update, delete, and supported bulk operations invalidate appropriately.
- Commit schedules the required invalidation; rollback does not publish changes.
- Active transactions, custom builders, unsupported stores, and testing bypass
  do not serve unsafe cached values.
- Per-call cache controls reset correctly and do not leak into later calls.
- TTL and configuration defaults have defined behavior.
- Database connections cannot collide in cache identity where supported.
- Cache/backend failures have an intentional, tested failure policy.

Investigate whether an in-flight read can repopulate stale data after
invalidation. Either establish the safety mechanism or document the consistency
limit; do not claim concurrency guarantees from sequential tests.

Make mutation ownership explicit. Direct Eloquent/query-builder writes currently
fall outside decorator invalidation. Prefer documenting and using the existing
supported write paths. Add a public invalidation hook only if a concrete Catalog
workflow requires it and its scope can be made clear.

**Exit gate:** real-cache evidence proves isolation and invalidation, with
transaction, direct-write, and concurrency limits stated accurately.

### Stage 5 — Configuration and module integration

Define the boundary between Pillar, Laravel, InterNACHI, application registration,
and Foundry.

Review and test:

- Provider discovery and configuration merging.
- Application overrides and relevant provider ordering.
- Config caching and module/provider boot behavior.
- Composer autoload and application-provider registration responsibilities.
- Supported module directory/namespace configuration.
- Duplicate registration prevention in the supported integration workflow.
- Operation without Foundry installed or loaded.

Do not introduce a replacement module-discovery engine or runtime
enable/disable system. Clarify the retained integration before expanding it.

**Exit gate:** a developer can identify who discovers, autoloads, registers, and
boots a module, and the supported workflow behaves consistently with caches.

### Stage 6 — Downstream acceptance and contract documentation

Run the relevant package and consumer suites after hardening:

1. Pillar behavior tests, including actual Redis checks.
2. Catalog behavior tests, especially permission-sensitive cached reads and
   grant/inherited-grant revocation.
3. Primary consumer HTTP tests.
4. Additional existing consumer variants when a changed contract affects them.
5. Foundry tests when registration or generated integration assumptions change.

Verify strict Composer validation and Laravel-preset Pint inside Docker.
Confirm runtime independence from Foundry. If dependency/autoload/module changes
affect production installation, rerun the existing disposable no-dev proof
without adding automated persistent migration execution.

Update `packages/pillar/README.md` with:

- Supported public contracts and recommended usage.
- Transaction and model-lifecycle ownership.
- Cache consistency and invalidation boundaries.
- Trusted query-option inputs.
- Optional dependencies and unsupported cases.
- Module registration responsibilities.
- Compatibility notes for any changed behavior.

Record decisions, actual validation results, and recovery boundaries in
`IMPLEMENTATION.md`.

**Exit gate:** package and downstream evidence agree with the documented public
contract; no generated application customization is overwritten.

## Findings and decision policy

Prioritize findings in this order:

1. **Correctness/security:** wrong rows, cross-user cache reuse, stale revocation,
   wrong connection, data loss, or failed rollback.
2. **Contract ambiguity:** inconsistent mutation semantics, leaked state,
   undocumented bypasses, or misleading dependency/configuration behavior.
3. **Maintainability:** redundant paths, unnecessary indirection, and unclear
   implementation boundaries.
4. **Convenience:** new helpers and ergonomic features backed by actual use.

For each proposed change, state the failing scenario, competing approaches,
selected fix, compatibility impact, and verifying tests. Preserve established
behavior unless there is evidence to change it. Ask for a decision before
breaking a public API or adding a consequential dependency.

## Definition of done

- All public Pillar capabilities are inventoried and classified.
- Confirmed high-priority defects are fixed or explicitly blocked with evidence.
- Actions, repositories, cache behavior, and module integration have focused
  behavior coverage and documented limitations.
- Real Redis checks and downstream Catalog/consumer tests pass.
- Formatting and manifest validation pass inside Docker.
- Runtime packages and generated modules remain independent of Foundry.
- No new domain or speculative framework layer is introduced.
- Documentation and `IMPLEMENTATION.md` reflect actual completed work.
- Remaining risks have concrete reproduction/acceptance criteria rather than
  an unsupported claim that the foundation has no possible gaps.

## First implementation task

Start with Stage 1: inventory Pillar's public API and establish a fresh Docker
baseline. Present the prioritized findings before undertaking broad refactoring.
