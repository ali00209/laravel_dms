<?php

namespace App\Http\Controllers\v1\api\Voucher;

use App\Http\Controllers\v1\api\BaseApiController;
use App\Http\Requests\Voucher\StoreDeliveryNoteRequest;
use App\Http\Requests\Voucher\UpdateDeliveryNoteRequest;
use App\Http\Resources\Voucher\DeliveryNoteResource;
use App\Services\Voucher\DeliveryNoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryNoteController extends BaseApiController
{
    protected array $permissions = [
        'index' => 'delivery_note.view',
        'store' => 'delivery_note.create',
        'show' => 'delivery_note.show',
        'update' => 'delivery_note.update',
        'destroy' => 'delivery_note.delete',
        'restore' => 'delivery_note.restore',
        'forceDelete' => 'delivery_note.forceDelete',
        'submit' => 'delivery_note.submit',
        'review' => 'delivery_note.review',
        'approve' => 'delivery_note.approve',
        'reject' => 'delivery_note.reject',
        'cancel' => 'delivery_note.cancel',
    ];

    public function __construct(protected DeliveryNoteService $service)
    {
        parent::__construct();
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAction($request);

        $filters = $request->only([
            'search', 'organization_id', 'invoice_id', 'status', 'date_from', 'date_to',
        ]);

        $this->restrictToContext($request, $filters);

        $items = $this->service->paginate($filters, $this->perPage($request));

        return $this->success(
            DeliveryNoteResource::collection($items),
            $this->paginationMetadata($items)
        );
    }

    public function store(StoreDeliveryNoteRequest $request): JsonResponse
    {
        $this->authorizeAction($request);

        $this->service->create($this->getValidatedData($request));

        return $this->created('Delivery Note created successfully.');
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $deliveryNote = $this->service->find($id, $this->getActiveOrgId($request));
        $this->authorizeAction($request, $deliveryNote);

        return $this->success(new DeliveryNoteResource($deliveryNote));
    }

    public function update(UpdateDeliveryNoteRequest $request, int $id): JsonResponse
    {
        $orgId = $this->getActiveOrgId($request);
        $deliveryNote = $this->service->find($id, $orgId);

        $this->authorizeAction($request, $deliveryNote);

        $action = $request->input('action');

        $result = match($action) {
            'submit' => $this->service->submitForApproval($deliveryNote, $request->input('note')),
            'review' => $this->service->review($deliveryNote, $request->input('note')),
            'approve' => $this->service->approve($deliveryNote, $request->input('note')),
            'reject' => $this->service->reject($deliveryNote, $request->input('reason'), $request->input('note')),
            'cancel' => $this->service->cancel($deliveryNote, $request->input('reason')),
            default => $this->service->update($deliveryNote, $request->validated()),
        };

        $message = $this->getWorkflowMessage($action);

        return $this->success([new DeliveryNoteResource($result), $message]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $orgId = $this->getActiveOrgId($request);
        $deliveryNote = $this->service->find($id, $orgId);

        $this->authorizeAction($request, $deliveryNote);
        $this->service->delete($deliveryNote);

        return $this->deleted('Delivery Note deleted successfully.');
    }

    public function submit(Request $request, int $id): JsonResponse
    {
        $orgId = $this->getActiveOrgId($request);
        $deliveryNote = $this->service->find($id, $orgId);
        $this->authorizeAction($request, $deliveryNote);

        $result = $this->service->submitForApproval($deliveryNote, $request->input('note'));

        return $this->success([new DeliveryNoteResource($result), 'Delivery Note submitted for approval.']);
    }

    public function review(Request $request, int $id): JsonResponse
    {
        $orgId = $this->getActiveOrgId($request);
        $deliveryNote = $this->service->find($id, $orgId);
        $this->authorizeAction($request, $deliveryNote);

        $result = $this->service->review($deliveryNote, $request->input('note'));

        return $this->success([new DeliveryNoteResource($result), 'Delivery Note reviewed successfully.']);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $orgId = $this->getActiveOrgId($request);
        $deliveryNote = $this->service->find($id, $orgId);
        $this->authorizeAction($request, $deliveryNote);

        $result = $this->service->approve($deliveryNote, $request->input('note'));

        return $this->success([new DeliveryNoteResource($result), 'Delivery Note approved successfully.']);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
            'note' => 'nullable|string|max:500',
        ]);

        $orgId = $this->getActiveOrgId($request);
        $deliveryNote = $this->service->find($id, $orgId);
        $this->authorizeAction($request, $deliveryNote);

        $result = $this->service->reject($deliveryNote, $request->reason, $request->note);

        return $this->success([new DeliveryNoteResource($result), 'Delivery Note rejected.']);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $orgId = $this->getActiveOrgId($request);
        $deliveryNote = $this->service->find($id, $orgId);
        $this->authorizeAction($request, $deliveryNote);

        $result = $this->service->cancel($deliveryNote, $request->reason);

        return $this->success([new DeliveryNoteResource($result), 'Delivery Note cancelled.']);
    }

    public function restore(Request $request, int $id): JsonResponse
    {
        // $this->ensureAdmin($request);
        $deliveryNote = $this->service->find($id, $this->getActiveOrgId($request), true);

        $this->authorizeAction($request, $deliveryNote);
        $this->service->restore($deliveryNote);

        return $this->success(['message' => 'Delivery Note restored successfully.']);
    }

    public function forceDelete(Request $request, int $id): JsonResponse
    {
        $this->ensureAdmin($request);
        $deliveryNote = $this->service->find($id, $this->getActiveOrgId($request), true);

        $this->authorizeAction($request, $deliveryNote);
        $this->service->forceDelete($deliveryNote);

        return $this->deleted('Delivery Note permanently deleted.');
    }

    protected function getWorkflowMessage(?string $action): string
    {
        return match($action) {
            'submit' => 'Delivery Note submitted for approval.',
            'review' => 'Delivery Note reviewed successfully.',
            'approve' => 'Delivery Note approved successfully.',
            'reject' => 'Delivery Note rejected.',
            'cancel' => 'Delivery Note cancelled.',
            default => 'Delivery Note updated successfully.',
        };
    }
}
