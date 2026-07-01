<?php

namespace App\Services\Voucher;

use App\Exceptions\NotFoundException;
use App\Exceptions\WorkflowException;
use App\Models\Vouchers\DeliveryNote;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DeliveryNoteService
{
    protected array $relations = [
        'organization',
        'saleOrder',
        'invoice',
        'rider',
        'updatedBy',
        'items',
    ];

    public function paginate(?array $filters, int $perPage): LengthAwarePaginator
    {
        return DeliveryNote::query()
            ->with($this->relations)
            ->when(isset($filters['organization_id']), fn($q) => $q->where('organization_id', $filters['organization_id']))
            ->when(isset($filters['branch_id']), fn($q) => $q->where('branch_id', $filters['branch_id']))
            ->when(isset($filters['warehouse_id']), fn($q) => $q->where('warehouse_id', $filters['warehouse_id']))
            ->when(isset($filters['outlet_id']), fn($q) => $q->where('outlet_id', $filters['outlet_id']))
            ->when(isset($filters['invoice_id']), fn($q) => $q->where('invoice_id', $filters['invoice_id']))
            ->when(isset($filters['status']), fn($q) => $q->where('status', $filters['status']))
            ->when(isset($filters['date_from']), fn($q) => $q->whereDate('date', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn($q) => $q->whereDate('date', '<=', $filters['date_to']))
            ->when(!empty($filters['search']), function ($q) use ($filters) {
                $term = '%' . trim($filters['search']) . '%';
                $q->where(function ($sub) use ($term) {
                    $sub->where('document_number', 'like', $term);
                });
            })
            ->latest()
            ->paginate($perPage);
    }

    public function find(int $id, ?int $orgId = null, bool $withTrashed = false): DeliveryNote
    {
        $query = DeliveryNote::query();
        $query->with($this->relations);

        if ($withTrashed) {
            $query->withTrashed();
        }

        if ($orgId !== null) {
            $query->where('organization_id', $orgId);
        }

        return $query->find($id) ?? throw new NotFoundException('Delivery Note not found.');
    }

    public function create(array $data): DeliveryNote
    {
        return DB::transaction(function () use ($data) {
            $deliveryNote = DeliveryNote::create($data);

            $deliveryNote->logEvent('created', null, $data);

            return $deliveryNote->load($this->relations);
        });
    }

    public function update(DeliveryNote $deliveryNote, array $data): DeliveryNote
    {
        if (!$deliveryNote->isEditable()) {
            throw new WorkflowException('Delivery Note cannot be edited in its current status: ' . $deliveryNote->status);
        }

        return DB::transaction(function () use ($deliveryNote, $data) {
            $oldData = $deliveryNote->toArray();

            $deliveryNote->update($data);
            $deliveryNote->logEvent('updated', $oldData, $deliveryNote->toArray());

            return $deliveryNote->load($this->relations);
        });
    }

    public function delete(DeliveryNote $deliveryNote): void
    {
        if (!$deliveryNote->isDeletable()) {
            throw new WorkflowException('Delivery Note cannot be deleted in its current status.');
        }

        $deliveryNote->delete();
        $deliveryNote->logEvent('deleted', null, null);
    }

    public function restore(DeliveryNote $deliveryNote): void
    {
        $deliveryNote->restore();
        $deliveryNote->logEvent('restored', null, null);
    }

    public function forceDelete(DeliveryNote $deliveryNote): void
    {
        $deliveryNote->forceDelete();
    }

    public function submitForApproval(DeliveryNote $deliveryNote, ?string $note = null): DeliveryNote
    {
        if (!$deliveryNote->canTransitionTo('submitted')) {
            throw new WorkflowException('Delivery Note cannot be submitted from status: ' . $deliveryNote->status);
        }

        return DB::transaction(function () use ($deliveryNote, $note) {
            $deliveryNote->markSubmitted();

            if ($note) {
                $deliveryNote->addComment($note, true);
            }

            $deliveryNote->logEvent('submitted', null, ['status' => $deliveryNote->status]);

            return $deliveryNote;
        });
    }

    public function review(DeliveryNote $deliveryNote, ?string $note = null, ?int $userId = null): DeliveryNote
    {
        if (!$deliveryNote->isReviewable()) {
            throw new WorkflowException('Delivery Note is not in a reviewable state.');
        }

        return DB::transaction(function () use ($deliveryNote, $note, $userId) {
            $deliveryNote->markReviewed($userId);
            $deliveryNote->changeStatus('reviewed', $note);

            if ($note) {
                $deliveryNote->addComment($note, true);
            }

            $deliveryNote->logEvent('reviewed', null, ['reviewed_by' => $userId ?? Auth::id()]);

            return $deliveryNote;
        });
    }

    public function approve(DeliveryNote $deliveryNote, ?string $note = null, ?int $userId = null): DeliveryNote
    {
        if (!$deliveryNote->isApprovable()) {
            throw new WorkflowException('Delivery Note is not in an approvable state.');
        }

        return DB::transaction(function () use ($deliveryNote, $note, $userId) {
            $deliveryNote->markApproved($userId);
            $deliveryNote->changeStatus('approved', $note);

            if ($note) {
                $deliveryNote->addComment($note, true);
            }

            $deliveryNote->logEvent('approved', null, ['approved_by' => $userId ?? Auth::id()]);

            $fresh = $deliveryNote->fresh();
            event(new \App\Events\Voucher\VoucherApproved([
                'reference_type' => get_class($fresh),
                'reference_id' => $fresh->id,
                'document_type' => 'delivery_note',
                'organization_id' => $fresh->organization_id ?? null,
                'branch_id' => $fresh->branch_id ?? null,
                'created_by' => $userId ?? $fresh->created_by ?? null,
                'items_count' => method_exists($fresh, 'items') ? $fresh->items()->count() : null,
            ]));

            return $deliveryNote;
        });
    }

    public function reject(DeliveryNote $deliveryNote, string $reason, ?string $note = null, ?int $userId = null): DeliveryNote
    {
        if (!$deliveryNote->canTransitionTo('rejected')) {
            throw new WorkflowException('Delivery Note cannot be rejected from status: ' . $deliveryNote->status);
        }

        return DB::transaction(function () use ($deliveryNote, $reason, $note, $userId) {
            $deliveryNote->markRejected($reason, null, $userId);

            if ($note) {
                $deliveryNote->addComment($note, true);
            }

            $deliveryNote->logEvent('rejected', null, [
                'reason' => $reason,
                'rejected_by' => $userId ?? Auth::id(),
            ]);

            return $deliveryNote;
        });
    }

    public function cancel(DeliveryNote $deliveryNote, string $reason): DeliveryNote
    {
        if (!$deliveryNote->canTransitionTo('cancelled')) {
            throw new WorkflowException('Delivery Note cannot be cancelled from status: ' . $deliveryNote->status);
        }

        return DB::transaction(function () use ($deliveryNote, $reason) {
            $deliveryNote->changeStatus('cancelled', $reason);

            $deliveryNote->logEvent('cancelled', null, ['reason' => $reason]);

            $fresh = $deliveryNote->fresh();
            event(new \App\Events\Voucher\VoucherCancelled([
                'reference_type' => get_class($fresh),
                'reference_id' => $fresh->id,
                'document_type' => 'delivery_note',
                'organization_id' => $fresh->organization_id ?? null,
                'branch_id' => $fresh->branch_id ?? null,
                'created_by' => $fresh->created_by ?? null,
                'reason' => $reason,
            ]));

            return $deliveryNote;
        });
    }

    public function post(int $id): ?DeliveryNote
    {
        $deliveryNote = DeliveryNote::find($id);
        if (!$deliveryNote) {
            return null;
        }

        $deliveryNote->update(['status' => 'posted']);

        $fresh = $deliveryNote->fresh();

        event(new \App\Events\DeliveryNotePosted([
            'reference_type' => get_class($fresh),
            'reference_id' => $fresh->id,
            'document_type' => 'delivery_note',
            'organization_id' => $fresh->organization_id ?? null,
            'branch_id' => $fresh->branch_id ?? null,
            'created_by' => $fresh->rider ?? ($fresh->created_by ?? null),
            'items_count' => method_exists($fresh, 'items') ? $fresh->items()->count() : null,
        ]));

        return $fresh;
    }
}
