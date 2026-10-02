# raid/catalog

PHP 8.4 / Laravel 12. Direct runtime dependencies: raid/pillar, Laravel framework, Spatie Translatable. No concrete User, Identity, Sanctum or Foundry dependency is required. Consumer authentication and HTTP representation remain application-owned.

Generic Brand, Category, Region and Product behavior is retained, including UUIDs, translated name/description, nullable region and relationships. Product creation injects `Contracts\SkuPolicy::apply(string): string`; the default `Support\SuppliedSku` preserves the supplied value. Bind a consumer policy before or after package registration. Creation validates SKU presence/type/length before the policy and uniqueness/length afterward. This hook governs creation; existing update semantics remain intact.

Mix `Traits\HasCatalogPermissions` into the configured consumer user model. Permission pivots and state storage expect a UUID `users` key. `Actions\Permissions\SetCatalogPermissionStateAction::handle(Model, PermissionState)` selects:

| State | Meaning |
| --- | --- |
| unconfigured / absent | Deny |
| denied | Deny, even with stored grants |
| unrestricted | Explicitly allow all |
| restricted | Brand OR category OR product grant |
| inherited | Resolve the explicit catalogPermissionParent() hook; missing parent or cycles deny |

SyncUserBrand/Category/ProductPermissionsAction retain `handle(Model, array)` and replace that axis atomically. Empty input denies that axis and preserves other grants; sync selects restricted state. Cache invalidation follows commit. Inherited reads resolve the parent's current bundle; cached repository keys include effective permissions, preventing stale grants after revocation. Guests/missing permission adapters fail closed. Region behavior remains generic and is protected by consumer authentication/gates.

Configure `catalog.user_model`, `auth_guard` and `permission_cache_ttl`. No implicit administrator bypass exists in the runtime. The sample administrator is explicitly assigned unrestricted state by the application. Low-level repository/model access is a persistence API; HTTP adapters enforce authorization for mutations.

Schema templates ship in `database/migrations`. Runtime providers do not load or publish them automatically. Package tests load them directly; Foundry emits deterministic module-owned copies with stable identities. Never copy templates into a second schema owner. Namespace renaming/data imports and already-applied schema upgrades are separate reviewed operations.

Run independent tests with `composer install` and `vendor/bin/pest` inside Docker. The two consumers demonstrate supplied versus normalized SKU behavior without editing this package. Product/Brand model override and media integration recipes are deliberately unsupported in this slice.
