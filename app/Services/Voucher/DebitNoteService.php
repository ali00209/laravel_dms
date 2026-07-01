<?php

namespace App\Services\Voucher;

use App\Exceptions\NotFoundException;
use App\Exceptions\WorkflowException;
use App\Models\Vouchers\DebitNote;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DebitNoteService
{
    protected array $relations = [
        'organization',
        'purchaseBill',
        'supplier',
        'voucherType',
        'createdBy',
        'items',
    ];

    public function paginate(?array $filters, int $perPage): LengthAwarePaginator
    {
        return DebitNote::query()
            ->with($this->relations)
            ->when(isset($filters['organization_id']), fn($q) => $q->where('organization_id', $filters['organization_id']))
            ->when(isset($filters['branch_id']), fn($q) => $q->where('branch_id', $filters['branch_id']))
            ->when(isset($filters['warehouse_id']), fn($q) => $q->where('warehouse_id', $filters['warehouse_id']))
            ->when(isset($filters['outlet_id']), fn($q) => $q->where('outlet_id', $filters['outlet_id']))
            ->when(isset($filters['supplier_id']), fn($q) => $q->where('supplier_id', $filters['supplier_id']))
            ->when(isset($filters['date_from']), fn($q) => $q->whereDate('date', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn($q) => $q->whereDate('date', '<=', $filters['date_to']))
            ->when(!empty($filters['search']), function ($q) use ($filters) {
                $term = '%' . trim($filters['search']) . '%';
                $q->where(function ($sub) use ($term) {
                    $sub->where('document_number', 'like', $term)
                        ->orWhereHas('supplier', fn($cq) => $cq->where('name', 'like', $term));
                });
            })
            ->latest()
            ->paginate($perPage);
    }

    public function find(int $id, ?int $orgId = null, bool $withTrashed = false): DebitNote
    {
        $query = DebitNote::query();
        $query->with($this->relations);

        if ($withTrashed) {
            $query->withTrashed();
        }

        if ($orgId !== null) {
            $query->where('organization_id', $orgId);
        }

        return $query->find($id) ?? throw new NotFoundException('Debit Note not found.');
    }

    public function create(array $data): DebitNote
    {
        return DB::transaction(function () use ($data) {
            $debitNote = DebitNote::create($data);

            $debitNote->logEvent('created', null, $data);

            return $debitNote->load($this->relations);
        });
    }

    public function update(DebitNote $debitNote, array $data): DebitNote
    {
        if (!$debitNote->isEditable()) {
            throw new WorkflowException('Debit Note cannot be edited in its current status: ' . $debitNote->status);
        }

        return DB::transaction(function () use ($debitNote, $data) {
            $oldData = $debitNote->toArray();

            $debitNote->update($data);
            $debitNote->logEvent('updated', $oldData, $debitNote->toArray());

            return $debitNote->load($this->relations);
        });
    }

    public function delete(DebitNote $debitNote): void
    {
        if (!$debitNote->isDeletable()) {
            throw new WorkflowException('Debit Note cannot be deleted in its current status.');
        }

        $debitNote->delete();
        $debitNote->logEvent('deleted', null, null);
    }

    public function restore(DebitNote $debitNote): void
    {
        $debitNote->restore();
        $debitNote->logEvent('restored', null, null);
    }

    public function forceDelete(DebitNote $debitNote): void
    {
        $debitNote->forceDelete();
    }

    public function submitForApproval(DebitNote $debitNote, ?string $note = null): DebitNote
    {
        if (!$debitNote->canTransitionTo('submitted')) {
            throw new WorkflowException('Debit Note cannot be submitted from status: ' . $debitNote->status);
        }

        return DB::transaction(function () use ($debitNote, $note) {
            $debitNote->markSubmitted();

            if ($note) {
                $debitNote->addComment($note, true);
            }

            $debitNote->logEvent('submitted', null, ['status' => $debitNote->status]);

            return $debitNote;
        });
    }

    public function review(DebitNote $debitNote, ?string $note = null, ?int $userId = null): DebitNote
    {
        if (!$debitNote->isReviewable()) {
            throw new WorkflowException('Debit Note is not in a reviewable state.');
        }

        return DB::transaction(function () use ($debitNote, $note, $userId) {
            $debitNote->markReviewed($userId);
            $debitNote->changeStatus('reviewed', $note);

            if ($note) {
                $debitNote->addComment($note, true);
            }

            $debitNote->logEvent('reviewed', null, ['reviewed_by' => $userId ?? Auth::id()]);

            return $debitNote;
        });
    }

    public function approve(DebitNote $debitNote, ?string $note = null, ?int $userId = null): DebitNote
    {
        if (!$debitNote->isApprovable()) {
            throw new WorkflowException('Debit Note is not in an approvable state.');
        }

        return DB::transaction(function () use ($debitNote, $note, $userId) {
            $debitNote->markApproved($userId);
            $debitNote->changeStatus('approved', $note);

            if ($note) {
                $debitNote->addComment($note, true);
            }

            $debitNote->logEvent('approved', null, ['approved_by' => $userId ?? Auth::id()]);

            $fresh = $debitNote->fresh();
            event(new \App\Events\Voucher\VoucherApproved([
                'reference_type' => get_class($fresh),
                'reference_id' => $fresh->id,
                'document_type' => 'debit_note',
                'organization_id' => $fresh->organization_id ?? null,
                'branch_id' => $fresh->branch_id ?? null,
                'created_by' => $userId ?? $fresh->created_by ?? null,
                'items_count' => method_exists($fresh, 'items') ? $fresh->items()->count() : null,
            ]));

            return $debitNote;
        });
    }

    public function reject(DebitNote $debitNote, string $reason, ?string $note = null, ?int $userId = null): DebitNote
    {
        if (!$debitNote->canTransitionTo('rejected')) {
            throw new WorkflowException('Debit Note cannot be rejected from status: ' . $debitNote->status);
        }

        return DB::transaction(function () use ($debitNote, $reason, $note, $userId) {
            $debitNote->markRejected($reason, null, $userId);

            if ($note) {
                $debitNote->addComment($note, true);
            }

            $debitNote->logEvent('rejected', null, [
                'reason' => $reason,
                'rejected_by' => $userId ?? Auth::id(),
            ]);

            return $debitNote;
        });
    }

    public function cancel(DebitNote $debitNote, string $reason): DebitNote
    {
        if (!$debitNote->canTransitionTo('cancelled')) {
            throw new WorkflowException('Debit Note cannot be cancelled from status: ' . $debitNote->status);
        }

        return DB::transaction(function () use ($debitNote, $reason) {
            $debitNote->changeStatus('cancelled', $reason);

            $debitNote->logEvent('cancelled', null, ['reason' => $reason]);

            $fresh = $debitNote->fresh();
            event(new \App\Events\Voucher\VoucherCancelled([
                'reference_type' => get_class($fresh),
                'reference_id' => $fresh->id,
                'document_type' => 'debit_note',
                'organization_id' => $fresh->organization_id ?? null,
                'branch_id' => $fresh->branch_id ?? null,
                'created_by' => $fresh->created_by ?? null,
                'reason' => $reason,
            ]));

            return $debitNote;
        });
    }

    public function post(int $id): ?DebitNote
    {
        $debitNote = DebitNote::find($id);
        if (!$debitNote) {
            return null;
        }

        $debitNote->update(['status' => 'posted']);

        $fresh = $debitNote->fresh();

        event(new \App\Events\DebitNotePosted([
            'reference_type' => get_class($fresh),
            'reference_id' => $fresh->id,
            'document_type' => 'debit_note',
            'organization_id' => $fresh->organization_id ?? null,
            'branch_id' => $fresh->branch_id ?? null,
            'created_by' => $fresh->created_by ?? null,
            'items_count' => method_exists($fresh, 'items') ? $fresh->items()->count() : null,
        ]));

        return $fresh;
    }
}
