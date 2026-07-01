<?php

namespace App\Http\Controllers\v1\api\Voucher;

use App\Http\Controllers\v1\api\BaseApiController;
use App\Http\Requests\Voucher\StoreCreditNoteRequest;
use App\Http\Requests\Voucher\UpdateCreditNoteRequest;
use App\Http\Resources\Voucher\CreditNoteResource;
use App\Services\Voucher\CreditNoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreditNoteController extends BaseApiController
{
    protected array $permissions = [
        'index' => 'credit_note.view',
        'store' => 'credit_note.create',
        'show' => 'credit_note.show',
        'update' => 'credit_note.update',
        'destroy' => 'credit_note.delete',
        'restore' => 'credit_note.restore',
        'forceDelete' => 'credit_note.forceDelete',
        'submit' => 'credit_note.submit',
        'review' => 'credit_note.review',
        'approve' => 'credit_note.approve',
        'reject' => 'credit_note.reject',
        'cancel' => 'credit_note.cancel',
    ];

    public function __construct(protected CreditNoteService $service)
    {
        parent::__construct();
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAction($request);

        $filters = $request->only([
            'search', 'organization_id', 'customer_id', 'date_from', 'date_to',
        ]);

        $this->restrictToContext($request, $filters);

        $items = $this->service->paginate($filters, $this->perPage($request));

        return $this->success(
            CreditNoteResource::collection($items),
            $this->paginationMetadata($items)
        );
    }

    public function store(StoreCreditNoteRequest $request): JsonResponse
    {
        $this->authorizeAction($request);

        $this->service->create($this->getValidatedData($request));

        return $this->created('Credit Note created successfully.');
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $creditNote = $this->service->find($id, $this->getActiveOrgId($request));
        $this->authorizeAction($request, $creditNote);

        return $this->success(new CreditNoteResource($creditNote));
    }

    public function update(UpdateCreditNoteRequest $request, int $id): JsonResponse
    {
        $orgId = $this->getActiveOrgId($request);
        $creditNote = $this->service->find($id, $orgId);

        $this->authorizeAction($request, $creditNote);

        $action = $request->input('action');

        $result = match($action) {
            'submit' => $this->service->submitForApproval($creditNote, $request->input('note')),
            'review' => $this->service->review($creditNote, $request->input('note')),
            'approve' => $this->service->approve($creditNote, $request->input('note')),
            'reject' => $this->service->reject($creditNote, $request->input('reason'), $request->input('note')),
            'cancel' => $this->service->cancel($creditNote, $request->input('reason')),
            default => $this->service->update($creditNote, $request->validated()),
        };

        $message = $this->getWorkflowMessage($action);

        return $this->success([new CreditNoteResource($result), $message]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $orgId = $this->getActiveOrgId($request);
        $creditNote = $this->service->find($id, $orgId);

        $this->authorizeAction($request, $creditNote);
        $this->service->delete($creditNote);

        return $this->deleted('Credit Note deleted successfully.');
    }

    public function submit(Request $request, int $id): JsonResponse
    {
        $orgId = $this->getActiveOrgId($request);
        $creditNote = $this->service->find($id, $orgId);
        $this->authorizeAction($request, $creditNote);

        $result = $this->service->submitForApproval($creditNote, $request->input('note'));

        return $this->success([new CreditNoteResource($result), 'Credit Note submitted for approval.']);
    }

    public function review(Request $request, int $id): JsonResponse
    {
        $orgId = $this->getActiveOrgId($request);
        $creditNote = $this->service->find($id, $orgId);
        $this->authorizeAction($request, $creditNote);

        $result = $this->service->review($creditNote, $request->input('note'));

        return $this->success([new CreditNoteResource($result), 'Credit Note reviewed successfully.']);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $orgId = $this->getActiveOrgId($request);
        $creditNote = $this->service->find($id, $orgId);
        $this->authorizeAction($request, $creditNote);

        $result = $this->service->approve($creditNote, $request->input('note'));

        return $this->success([new CreditNoteResource($result), 'Credit Note approved successfully.']);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
            'note' => 'nullable|string|max:500',
        ]);

        $orgId = $this->getActiveOrgId($request);
        $creditNote = $this->service->find($id, $orgId);
        $this->authorizeAction($request, $creditNote);

        $result = $this->service->reject($creditNote, $request->reason, $request->note);

        return $this->success([new CreditNoteResource($result), 'Credit Note rejected.']);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $orgId = $this->getActiveOrgId($request);
        $creditNote = $this->service->find($id, $orgId);
        $this->authorizeAction($request, $creditNote);

        $result = $this->service->cancel($creditNote, $request->reason);

        return $this->success([new CreditNoteResource($result), 'Credit Note cancelled.']);
    }

    public function restore(Request $request, int $id): JsonResponse
    {
        // $this->ensureAdmin($request);
        $creditNote = $this->service->find($id, $this->getActiveOrgId($request), true);

        $this->authorizeAction($request, $creditNote);
        $this->service->restore($creditNote);

        return $this->success(['message' => 'Credit Note restored successfully.']);
    }

    public function forceDelete(Request $request, int $id): JsonResponse
    {
        $this->ensureAdmin($request);
        $creditNote = $this->service->find($id, $this->getActiveOrgId($request), true);

        $this->authorizeAction($request, $creditNote);
        $this->service->forceDelete($creditNote);

        return $this->deleted('Credit Note permanently deleted.');
    }

    protected function getWorkflowMessage(?string $action): string
    {
        return match($action) {
            'submit' => 'Credit Note submitted for approval.',
            'review' => 'Credit Note reviewed successfully.',
            'approve' => 'Credit Note approved successfully.',
            'reject' => 'Credit Note rejected.',
            'cancel' => 'Credit Note cancelled.',
            default => 'Credit Note updated successfully.',
        };
    }
}
