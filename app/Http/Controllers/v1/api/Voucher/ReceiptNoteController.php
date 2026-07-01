<?php

namespace App\Http\Controllers\v1\api\Voucher;

use App\Http\Controllers\v1\api\BaseApiController;
use App\Http\Requests\Voucher\StoreReceiptNoteRequest;
use App\Http\Requests\Voucher\UpdateReceiptNoteRequest;
use App\Http\Resources\Voucher\ReceiptNoteResource;
use App\Services\Voucher\ReceiptNoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReceiptNoteController extends BaseApiController
{
    protected array $permissions = [
        'index' => 'receipt_note.view',
        'store' => 'receipt_note.create',
        'show' => 'receipt_note.show',
        'update' => 'receipt_note.update',
        'destroy' => 'receipt_note.delete',
        'restore' => 'receipt_note.restore',
        'forceDelete' => 'receipt_note.forceDelete',
        'submit' => 'receipt_note.submit',
        'review' => 'receipt_note.review',
        'approve' => 'receipt_note.approve',
        'reject' => 'receipt_note.reject',
        'cancel' => 'receipt_note.cancel',
    ];

    public function __construct(protected ReceiptNoteService $service)
    {
        parent::__construct();
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAction($request);

        $filters = $request->only([
            'search', 'organization_id', 'purchase_bill_id', 'status', 'date_from', 'date_to',
        ]);

        $this->restrictToContext($request, $filters);

        $items = $this->service->paginate($filters, $this->perPage($request));

        return $this->success(
            ReceiptNoteResource::collection($items),
            $this->paginationMetadata($items)
        );
    }

    public function store(StoreReceiptNoteRequest $request): JsonResponse
    {
        $this->authorizeAction($request);

        $this->service->create($this->getValidatedData($request));

        return $this->created('Receipt Note created successfully.');
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $receiptNote = $this->service->find($id, $this->getActiveOrgId($request));
        $this->authorizeAction($request, $receiptNote);

        return $this->success(new ReceiptNoteResource($receiptNote));
    }

    public function update(UpdateReceiptNoteRequest $request, int $id): JsonResponse
    {
        $orgId = $this->getActiveOrgId($request);
        $receiptNote = $this->service->find($id, $orgId);

        $this->authorizeAction($request, $receiptNote);

        $action = $request->input('action');

        $result = match($action) {
            'submit' => $this->service->submitForApproval($receiptNote, $request->input('note')),
            'review' => $this->service->review($receiptNote, $request->input('note')),
            'approve' => $this->service->approve($receiptNote, $request->input('note')),
            'reject' => $this->service->reject($receiptNote, $request->input('reason'), $request->input('note')),
            'cancel' => $this->service->cancel($receiptNote, $request->input('reason')),
            default => $this->service->update($receiptNote, $request->validated()),
        };

        $message = $this->getWorkflowMessage($action);

        return $this->success([new ReceiptNoteResource($result), $message]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $orgId = $this->getActiveOrgId($request);
        $receiptNote = $this->service->find($id, $orgId);

        $this->authorizeAction($request, $receiptNote);
        $this->service->delete($receiptNote);

        return $this->deleted('Receipt Note deleted successfully.');
    }

    public function submit(Request $request, int $id): JsonResponse
    {
        $orgId = $this->getActiveOrgId($request);
        $receiptNote = $this->service->find($id, $orgId);
        $this->authorizeAction($request, $receiptNote);

        $result = $this->service->submitForApproval($receiptNote, $request->input('note'));

        return $this->success([new ReceiptNoteResource($result), 'Receipt Note submitted for approval.']);
    }

    public function review(Request $request, int $id): JsonResponse
    {
        $orgId = $this->getActiveOrgId($request);
        $receiptNote = $this->service->find($id, $orgId);
        $this->authorizeAction($request, $receiptNote);

        $result = $this->service->review($receiptNote, $request->input('note'));

        return $this->success([new ReceiptNoteResource($result), 'Receipt Note reviewed successfully.']);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $orgId = $this->getActiveOrgId($request);
        $receiptNote = $this->service->find($id, $orgId);
        $this->authorizeAction($request, $receiptNote);

        $result = $this->service->approve($receiptNote, $request->input('note'));

        return $this->success([new ReceiptNoteResource($result), 'Receipt Note approved successfully.']);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
            'note' => 'nullable|string|max:500',
        ]);

        $orgId = $this->getActiveOrgId($request);
        $receiptNote = $this->service->find($id, $orgId);
        $this->authorizeAction($request, $receiptNote);

        $result = $this->service->reject($receiptNote, $request->reason, $request->note);

        return $this->success([new ReceiptNoteResource($result), 'Receipt Note rejected.']);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $orgId = $this->getActiveOrgId($request);
        $receiptNote = $this->service->find($id, $orgId);
        $this->authorizeAction($request, $receiptNote);

        $result = $this->service->cancel($receiptNote, $request->reason);

        return $this->success([new ReceiptNoteResource($result), 'Receipt Note cancelled.']);
    }

    public function restore(Request $request, int $id): JsonResponse
    {
        // $this->ensureAdmin($request);
        $receiptNote = $this->service->find($id, $this->getActiveOrgId($request), true);

        $this->authorizeAction($request, $receiptNote);
        $this->service->restore($receiptNote);

        return $this->success(['message' => 'Receipt Note restored successfully.']);
    }

    public function forceDelete(Request $request, int $id): JsonResponse
    {
        $this->ensureAdmin($request);
        $receiptNote = $this->service->find($id, $this->getActiveOrgId($request), true);

        $this->authorizeAction($request, $receiptNote);
        $this->service->forceDelete($receiptNote);

        return $this->deleted('Receipt Note permanently deleted.');
    }

    protected function getWorkflowMessage(?string $action): string
    {
        return match($action) {
            'submit' => 'Receipt Note submitted for approval.',
            'review' => 'Receipt Note reviewed successfully.',
            'approve' => 'Receipt Note approved successfully.',
            'reject' => 'Receipt Note rejected.',
            'cancel' => 'Receipt Note cancelled.',
            default => 'Receipt Note updated successfully.',
        };
    }
}
