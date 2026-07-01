<?php

namespace App\Services\Voucher;

use App\Exceptions\NotFoundException;
use App\Exceptions\WorkflowException;
use App\Models\Vouchers\ReceiptNote;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReceiptNoteService
{
    protected array $relations = [
        'organization',
        'purchaseOrder',
        'purchaseBill',
        'voucherType',
        'receivedBy',
        'updatedBy',
        'items',
    ];

    public function paginate(?array $filters, int $perPage): LengthAwarePaginator
    {
        return ReceiptNote::query()
            ->with($this->relations)
            ->when(isset($filters['organization_id']), fn($q) => $q->where('organization_id', $filters['organization_id']))
            ->when(isset($filters['branch_id']), fn($q) => $q->where('branch_id', $filters['branch_id']))
            ->when(isset($filters['warehouse_id']), fn($q) => $q->where('warehouse_id', $filters['warehouse_id']))
            ->when(isset($filters['outlet_id']), fn($q) => $q->where('outlet_id', $filters['outlet_id']))
            ->when(isset($filters['purchase_bill_id']), fn($q) => $q->where('purchase_bill_id', $filters['purchase_bill_id']))
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

    public function find(int $id, ?int $orgId = null, bool $withTrashed = false): ReceiptNote
    {
        $query = ReceiptNote::query();
        $query->with($this->relations);

        if ($withTrashed) {
            $query->withTrashed();
        }

        if ($orgId !== null) {
            $query->where('organization_id', $orgId);
        }

        return $query->find($id) ?? throw new NotFoundException('Receipt Note not found.');
    }

    public function create(array $data): ReceiptNote
    {
        return DB::transaction(function () use ($data) {
            $receiptNote = ReceiptNote::create($data);

            $receiptNote->logEvent('created', null, $data);

            return $receiptNote->load($this->relations);
        });
    }

    public function update(ReceiptNote $receiptNote, array $data): ReceiptNote
    {
        if (!$receiptNote->isEditable()) {
            throw new WorkflowException('Receipt Note cannot be edited in its current status: ' . $receiptNote->status);
        }

        return DB::transaction(function () use ($receiptNote, $data) {
            $oldData = $receiptNote->toArray();

            $receiptNote->update($data);
            $receiptNote->logEvent('updated', $oldData, $receiptNote->toArray());

            return $receiptNote->load($this->relations);
        });
    }

    public function delete(ReceiptNote $receiptNote): void
    {
        if (!$receiptNote->isDeletable()) {
            throw new WorkflowException('Receipt Note cannot be deleted in its current status.');
        }

        $receiptNote->delete();
        $receiptNote->logEvent('deleted', null, null);
    }

    public function restore(ReceiptNote $receiptNote): void
    {
        $receiptNote->restore();
        $receiptNote->logEvent('restored', null, null);
    }

    public function forceDelete(ReceiptNote $receiptNote): void
    {
        $receiptNote->forceDelete();
    }

    public function submitForApproval(ReceiptNote $receiptNote, ?string $note = null): ReceiptNote
    {
        if (!$receiptNote->canTransitionTo('submitted')) {
            throw new WorkflowException('Receipt Note cannot be submitted from status: ' . $receiptNote->status);
        }

        return DB::transaction(function () use ($receiptNote, $note) {
            $receiptNote->markSubmitted();

            if ($note) {
                $receiptNote->addComment($note, true);
            }

            $receiptNote->logEvent('submitted', null, ['status' => $receiptNote->status]);

            return $receiptNote;
        });
    }

    public function review(ReceiptNote $receiptNote, ?string $note = null, ?int $userId = null): ReceiptNote
    {
        if (!$receiptNote->isReviewable()) {
            throw new WorkflowException('Receipt Note is not in a reviewable state.');
        }

        return DB::transaction(function () use ($receiptNote, $note, $userId) {
            $receiptNote->markReviewed($userId);
            $receiptNote->changeStatus('reviewed', $note);

            if ($note) {
                $receiptNote->addComment($note, true);
            }

            $receiptNote->logEvent('reviewed', null, ['reviewed_by' => $userId ?? Auth::id()]);

            return $receiptNote;
        });
    }

    public function approve(ReceiptNote $receiptNote, ?string $note = null, ?int $userId = null): ReceiptNote
    {
        if (!$receiptNote->isApprovable()) {
            throw new WorkflowException('Receipt Note is not in an approvable state.');
        }

        return DB::transaction(function () use ($receiptNote, $note, $userId) {
            $receiptNote->markApproved($userId);
            $receiptNote->changeStatus('approved', $note);

            if ($note) {
                $receiptNote->addComment($note, true);
            }

            $receiptNote->logEvent('approved', null, ['approved_by' => $userId ?? Auth::id()]);

            $fresh = $receiptNote->fresh();
            event(new \App\Events\Voucher\VoucherApproved([
                'reference_type' => get_class($fresh),
                'reference_id' => $fresh->id,
                'document_type' => 'receipt_note',
                'organization_id' => $fresh->organization_id ?? null,
                'branch_id' => $fresh->branch_id ?? null,
                'created_by' => $userId ?? $fresh->created_by ?? null,
                'items_count' => method_exists($fresh, 'items') ? $fresh->items()->count() : null,
            ]));

            return $receiptNote;
        });
    }

    public function reject(ReceiptNote $receiptNote, string $reason, ?string $note = null, ?int $userId = null): ReceiptNote
    {
        if (!$receiptNote->canTransitionTo('rejected')) {
            throw new WorkflowException('Receipt Note cannot be rejected from status: ' . $receiptNote->status);
        }

        return DB::transaction(function () use ($receiptNote, $reason, $note, $userId) {
            $receiptNote->markRejected($reason, null, $userId);

            if ($note) {
                $receiptNote->addComment($note, true);
            }

            $receiptNote->logEvent('rejected', null, [
                'reason' => $reason,
                'rejected_by' => $userId ?? Auth::id(),
            ]);

            return $receiptNote;
        });
    }

    public function cancel(ReceiptNote $receiptNote, string $reason): ReceiptNote
    {
        if (!$receiptNote->canTransitionTo('cancelled')) {
            throw new WorkflowException('Receipt Note cannot be cancelled from status: ' . $receiptNote->status);
        }

        return DB::transaction(function () use ($receiptNote, $reason) {
            $receiptNote->changeStatus('cancelled', $reason);

            $receiptNote->logEvent('cancelled', null, ['reason' => $reason]);

            $fresh = $receiptNote->fresh();
            event(new \App\Events\Voucher\VoucherCancelled([
                'reference_type' => get_class($fresh),
                'reference_id' => $fresh->id,
                'document_type' => 'receipt_note',
                'organization_id' => $fresh->organization_id ?? null,
                'branch_id' => $fresh->branch_id ?? null,
                'created_by' => $fresh->created_by ?? null,
                'reason' => $reason,
            ]));

            return $receiptNote;
        });
    }

    public function post(int $id): ?ReceiptNote
    {
        $receiptNote = ReceiptNote::find($id);
        if (!$receiptNote) {
            return null;
        }

        $receiptNote->update(['status' => 'posted']);

        $fresh = $receiptNote->fresh();

        event(new \App\Events\ReceiptNotePosted([
            'reference_type' => get_class($fresh),
            'reference_id' => $fresh->id,
            'document_type' => 'receipt_note',
            'organization_id' => $fresh->organization_id ?? null,
            'branch_id' => $fresh->branch_id ?? null,
            'created_by' => $fresh->received_by ?? ($fresh->created_by ?? null),
            'items_count' => method_exists($fresh, 'items') ? $fresh->items()->count() : null,
        ]));

        return $fresh;
    }
}
