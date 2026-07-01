<?php

namespace App\Http\Controllers\v1\api\Voucher;

use App\Http\Controllers\v1\api\BaseApiController;
use App\Http\Requests\Voucher\StorePurchaseBillRequest;
use App\Http\Requests\Voucher\UpdatePurchaseBillRequest;
use App\Http\Resources\Voucher\PurchaseBillResource;
use App\Services\Voucher\PurchaseBillService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseBillController extends BaseApiController
{
    protected array $permissions = [
        'index' => 'purchase_bill.view',
        'store' => 'purchase_bill.create',
        'show' => 'purchase_bill.show',
        'update' => 'purchase_bill.update',
        'destroy' => 'purchase_bill.delete',
        'restore' => 'purchase_bill.restore',
        'forceDelete' => 'purchase_bill.forceDelete',
        'submit' => 'purchase_bill.submit',
        'review' => 'purchase_bill.review',
        'approve' => 'purchase_bill.approve',
        'reject' => 'purchase_bill.reject',
        'cancel' => 'purchase_bill.cancel',
    ];

    public function __construct(protected PurchaseBillService $service)
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
            PurchaseBillResource::collection($items),
            $this->paginationMetadata($items)
        );
    }

    public function store(StorePurchaseBillRequest $request): JsonResponse
    {
        $this->authorizeAction($request);

        $this->service->create($this->getValidatedData($request));

        return $this->created('Purchase Bill created successfully.');
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $purchaseBill = $this->service->find($id, $this->getActiveOrgId($request));
        $this->authorizeAction($request, $purchaseBill);

        return $this->success(new PurchaseBillResource($purchaseBill));
    }

    public function update(UpdatePurchaseBillRequest $request, int $id): JsonResponse
    {
        $orgId = $this->getActiveOrgId($request);
        $purchaseBill = $this->service->find($id, $orgId);

        $this->authorizeAction($request, $purchaseBill);

        $action = $request->input('action');

        $result = match($action) {
            'submit' => $this->service->submitForApproval($purchaseBill, $request->input('note')),
            'review' => $this->service->review($purchaseBill, $request->input('note')),
            'approve' => $this->service->approve($purchaseBill, $request->input('note')),
            'reject' => $this->service->reject($purchaseBill, $request->input('reason'), $request->input('note')),
            'cancel' => $this->service->cancel($purchaseBill, $request->input('reason')),
            default => $this->service->update($purchaseBill, $request->validated()),
        };

        $message = $this->getWorkflowMessage($action);

        return $this->success([new PurchaseBillResource($result), $message]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $orgId = $this->getActiveOrgId($request);
        $purchaseBill = $this->service->find($id, $orgId);

        $this->authorizeAction($request, $purchaseBill);
        $this->service->delete($purchaseBill);

        return $this->deleted('Purchase Bill deleted successfully.');
    }

    public function submit(Request $request, int $id): JsonResponse
    {
        $orgId = $this->getActiveOrgId($request);
        $purchaseBill = $this->service->find($id, $orgId);
        $this->authorizeAction($request, $purchaseBill);

        $result = $this->service->submitForApproval($purchaseBill, $request->input('note'));

        return $this->success([new PurchaseBillResource($result), 'Purchase Bill submitted for approval.']);
    }

    public function review(Request $request, int $id): JsonResponse
    {
        $orgId = $this->getActiveOrgId($request);
        $purchaseBill = $this->service->find($id, $orgId);
        $this->authorizeAction($request, $purchaseBill);

        $result = $this->service->review($purchaseBill, $request->input('note'));

        return $this->success([new PurchaseBillResource($result), 'Purchase Bill reviewed successfully.']);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $orgId = $this->getActiveOrgId($request);
        $purchaseBill = $this->service->find($id, $orgId);
        $this->authorizeAction($request, $purchaseBill);

        $result = $this->service->approve($purchaseBill, $request->input('note'));

        return $this->success([new PurchaseBillResource($result), 'Purchase Bill approved successfully.']);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
            'note' => 'nullable|string|max:500',
        ]);

        $orgId = $this->getActiveOrgId($request);
        $purchaseBill = $this->service->find($id, $orgId);
        $this->authorizeAction($request, $purchaseBill);

        $result = $this->service->reject($purchaseBill, $request->reason, $request->note);

        return $this->success([new PurchaseBillResource($result), 'Purchase Bill rejected.']);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $orgId = $this->getActiveOrgId($request);
        $purchaseBill = $this->service->find($id, $orgId);
        $this->authorizeAction($request, $purchaseBill);

        $result = $this->service->cancel($purchaseBill, $request->reason);

        return $this->success([new PurchaseBillResource($result), 'Purchase Bill cancelled.']);
    }

    public function restore(Request $request, int $id): JsonResponse
    {
        // $this->ensureAdmin($request);
        $purchaseBill = $this->service->find($id, $this->getActiveOrgId($request), true);

        $this->authorizeAction($request, $purchaseBill);
        $this->service->restore($purchaseBill);

        return $this->success(['message' => 'Purchase Bill restored successfully.']);
    }

    public function forceDelete(Request $request, int $id): JsonResponse
    {
        $this->ensureAdmin($request);
        $purchaseBill = $this->service->find($id, $this->getActiveOrgId($request), true);

        $this->authorizeAction($request, $purchaseBill);
        $this->service->forceDelete($purchaseBill);

        return $this->deleted('Purchase Bill permanently deleted.');
    }

    protected function getWorkflowMessage(?string $action): string
    {
        return match($action) {
            'submit' => 'Purchase Bill submitted for approval.',
            'review' => 'Purchase Bill reviewed successfully.',
            'approve' => 'Purchase Bill approved successfully.',
            'reject' => 'Purchase Bill rejected.',
            'cancel' => 'Purchase Bill cancelled.',
            default => 'Purchase Bill updated successfully.',
        };
    }
}
