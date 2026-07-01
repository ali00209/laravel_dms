<?php

namespace App\Http\Controllers\v1\api\Voucher;

use App\Http\Controllers\v1\api\BaseApiController;
use App\Http\Requests\Voucher\StorePurchaseOrderRequest;
use App\Http\Requests\Voucher\UpdatePurchaseOrderRequest;
use App\Http\Resources\Voucher\PurchaseOrderResource;
use App\Services\Voucher\PurchaseOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseOrderController extends BaseApiController
{
    protected array $permissions = [
        'index' => 'purchase_order.view',
        'store' => 'purchase_order.create',
        'show' => 'purchase_order.show',
        'update' => 'purchase_order.update',
        'destroy' => 'purchase_order.delete',
        'restore' => 'purchase_order.restore',
        'forceDelete' => 'purchase_order.forceDelete',
        'submit' => 'purchase_order.submit',
        'review' => 'purchase_order.review',
        'approve' => 'purchase_order.approve',
        'reject' => 'purchase_order.reject',
        'cancel' => 'purchase_order.cancel',
    ];

    public function __construct(protected PurchaseOrderService $service)
    {
        parent::__construct();
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAction($request);

        $filters = $request->only([
            'search', 'organization_id', 'supplier_id', 'status', 'date_from', 'date_to',
        ]);

        $this->restrictToContext($request, $filters);

        $items = $this->service->paginate($filters, $this->perPage($request));

        return $this->success(
            PurchaseOrderResource::collection($items),
            $this->paginationMetadata($items)
        );
    }

    public function store(StorePurchaseOrderRequest $request): JsonResponse
    {
        $this->authorizeAction($request);

        $this->service->create($this->getValidatedData($request));

        return $this->created('Purchase Order created successfully.');
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $purchaseOrder = $this->service->find($id, $this->getActiveOrgId($request));
        $this->authorizeAction($request, $purchaseOrder);

        return $this->success(new PurchaseOrderResource($purchaseOrder));
    }

    public function update(UpdatePurchaseOrderRequest $request, int $id): JsonResponse
    {
        $orgId = $this->getActiveOrgId($request);
        $purchaseOrder = $this->service->find($id, $orgId);

        $this->authorizeAction($request, $purchaseOrder);

        $action = $request->input('action');

        $result = match($action) {
            'submit' => $this->service->submitForApproval($purchaseOrder, $request->input('note')),
            'review' => $this->service->review($purchaseOrder, $request->input('note')),
            'approve' => $this->service->approve($purchaseOrder, $request->input('note')),
            'reject' => $this->service->reject($purchaseOrder, $request->input('reason'), $request->input('note')),
            'cancel' => $this->service->cancel($purchaseOrder, $request->input('reason')),
            default => $this->service->update($purchaseOrder, $request->validated()),
        };

        $message = $this->getWorkflowMessage($action);

        return $this->success([new PurchaseOrderResource($result), $message]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $orgId = $this->getActiveOrgId($request);
        $purchaseOrder = $this->service->find($id, $orgId);

        $this->authorizeAction($request, $purchaseOrder);
        $this->service->delete($purchaseOrder);

        return $this->deleted('Purchase Order deleted successfully.');
    }

    public function submit(Request $request, int $id): JsonResponse
    {
        $orgId = $this->getActiveOrgId($request);
        $purchaseOrder = $this->service->find($id, $orgId);
        $this->authorizeAction($request, $purchaseOrder);

        $result = $this->service->submitForApproval($purchaseOrder, $request->input('note'));

        return $this->success([new PurchaseOrderResource($result), 'Purchase Order submitted for approval.']);
    }

    public function review(Request $request, int $id): JsonResponse
    {
        $orgId = $this->getActiveOrgId($request);
        $purchaseOrder = $this->service->find($id, $orgId);
        $this->authorizeAction($request, $purchaseOrder);

        $result = $this->service->review($purchaseOrder, $request->input('note'));

        return $this->success([new PurchaseOrderResource($result), 'Purchase Order reviewed successfully.']);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $orgId = $this->getActiveOrgId($request);
        $purchaseOrder = $this->service->find($id, $orgId);
        $this->authorizeAction($request, $purchaseOrder);

        $result = $this->service->approve($purchaseOrder, $request->input('note'));

        return $this->success([new PurchaseOrderResource($result), 'Purchase Order approved successfully.']);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
            'note' => 'nullable|string|max:500',
        ]);

        $orgId = $this->getActiveOrgId($request);
        $purchaseOrder = $this->service->find($id, $orgId);
        $this->authorizeAction($request, $purchaseOrder);

        $result = $this->service->reject($purchaseOrder, $request->reason, $request->note);

        return $this->success([new PurchaseOrderResource($result), 'Purchase Order rejected.']);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $orgId = $this->getActiveOrgId($request);
        $purchaseOrder = $this->service->find($id, $orgId);
        $this->authorizeAction($request, $purchaseOrder);

        $result = $this->service->cancel($purchaseOrder, $request->reason);

        return $this->success([new PurchaseOrderResource($result), 'Purchase Order cancelled.']);
    }

    public function restore(Request $request, int $id): JsonResponse
    {
        // $this->ensureAdmin($request);
        $purchaseOrder = $this->service->find($id, $this->getActiveOrgId($request), true);

        $this->authorizeAction($request, $purchaseOrder);
        $this->service->restore($purchaseOrder);

        return $this->success(['message' => 'Purchase Order restored successfully.']);
    }

    public function forceDelete(Request $request, int $id): JsonResponse
    {
        $this->ensureAdmin($request);
        $purchaseOrder = $this->service->find($id, $this->getActiveOrgId($request), true);

        $this->authorizeAction($request, $purchaseOrder);
        $this->service->forceDelete($purchaseOrder);

        return $this->deleted('Purchase Order permanently deleted.');
    }

    protected function getWorkflowMessage(?string $action): string
    {
        return match($action) {
            'submit' => 'Purchase Order submitted for approval.',
            'review' => 'Purchase Order reviewed successfully.',
            'approve' => 'Purchase Order approved successfully.',
            'reject' => 'Purchase Order rejected.',
            'cancel' => 'Purchase Order cancelled.',
            default => 'Purchase Order updated successfully.',
        };
    }
}
