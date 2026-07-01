<?php

namespace App\Http\Controllers\v1\api\Inventory;

use App\Http\Controllers\v1\api\BaseApiController;
use App\Http\Controllers\v1\api\Traits\HasTrashedFilter;
use App\Http\Requests\Inventory\StoreInventoryBatchRequest;
use App\Http\Requests\Inventory\UpdateInventoryBatchRequest;
use App\Http\Resources\Inventory\InventoryBatchResource;
use App\Services\Inventory\InventoryBatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryBatchController extends BaseApiController
{
    use HasTrashedFilter;
    protected array $permissions = [
        'index'       => 'inventory.batch.view',
        'store'       => 'inventory.batch.create',
        'show'        => 'inventory.batch.show',
        'update'      => 'inventory.batch.update',
        'destroy'     => 'inventory.batch.delete',
        'restore'     => 'inventory.batch.restore',
        'forceDelete' => 'inventory.batch.forceDelete',
    ];

    public function __construct(protected InventoryBatchService $service)
    {
        parent::__construct();
    }

    public function store(StoreInventoryBatchRequest $request): JsonResponse
    {
        $this->authorizeAction($request);
        $this->enforcePolicy($request, 'inventory');

        // Note: Variant/Org ownership should be validated in the FormRequest
        $batch = $this->service->create($request->validated());

        return $this->created(new InventoryBatchResource($batch));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $batch = $this->service->find($id, $this->getActiveOrgId($request));
        $this->authorizeAction($request, $batch);

        return $this->success(new InventoryBatchResource($batch));
    }

    public function update(UpdateInventoryBatchRequest $request, int $id): JsonResponse
    {
        $orgId = $this->getActiveOrgId($request);
        $batch = $this->service->find($id, $orgId);

        $this->authorizeAction($request, $batch);
        $updated = $this->service->update($batch, $request->validated());

        return $this->success(new InventoryBatchResource($updated));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $orgId = $this->getActiveOrgId($request);
        $batch = $this->service->find($id, $orgId);

        $this->authorizeAction($request, $batch);
        $this->service->delete($batch);

        return $this->deleted('Batch deleted successfully.');
    }

    public function restore(Request $request, int $id): JsonResponse
    {
        // $this->ensureAdmin($request);
        $orgId = $this->getActiveOrgId($request);

        $batch = $this->service->find($id, $orgId, withTrashed: true);
        $this->authorizeAction($request, $batch);

        $this->service->restore($batch);

        return $this->success(['message' => 'Batch restored successfully.']);
    }

    public function forceDelete(Request $request, int $id): JsonResponse
    {
        $this->ensureAdmin($request);
        $orgId = $this->getActiveOrgId($request);

        $batch = $this->service->find($id, $orgId, withTrashed: true);
        $this->authorizeAction($request, $batch);

        $this->service->forceDelete($batch);

        return $this->deleted('Batch permanently deleted.');
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAction($request);

        $filters = $request->only(['search', 'product_variant_id', 'status', 'is_recalled']);
        $this->restrictToContext($request, $filters);

        $trashed = $this->extractTrashedFilter($request);

        $batches = $this->service->paginate($filters, $this->perPage($request), $trashed);

        return $this->success(
            InventoryBatchResource::collection($batches),
            $this->paginationMetadata($batches)
        );
    }

    public function byVariant(Request $request, int $productVariantId): JsonResponse
    {
        $orgId = $this->getActiveOrgId($request);

        $filters = $request->only(['status', 'is_recalled']);
        $filters['product_variant_id'] = $productVariantId;
        $filters['organization_id'] = $orgId;

        $batches = $this->service->paginate($filters, $this->perPage($request));

        return $this->success(
            InventoryBatchResource::collection($batches),
            $this->paginationMetadata($batches)
        );
    }

    public function getExpired(Request $request): JsonResponse
    {
        $this->authorizeAction($request);
        $orgId = $this->getActiveOrgId($request);

        $batches = $this->service->getExpired($orgId);

        return $this->success(InventoryBatchResource::collection($batches));
    }

    public function getExpiring(Request $request): JsonResponse
    {
        $this->authorizeAction($request);
        $orgId = $this->getActiveOrgId($request);

        $days = $request->input('days', 30);
        $batches = $this->service->getExpiring($orgId, (int) $days);

        return $this->success(InventoryBatchResource::collection($batches));
    }
}
