<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modules\Catalog\Http\Requests\Brand\CreateBrandRequest;
use Modules\Catalog\Http\Requests\Brand\ListBrandsRequest;
use Modules\Catalog\Http\Requests\Brand\UpdateBrandRequest;
use Modules\Catalog\Http\Resources\BrandResource;
use Raid\Catalog\Actions\Brand\CreateBrandAction;
use Raid\Catalog\Actions\Brand\DeleteBrandAction;
use Raid\Catalog\Actions\Brand\FindBrandAction;
use Raid\Catalog\Actions\Brand\RetrievePaginateBrandAction;
use Raid\Catalog\Actions\Brand\UpdateBrandAction;

/**
 * BrandController
 *
 * Handles the full brand lifecycle — list, create, show, update, and delete —
 * by delegating each operation to the corresponding Catalog Foundation action.
 *
 * Pattern: thin HTTP adapter — every method validates input, calls exactly one
 * action, and returns a JSON response. No business logic lives here.
 *
 * ── Endpoints ────────────────────────────────────────────────────────────────
 *
 *   GET    /brands         → index()
 *   POST   /brands         → store()
 *   GET    /brands/{id}    → show()
 *   PATCH  /brands/{id}    → update()
 *   DELETE /brands/{id}    → destroy()
 *
 * ── Access control ───────────────────────────────────────────────────────────
 *
 *   RetrievePaginateBrandAction and FindBrandAction apply the `accessibleByUser`
 *   scope automatically. Admin users see all brands; non-admins are filtered to
 *   brands permitted by their UserBrandPermission rows (union model).
 *
 * @see CreateBrandAction
 * @see UpdateBrandAction
 * @see FindBrandAction
 * @see DeleteBrandAction
 * @see RetrievePaginateBrandAction
 */
class BrandController extends Controller
{
    /**
     * List brands with optional pagination.
     *
     * The `accessibleByUser` scope is applied inside the action — admin users
     * receive all brands; non-admin users receive only their permitted brands.
     */
    public function index(ListBrandsRequest $request, RetrievePaginateBrandAction $action): AnonymousResourceCollection
    {
        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 15);

        $brands = $action->handle(perPage: $perPage);

        return BrandResource::collection($brands);
    }

    /**
     * Create a new brand.
     */
    public function store(CreateBrandRequest $request, CreateBrandAction $action): BrandResource
    {
        $brand = $action->handle($request->validated());

        return new BrandResource($brand);
    }

    /**
     * Retrieve a single brand by UUID.
     *
     * Throws ModelNotFoundException (→ 404) when not found or not accessible.
     */
    public function show(string $id, FindBrandAction $action): BrandResource
    {
        $brand = $action->handle(conditions: ['id' => $id]);

        return new BrandResource($brand);
    }

    /**
     * Update an existing brand.
     *
     * Only the fields present in the validated payload are written — absent
     * fields remain unchanged.
     */
    public function update(UpdateBrandRequest $request, string $id, UpdateBrandAction $action): BrandResource
    {
        $brand = $action->handle($id, $request->validated());

        return new BrandResource($brand);
    }

    /**
     * Delete a brand by UUID.
     *
     * Throws ValidationException when the brand still has associated products.
     */
    public function destroy(string $id, DeleteBrandAction $action): JsonResponse
    {
        $action->handle(ids: [$id]);

        return response()->json(['message' => 'Brand deleted successfully.']);
    }
}
