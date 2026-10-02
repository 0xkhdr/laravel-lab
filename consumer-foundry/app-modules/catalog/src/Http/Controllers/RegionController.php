<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modules\Catalog\Http\Requests\Region\CreateRegionRequest;
use Modules\Catalog\Http\Requests\Region\ListRegionsRequest;
use Modules\Catalog\Http\Requests\Region\UpdateRegionRequest;
use Modules\Catalog\Http\Resources\RegionResource;
use Raid\Catalog\Actions\Region\CreateRegionAction;
use Raid\Catalog\Actions\Region\DeleteRegionAction;
use Raid\Catalog\Actions\Region\FindRegionAction;
use Raid\Catalog\Actions\Region\RetrievePaginateRegionAction;
use Raid\Catalog\Actions\Region\UpdateRegionAction;

/**
 * RegionController
 *
 * Handles the full region lifecycle — list, create, show, update, delete —
 * by delegating each operation to the corresponding Catalog Foundation action.
 *
 * Pattern: thin HTTP adapter. No business logic lives here.
 *
 * @see CreateRegionAction
 * @see UpdateRegionAction
 * @see FindRegionAction
 * @see DeleteRegionAction
 * @see RetrievePaginateRegionAction
 */
class RegionController extends Controller
{
    public function index(ListRegionsRequest $request, RetrievePaginateRegionAction $action): AnonymousResourceCollection
    {
        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 15);

        $regions = $action->handle(perPage: $perPage);

        return RegionResource::collection($regions);
    }

    public function store(CreateRegionRequest $request, CreateRegionAction $action): RegionResource
    {
        $region = $action->handle($request->validated());

        return new RegionResource($region);
    }

    public function show(string $id, FindRegionAction $action): RegionResource
    {
        $region = $action->handle(conditions: ['id' => $id]);

        return new RegionResource($region);
    }

    public function update(UpdateRegionRequest $request, string $id, UpdateRegionAction $action): RegionResource
    {
        $region = $action->handle($id, $request->validated());

        return new RegionResource($region);
    }

    public function destroy(string $id, DeleteRegionAction $action): JsonResponse
    {
        $action->handle(ids: [$id]);

        return response()->json(['message' => 'Region deleted successfully.']);
    }
}
