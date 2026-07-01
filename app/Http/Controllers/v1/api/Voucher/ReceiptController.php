<?php

namespace App\Http\Controllers\v1\api\Voucher;

use App\Http\Controllers\v1\api\BaseApiController;
use App\Http\Requests\Voucher\StoreReceiptRequest;
use App\Http\Requests\Voucher\UpdateReceiptRequest;
use App\Http\Resources\Voucher\ReceiptResource;
use App\Services\Voucher\ReceiptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReceiptController extends BaseApiController
{
    protected array $permissions = [
        'index' => 'accounts.receipt.view',
        'store' => 'accounts.receipt.create',
        'show' => 'accounts.receipt.show',
        'update' => 'accounts.receipt.update',
        'destroy' => 'accounts.receipt.delete',
        'restore' => 'accounts.receipt.restore',
        'forceDelete' => 'accounts.receipt.forceDelete',
        'post' => 'accounts.receipt.post',
        'cancel' => 'accounts.receipt.cancel',
        'allocate' => 'accounts.receipt.allocate',
    ];

    public function __construct(protected ReceiptService $service)
    {
        parent::__construct();
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAction($request);

        $filters = $request->only([
            'search', 'customer_id', 'status', 'date_from', 'date_to',
        ]);

        $this->restrictToContext($request, $filters);

        $items = $this->service->paginate($filters, $this->perPage($request));

        return $this->success(
            ReceiptResource::collection($items),
            $this->paginationMetadata($items)
        );
    }

    public function store(StoreReceiptRequest $request): JsonResponse
    {
        $this->authorizeAction($request);
        $this->enforcePolicy($request, 'accounts');

        $receipt = $this->service->create($this->getValidatedData($request));

        return $this->created(new ReceiptResource($receipt), ['message' => 'Receipt created successfully.']);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $receipt = $this->service->find($id, $this->getActiveOrgId($request));
        $this->authorizeAction($request, $receipt);

        return $this->success(new ReceiptResource($receipt));
    }

    public function update(UpdateReceiptRequest $request, int $id): JsonResponse
    {
        $orgId = $this->getActiveOrgId($request);
        $receipt = $this->service->find($id, $orgId);

        $this->authorizeAction($request, $receipt);

        $result = $this->service->update($receipt, $request->validated());

        return $this->success(new ReceiptResource($result), ['message' => 'Receipt updated successfully.']);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $orgId = $this->getActiveOrgId($request);
        $receipt = $this->service->find($id, $orgId);

        $this->authorizeAction($request, $receipt);
        $this->service->delete($receipt);

        return $this->deleted('Receipt deleted successfully.');
    }

    public function post(Request $request, int $id): JsonResponse
    {
        $orgId = $this->getActiveOrgId($request);
        $receipt = $this->service->find($id, $orgId);
        $this->authorizeAction($request, $receipt);

        $result = $this->service->post($receipt);

        return $this->success(new ReceiptResource($result), ['message' => 'Receipt posted successfully.']);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $orgId = $this->getActiveOrgId($request);
        $receipt = $this->service->find($id, $orgId);
        $this->authorizeAction($request, $receipt);

        $result = $this->service->cancel($receipt, $request->reason);

        return $this->success(new ReceiptResource($result), ['message' => 'Receipt cancelled.']);
    }

    public function allocate(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'references' => 'required|array',
            'references.*.reference_type' => 'required|string',
            'references.*.reference_id' => 'required|integer',
            'references.*.amount' => 'required|numeric|min:0.01',
        ]);

        $orgId = $this->getActiveOrgId($request);
        $receipt = $this->service->find($id, $orgId);
        $this->authorizeAction($request, $receipt);

        $result = $this->service->allocate($receipt, $request->references);

        return $this->success(new ReceiptResource($result), ['message' => 'Receipt allocated successfully.']);
    }

    public function restore(Request $request, int $id): JsonResponse
    {
        // $this->ensureAdmin($request);
        $orgId = $this->getActiveOrgId($request);
        $receipt = $this->service->find($id, $orgId, true);

        $this->authorizeAction($request, $receipt);
        $this->service->restore($receipt->id);

        return $this->success(['message' => 'Receipt restored successfully.']);
    }

    public function forceDelete(Request $request, int $id): JsonResponse
    {
        $this->ensureAdmin($request);
        $orgId = $this->getActiveOrgId($request);
        $receipt = $this->service->find($id, $orgId, true);

        $this->authorizeAction($request, $receipt);
        $this->service->forceDelete($receipt->id);

        return $this->deleted('Receipt permanently deleted.');
    }
}
