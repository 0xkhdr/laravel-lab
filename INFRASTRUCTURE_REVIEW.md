# Infrastructure review and preparation

Reviewed 2026-10-04. Reference: `digital-cards-distribution`, branch `foundation`, revision `6a2cf9816b7f0fd64ba8aa26192ae00352e9e77f`. Its planning artifacts are untracked; findings concern the checkout, not just the commit. The reference remained read-only. This stage prepares expansion; it implements no additional domain or infrastructure package. Catalog remains the supported slice.

## Intended product

You are building the **Raid Laravel ecosystem**: reusable runtime foundations that supply working behavior, application modules that customize them, and development tooling that assembles those modules safely. The digital-card application supplied the original use cases and became the first ecosystem workspace. `laravel-lab` proves that the extracted pieces can work in independent Laravel applications.

Evidence: the reference [workflow proposal](../digital-cards-distribution/pillar-foundry-workflow-plan.md), [architecture mapping](../digital-cards-distribution/artifacts/03-target-architecture.md), root [Composer manifest](../digital-cards-distribution/composer.json), and this workspace's [implementation record](IMPLEMENTATION.md).

The ownership boundary is:

| Layer | Owns |
| --- | --- |
| Laravel | Container, Eloquent, HTTP, queues, events, framework lifecycle |
| Pillar | Action execution, consumed repository/cache behavior, retained module conventions |
| Domain/support foundations | Portable behavior, contracts, models, schema templates, runtime maintenance |
| Application modules | Routes, requests/resources, authorization, concrete user integration, project rules |
| Foundry | Development-time planning, generation, registration, ownership journal and file recovery |

Runtime dependencies point toward Laravel/Pillar, never toward Foundry. An application developer installs dependencies with Composer, previews a recipe, generates integration, customizes owned code, refreshes autoload, and verifies it. Database execution remains an explicit operator action. Package authors prove a capability in isolation and in a consumer before advertising its recipe.

## What is already proven here

`raid/pillar`, `raid/catalog`, and `raid/foundry` exist locally. Catalog has translated CRUD, explicit permission states, permission-sensitive cache behavior, and a SKU customization contract. Foundry supplies the Catalog blueprint, conflict detection, deterministic migration identities, interruption recovery and file-only rollback. Application-owned HTTP adapters use Sanctum and explicit gates.

Fresh Docker checks in this review:

| Harness | Tests | Assertions |
| --- | ---: | ---: |
| Pillar | 8 passed | 37 |
| Catalog | 11 passed | 65 |
| Foundry | 11 passed | 40 |
| Primary consumer | 9 passed | 62 |

The existing selected reference SHA-256 manifest also verified successfully. Production/no-dev and upgrade proofs are **historical evidence** in IMPLEMENTATION.md; they were not rebuilt in this review. No persistent migration command was executed. Behavior tests use disposable schemas.

This is architectural proof, not full feature parity with the older app. The old Catalog module additionally exposes permission-management HTTP endpoints and uses fine-grained Spatie permissions, user-type middleware and idempotency. The new module has four CRUD adapters and simpler read/write gates; permission actions exist in the runtime, but no corresponding management HTTP adapter is generated. Preserve this distinction when planning an Identity integration.

## Package inventory and preparation order

The reference has **15 local packages**: four Frontier and eleven Raid. Nine runtime Raid packages besides Catalog need later assessment/migration; starter tooling is absorbed into Foundry. Suggested target names below are planning identifiers, not published packages or newly supported recipes. Package-name availability has not been checked.

| Existing source under `pkgs/raid/` | Suggested destination | Preparation required |
| --- | --- | --- |
| `laravel-auth-foundation` | `raid/identity` | First ecosystem dependency candidate: users, RBAC and token lifecycle. Preserve configurable user/guard integration; resolve schema ownership with existing consumer users/Sanctum tables. Prove token revocation and Catalog authorization together. |
| `laravel-audit-trail` | `raid/audit-trail` | First supporting capability candidate. Characterize actor/entity references, queued recording, transaction rollback, redaction and pruning. Catalog mutations offer a concrete consumer case. |
| `laravel-typed-settings` | `raid/typed-settings` | Independent support package. Prove typed values, encrypted storage, cache invalidation and defaults in an isolated consumer. Do not make Catalog depend on it merely to expose configuration. |
| `laravel-media-uploads` | `raid/media-uploads` | Catalog extension candidate after model wiring is supported. Prove upload ownership, validation, attach cleanup and the optional Spatie path. |
| `laravel-notifications-hub` | `raid/notifications` | Prove a differently named notifiable/user model, queued delivery, preferences and ownership checks. Keep channels as explicit integrations. |
| `laravel-two-factor` | `raid/two-factor` | After Identity dependency policy is decided. Prove challenge expiry, attempt limits, replay prevention and user adapter wiring. |
| `laravel-passport-clients` | `raid/passport-clients` | Only for a concrete machine-client use case. Distinguish client credentials from human Sanctum tokens and decide how a machine principal receives Catalog access. |
| `laravel-wallet` | `raid/wallet` | Later financial gate: exact amounts, atomic ledger/balance writes, duplicate references, concurrency and transaction ownership. This is a wallet primitive, not complete Billing. |
| `laravel-webhooks` | `raid/webhooks` | Later delivery gate: duplicate jobs, retries, crash recovery, dead letters and optional circuit breaker binding. Requires a real worker proof. |

Frontier Action/Repository/Modular runtime is already consolidated in Pillar to the extent consumed by Catalog. Frontier's installer and Raid's starter kit are workflow references for Foundry. Their unused generators/helpers are not automatically part of Pillar's supported API.

The starter [registry](../digital-cards-distribution/pkgs/raid/laravel-starter-kit/src/Enums/RaidPackage.php) also lists **six capabilities without matching local package directories**: circuit breaker, idempotency, order foundation, refund foundation, invoice foundation, and outbound logger. Remote existence was not investigated. Order/refund/invoice are future business domains, not prerequisites for completing infrastructure. Do not create placeholder packages or advertise installation commands for them.

Shared application code—JSON responses, exception rendering, locale, security headers, metrics, health checks and idempotency—also deserves an ownership decision. It is currently application infrastructure, not proof that another umbrella runtime package is needed. Retain native Laravel/application integration until a second real consumer establishes a reusable contract.

## Review findings that affect extraction

1. **High: root dependencies conceal incomplete package contracts.** Raid runtime actions import `Frontier\Actions\BaseAction`, while manifests require `frontier/repository` without `frontier/action`. Each migrated package must explicitly require Pillar. Additionally, [SendTwoFactorCodeAction](../digital-cards-distribution/pkgs/raid/laravel-two-factor/src/Actions/SendTwoFactorCodeAction.php:44) extends an Auth Foundation class, but [TwoFactor's manifest](../digital-cards-distribution/pkgs/raid/laravel-two-factor/composer.json) only suggests that package. Loading that action without Auth cannot work. Either declare the dependency or isolate the Auth adapter; Composer suggestions do not satisfy inheritance dependencies.

2. **High: the old Catalog scope can fail open.** [Product](../digital-cards-distribution/pkgs/raid/laravel-catalog-foundation/src/Models/Product.php:97) returns an unfiltered query for guests, missing permission methods, and all-null permission sets. The application [User](../digital-cards-distribution/app-modules/users/src/Models/User.php) lacks the Catalog permission trait. The lab's explicit states and denial behavior address this; future Identity wiring must preserve them and separately enforce mutation authorization.

3. **High: advertised idempotency is incomplete in this checkout.** The [middleware](../digital-cards-distribution/app/Http/Middleware/IdempotencyMiddleware.php:31) calls `getMerchant()` on the authenticated user, then references `App\Models\IdempotencyRequest`. Neither that model nor a matching method on the current user hierarchy was found, and no idempotency migration was found in the root database migrations. Requests without the header bypass this branch, so ordinary CRUD tests miss it. Even after restoring prerequisites, the lookup does not filter `expires_at`, and the code does not recheck persisted responses after acquiring its ten-second lock. A future implementation must prove scope, expiry, payload conflicts, concurrent replay and crash handling through Catalog requests.

4. **High: old installers are unsuitable for direct reuse.** The [starter installer](../digital-cards-distribution/pkgs/raid/laravel-starter-kit/src/Console/Commands/InstallStarterKitCommand.php:731) can execute forced migrations. [FileModifier](../digital-cards-distribution/pkgs/raid/laravel-starter-kit/src/Integration/Support/FileModifier.php:152) passes a Closure to `preg_replace` instead of using `preg_replace_callback`; absent files/anchors can silently skip other edits while the runner records an integration as applied. Port intended integration effects through Foundry's verified plan, not these execution paths.

5. **Medium: media integration has no supported Catalog model path.** The old [handler](../digital-cards-distribution/pkgs/raid/laravel-catalog-foundation/src/Integration/Handlers/MediaLibraryIntegrationHandler.php) targets module Brand/Product subclasses and returns when missing. The current lab explicitly does not support model overrides/media recipes. A future recipe must prove repository construction, relationships, factories and serialization use the intended model, rather than merely adding traits to unused subclasses.

6. **Medium: portability and override contracts need independent checks.** [NotificationRepositoryEloquent](../digital-cards-distribution/pkgs/raid/laravel-notifications-hub/src/Repositories/NotificationRepositoryEloquent.php) imports `App\Models\User` as a fallback. Webhooks [unconditionally binds](../digital-cards-distribution/pkgs/raid/laravel-webhooks/src/WebhooksServiceProvider.php:118) its NullCircuitBreaker; this is a default adapter, not a functioning breaker. Test custom model names and custom bindings in both provider orders. Optional adapter imports need an explicit installation contract.

7. **High readiness gap: Wallet/Webhooks have no package `*Test.php` files.** Wallet's [TransactionData](../digital-cards-distribution/pkgs/raid/laravel-wallet/src/DataObjects/TransactionData.php) accepts float amounts; [balance adjustment](../digital-cards-distribution/pkgs/raid/laravel-wallet/src/Actions/AdjustWalletBalanceAction.php) converts BCMath results back to float, and its manifest omits `ext-bcmath`. Joining an outer transaction also means return does not imply commit. Webhooks has queued state transitions but no independent delivery/crash proof. Source existence is insufficient acceptance for either package.

## Preparation needed before a second recipe

Foundry's [command](packages/foundry/src/Commands/FoundryCommand.php) deliberately accepts only Catalog. Its [installer](packages/foundry/src/Installers/CatalogInstaller.php:34) combines Catalog planning and runtime checks with file execution and uses `installations.catalog` throughout. That is appropriate for the delivered slice, but not a general recipe registry.

The most consequential expansion issue is shared files: Catalog records whole-file hashes and backups for `composer.json` and `bootstrap/providers.php`. A second recipe's legitimate edits would be treated as customized Catalog configuration; restoring an earlier whole-file backup could also remove the second recipe's registration. Before expansion, specify and test shared-file ownership, reconciliation and rollback. Preserve unrelated application edits and other recipe registrations; never solve this by weakening hash/conflict checks globally.

Prepare these Catalog-bounded acceptance cases before implementing a second domain:

- Catalog reconciliation accepts a legitimate additional provider/autoload mapping while preserving custom SKU/resource code.
- Recovery removes only the selected recipe's unchanged contribution, or stops with a reviewable conflict; it preserves later registrations and application edits.
- Installation records and stable operation identities distinguish recipes without changing existing Catalog identities.
- User/auth schema requirements are explicit and have one owner; Identity cannot generate a second users/token schema over the Catalog consumer.
- Read/write authorization and row visibility remain separate; missing integrations deny, and revoked tokens/grants stop working.

Extract the existing file/journal mechanics only when that second concrete plan requires reuse. Avoid adding a generic resolver, plugin engine, domain base classes or broad generator catalog in advance.

## Recommended next work

Within the present Catalog scope, the next implementation stage should address shared-file ownership and specify the authentication/authorization integration contract, backed by focused Catalog/Foundry behavior tests. Finish those gates before expanding supported recipes.

After scope is widened, **Identity is the recommended next domain foundation**, with Catalog as its first integration consumer. Audit Trail is a suitable subsequent support capability. Settings can progress independently when required; media needs Catalog model wiring first; 2FA follows the Identity decision. Passport, notifications, Wallet and Webhooks should follow concrete use cases and their dedicated gates rather than a registry completion target.

For each selected package, require: complete direct dependencies; runtime/tooling separation; independent Testbench harness; two demonstrated application configurations where customization is promised; deterministic schema ownership; write-free preview; repeat/conflict/interruption/recovery checks; cached consumer behavior; copied `--no-dev` runtime proof; and preservation of application-owned code across upgrade. New PHP uses strict types and Laravel-preset Pint; all PHP tooling runs in Docker. Public names, version constraints, external-consumer compatibility and serialized namespace/data transitions remain separate release decisions.
