<?php

namespace App\Http\Controllers\v1\api\Inventory;

use App\Exceptions\NotFoundException;
use App\Http\Controllers\v1\api\BaseApiController;
use App\Http\Controllers\v1\api\Traits\HasTrashedFilter;
use App\Http\Requests\Inventory\StoreStockLocationRequest;
use App\Http\Requests\Inventory\UpdateStockLocationRequest;
use App\Http\Resources\Inventory\StockLocationResource;
use App\Services\Inventory\StockLocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockLocationController extends BaseApiController
{
    use HasTrashedFilter;
    protected array $permissions = [
        'index'       => 'inventory.stockLocation.view',
        'store'       => 'inventory.stockLocation.create',
        'show'        => 'inventory.stockLocation.show',
        'update'      => 'inventory.stockLocation.update',
        'destroy'     => 'inventory.stockLocation.delete',
        'restore'     => 'inventory.stockLocation.restore',
        'forceDelete' => 'inventory.stockLocation.forceDelete',
    ];

    public function __construct(
        protected StockLocationService $service,
    ) {
        parent::__construct();
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAction($request);

        $filters = $request->only(['search', 'is_active']);
        $this->restrictToContext($request, $filters);

        $trashed = $this->extractTrashedFilter($request);

        $locations = $this->service->paginate(
            $this->getActiveOrgId($request),
            $filters,
            $this->perPage($request),
            $trashed
        );

        return $this->success(
            StockLocationResource::collection($locations->items()),
            $this->paginationMetadata($locations)
        );
    }

    public function store(StoreStockLocationRequest $request): JsonResponse
    {
        $this->authorizeAction($request);
        $this->enforcePolicy($request, 'inventory');

        $location = $this->service->create($request->validated());

        return $this->created(new StockLocationResource($location));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $location = $this->service->find(
            $this->getActiveOrgId($request),
            $id
        ) ?? throw new NotFoundException('Stock Location not found.');

        $this->authorizeAction($request, $location);

        return $this->success(new StockLocationResource($location));
    }

    public function update(UpdateStockLocationRequest $request, int $id): JsonResponse
    {
        $location = $this->service->find(
            $this->getActiveOrgId($request),
            $id
        ) ?? throw new NotFoundException('Stock Location not found.');

        $this->authorizeAction($request, $location);

        $updated = $this->service->update($location, $request->validated());

        return $this->success(new StockLocationResource($updated));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $location = $this->service->find(
            $this->getActiveOrgId($request),
            $id
        ) ?? throw new NotFoundException('Stock Location not found.');

        $this->authorizeAction($request, $location);

        $this->service->delete($location);

        return $this->deleted('Stock Location deleted successfully.');
    }

    public function restore(Request $request, int $id): JsonResponse
    {
        // $this->ensureAdmin($request);
        $location = $this->service->findWithTrashed(
            $this->getActiveOrgId($request),
            $id
        ) ?? throw new NotFoundException('Stock Location not found.');

        $this->authorizeAction($request, $location);

        $this->service->restore($location);

        return $this->success(['message' => 'Stock Location restored successfully.']);
    }

    public function forceDelete(Request $request, int $id): JsonResponse
    {
        $this->ensureAdmin($request);

        $location = $this->service->findWithTrashed(
            $this->getActiveOrgId($request),
            $id
        ) ?? throw new NotFoundException('Stock Location not found.');

        $this->authorizeAction($request, $location);

        $this->service->forceDelete($location);

        return $this->deleted('Stock Location permanently deleted.');
    }
}
