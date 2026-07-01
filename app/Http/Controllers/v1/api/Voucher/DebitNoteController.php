<?php

namespace App\Http\Controllers\v1\api\Voucher;

use App\Http\Controllers\v1\api\BaseApiController;
use App\Http\Requests\Voucher\StoreDebitNoteRequest;
use App\Http\Requests\Voucher\UpdateDebitNoteRequest;
use App\Http\Resources\Voucher\DebitNoteResource;
use App\Services\Voucher\DebitNoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DebitNoteController extends BaseApiController
{
    protected array $permissions = [
        'index' => 'debit_note.view',
        'store' => 'debit_note.create',
        'show' => 'debit_note.show',
        'update' => 'debit_note.update',
        'destroy' => 'debit_note.delete',
        'restore' => 'debit_note.restore',
        'forceDelete' => 'debit_note.forceDelete',
        'submit' => 'debit_note.submit',
        'review' => 'debit_note.review',
        'approve' => 'debit_note.approve',
        'reject' => 'debit_note.reject',
        'cancel' => 'debit_note.cancel',
    ];

    public function __construct(protected DebitNoteService $service)
    {
        parent::__construct();
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAction($request);

        $filters = $request->only([
            'search', 'organization_id', 'supplier_id', 'date_from', 'date_to',
        ]);

        $this->restrictToContext($request, $filters);

        $items = $this->service->paginate($filters, $this->perPage($request));

        return $this->success(
            DebitNoteResource::collection($items),
            $this->paginationMetadata($items)
        );
    }

    public function store(StoreDebitNoteRequest $request): JsonResponse
    {
        $this->authorizeAction($request);

        $this->service->create($this->getValidatedData($request));

        return $this->created('Debit Note created successfully.');
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $debitNote = $this->service->find($id, $this->getActiveOrgId($request));
        $this->authorizeAction($request, $debitNote);

        return $this->success(new DebitNoteResource($debitNote));
    }

    public function update(UpdateDebitNoteRequest $request, int $id): JsonResponse
    {
        $orgId = $this->getActiveOrgId($request);
        $debitNote = $this->service->find($id, $orgId);

        $this->authorizeAction($request, $debitNote);

        $action = $request->input('action');

        $result = match($action) {
            'submit' => $this->service->submitForApproval($debitNote, $request->input('note')),
            'review' => $this->service->review($debitNote, $request->input('note')),
            'approve' => $this->service->approve($debitNote, $request->input('note')),
            'reject' => $this->service->reject($debitNote, $request->input('reason'), $request->input('note')),
            'cancel' => $this->service->cancel($debitNote, $request->input('reason')),
            default => $this->service->update($debitNote, $request->validated()),
        };

        $message = $this->getWorkflowMessage($action);

        return $this->success([new DebitNoteResource($result), $message]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $orgId = $this->getActiveOrgId($request);
        $debitNote = $this->service->find($id, $orgId);

        $this->authorizeAction($request, $debitNote);
        $this->service->delete($debitNote);

        return $this->deleted('Debit Note deleted successfully.');
    }

    public function submit(Request $request, int $id): JsonResponse
    {
        $orgId = $this->getActiveOrgId($request);
        $debitNote = $this->service->find($id, $orgId);
        $this->authorizeAction($request, $debitNote);

        $result = $this->service->submitForApproval($debitNote, $request->input('note'));

        return $this->success([new DebitNoteResource($result), 'Debit Note submitted for approval.']);
    }

    public function review(Request $request, int $id): JsonResponse
    {
        $orgId = $this->getActiveOrgId($request);
        $debitNote = $this->service->find($id, $orgId);
        $this->authorizeAction($request, $debitNote);

        $result = $this->service->review($debitNote, $request->input('note'));

        return $this->success([new DebitNoteResource($result), 'Debit Note reviewed successfully.']);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $orgId = $this->getActiveOrgId($request);
        $debitNote = $this->service->find($id, $orgId);
        $this->authorizeAction($request, $debitNote);

        $result = $this->service->approve($debitNote, $request->input('note'));

        return $this->success([new DebitNoteResource($result), 'Debit Note approved successfully.']);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
            'note' => 'nullable|string|max:500',
        ]);

        $orgId = $this->getActiveOrgId($request);
        $debitNote = $this->service->find($id, $orgId);
        $this->authorizeAction($request, $debitNote);

        $result = $this->service->reject($debitNote, $request->reason, $request->note);

        return $this->success([new DebitNoteResource($result), 'Debit Note rejected.']);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $orgId = $this->getActiveOrgId($request);
        $debitNote = $this->service->find($id, $orgId);
        $this->authorizeAction($request, $debitNote);

        $result = $this->service->cancel($debitNote, $request->reason);

        return $this->success([new DebitNoteResource($result), 'Debit Note cancelled.']);
    }

    public function restore(Request $request, int $id): JsonResponse
    {
        // $this->ensureAdmin($request);
        $debitNote = $this->service->find($id, $this->getActiveOrgId($request), true);

        $this->authorizeAction($request, $debitNote);
        $this->service->restore($debitNote);

        return $this->success(['message' => 'Debit Note restored successfully.']);
    }

    public function forceDelete(Request $request, int $id): JsonResponse
    {
        $this->ensureAdmin($request);
        $debitNote = $this->service->find($id, $this->getActiveOrgId($request), true);

        $this->authorizeAction($request, $debitNote);
        $this->service->forceDelete($debitNote);

        return $this->deleted('Debit Note permanently deleted.');
    }

    protected function getWorkflowMessage(?string $action): string
    {
        return match($action) {
            'submit' => 'Debit Note submitted for approval.',
            'review' => 'Debit Note reviewed successfully.',
            'approve' => 'Debit Note approved successfully.',
            'reject' => 'Debit Note rejected.',
            'cancel' => 'Debit Note cancelled.',
            default => 'Debit Note updated successfully.',
        };
    }
}
