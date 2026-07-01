<?php

namespace App\Http\Controllers\v1\api\Voucher;

use App\Http\Controllers\v1\api\BaseApiController;
use App\Http\Requests\Voucher\StoreInvoiceRequest;
use App\Http\Requests\Voucher\UpdateInvoiceRequest;
use App\Http\Resources\Voucher\InvoiceResource;
use App\Services\Voucher\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends BaseApiController
{
    protected array $permissions = [
        'index' => 'invoice.view',
        'store' => 'invoice.create',
        'show' => 'invoice.show',
        'update' => 'invoice.update',
        'destroy' => 'invoice.delete',
        'restore' => 'invoice.restore',
        'forceDelete' => 'invoice.forceDelete',
        'submit' => 'invoice.submit',
        'review' => 'invoice.review',
        'approve' => 'invoice.approve',
        'confirm' => 'invoice.confirm',
        'reject' => 'invoice.reject',
        'cancel' => 'invoice.cancel',
    ];

    public function __construct(protected InvoiceService $service)
    {
        parent::__construct();
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAction($request);

        $filters = $request->only([
            'search', 'branch_id', 'customer_id', 'voucher_type_id',
            'status', 'date_from', 'date_to', 'need_review', 'need_approval'
        ]);

        $this->restrictToContext($request, $filters);

        $items = $this->service->paginate($filters, $this->perPage($request));

        return $this->success(
            InvoiceResource::collection($items),
            $this->paginationMetadata($items)
        );
    }

    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $this->authorizeAction($request);
        $this->enforcePolicy($request, 'sales');

        $this->service->create($this->getValidatedData($request));

        return $this->created('Invoice created successfully.');
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $invoice = $this->service->find($id, $this->getActiveOrgId($request));
        $this->authorizeAction($request, $invoice);

        return $this->success(new InvoiceResource($invoice));
    }

    public function update(UpdateInvoiceRequest $request, int $id): JsonResponse
    {
        $orgId = $this->getActiveOrgId($request);
        $invoice = $this->service->find($id, $orgId);

        $this->authorizeAction($request, $invoice);

        $action = $request->input('action');

        $result = match($action) {
            'submit' => $this->service->submitForApproval($invoice, $request->input('note')),
            'review' => $this->service->review($invoice, $request->input('note')),
            'approve' => $this->service->approve($invoice, $request->input('note')),
            'confirm' => $this->service->confirm($invoice, $request->input('note')),
            'reject' => $this->service->reject($invoice, $request->input('reason'), $request->input('note')),
            'cancel' => $this->service->cancel($invoice, $request->input('reason')),
            default => $this->service->update($invoice, $request->validated()),
        };

        $message = $this->getWorkflowMessage($action);

        return $this->success([new InvoiceResource($result), $message]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $orgId = $this->getActiveOrgId($request);
        $invoice = $this->service->find($id, $orgId);

        $this->authorizeAction($request, $invoice);
        $this->service->delete($invoice);

        return $this->deleted('Invoice deleted successfully.');
    }

    public function restore(Request $request, int $id): JsonResponse
    {
        // $this->ensureAdmin($request);
        $invoice = $this->service->find($id, $this->getActiveOrgId($request), true);

        $this->authorizeAction($request, $invoice);
        $this->service->restore($invoice);

        return $this->success(['message' => 'Invoice restored successfully.']);
    }

    public function forceDelete(Request $request, int $id): JsonResponse
    {
        $this->ensureAdmin($request);
        $invoice = $this->service->find($id, $this->getActiveOrgId($request), true);

        $this->authorizeAction($request, $invoice);
        $this->service->forceDelete($invoice);

        return $this->deleted('Invoice permanently deleted.');
    }

    public function submit(Request $request, int $id): JsonResponse
    {
        $orgId = $this->getActiveOrgId($request);
        $invoice = $this->service->find($id, $orgId);
        $this->authorizeAction($request, $invoice);

        $result = $this->service->submitForApproval($invoice, $request->input('note'));

        return $this->success([new InvoiceResource($result), 'Invoice submitted for approval.']);
    }

    public function review(Request $request, int $id): JsonResponse
    {
        $orgId = $this->getActiveOrgId($request);
        $invoice = $this->service->find($id, $orgId);
        $this->authorizeAction($request, $invoice);

        $result = $this->service->review($invoice, $request->input('note'));

        return $this->success([new InvoiceResource($result), 'Invoice reviewed successfully.']);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $orgId = $this->getActiveOrgId($request);
        $invoice = $this->service->find($id, $orgId);
        $this->authorizeAction($request, $invoice);

        $result = $this->service->approve($invoice, $request->input('note'));

        return $this->success([new InvoiceResource($result), 'Invoice approved successfully.']);
    }

    public function confirm(Request $request, int $id): JsonResponse
    {
        $orgId = $this->getActiveOrgId($request);
        $invoice = $this->service->find($id, $orgId);
        $this->authorizeAction($request, $invoice);

        $result = $this->service->confirm($invoice, $request->input('note'));

        return $this->success([new InvoiceResource($result), 'Invoice confirmed successfully.']);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
            'note' => 'nullable|string|max:500',
        ]);

        $orgId = $this->getActiveOrgId($request);
        $invoice = $this->service->find($id, $orgId);
        $this->authorizeAction($request, $invoice);

        $result = $this->service->reject($invoice, $request->reason, $request->note);

        return $this->success([new InvoiceResource($result), 'Invoice rejected.']);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $orgId = $this->getActiveOrgId($request);
        $invoice = $this->service->find($id, $orgId);
        $this->authorizeAction($request, $invoice);

        $result = $this->service->cancel($invoice, $request->reason);

        return $this->success([new InvoiceResource($result), 'Invoice cancelled.']);
    }

    protected function getWorkflowMessage(?string $action): string
    {
        return match($action) {
            'submit' => 'Invoice submitted for approval.',
            'review' => 'Invoice reviewed successfully.',
            'approve' => 'Invoice approved successfully.',
            'confirm' => 'Invoice confirmed successfully.',
            'reject' => 'Invoice rejected.',
            'cancel' => 'Invoice cancelled.',
            default => 'Invoice updated successfully.',
        };
    }
}
