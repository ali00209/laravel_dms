<?php

namespace App\Services\Voucher;

use App\Exceptions\NotFoundException;
use App\Exceptions\WorkflowException;
use App\Models\Vouchers\CreditNote;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CreditNoteService
{
    protected array $relations = [
        'organization',
        'invoice',
        'customer',
        'voucherType',
        'createdBy',
        'items',
    ];

    public function paginate(?array $filters, int $perPage): LengthAwarePaginator
    {
        return CreditNote::query()
            ->with($this->relations)
            ->when(isset($filters['organization_id']), fn($q) => $q->where('organization_id', $filters['organization_id']))
            ->when(isset($filters['branch_id']), fn($q) => $q->where('branch_id', $filters['branch_id']))
            ->when(isset($filters['warehouse_id']), fn($q) => $q->where('warehouse_id', $filters['warehouse_id']))
            ->when(isset($filters['outlet_id']), fn($q) => $q->where('outlet_id', $filters['outlet_id']))
            ->when(isset($filters['customer_id']), fn($q) => $q->where('customer_id', $filters['customer_id']))
            ->when(isset($filters['date_from']), fn($q) => $q->whereDate('date', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn($q) => $q->whereDate('date', '<=', $filters['date_to']))
            ->when(!empty($filters['search']), function ($q) use ($filters) {
                $term = '%' . trim($filters['search']) . '%';
                $q->where(function ($sub) use ($term) {
                    $sub->where('document_number', 'like', $term)
                        ->orWhereHas('customer', fn($cq) => $cq->where('name', 'like', $term));
                });
            })
            ->latest()
            ->paginate($perPage);
    }

    public function find(int $id, ?int $orgId = null, bool $withTrashed = false): CreditNote
    {
        $query = CreditNote::query();
        $query->with($this->relations);

        if ($withTrashed) {
            $query->withTrashed();
        }

        if ($orgId !== null) {
            $query->where('organization_id', $orgId);
        }

        return $query->find($id) ?? throw new NotFoundException('Credit Note not found.');
    }

    public function create(array $data): CreditNote
    {
        return DB::transaction(function () use ($data) {
            $creditNote = CreditNote::create($data);

            $creditNote->logEvent('created', null, $data);

            return $creditNote->load($this->relations);
        });
    }

    public function update(CreditNote $creditNote, array $data): CreditNote
    {
        if (!$creditNote->isEditable()) {
            throw new WorkflowException('Credit Note cannot be edited in its current status: ' . $creditNote->status);
        }

        return DB::transaction(function () use ($creditNote, $data) {
            $oldData = $creditNote->toArray();

            $creditNote->update($data);
            $creditNote->logEvent('updated', $oldData, $creditNote->toArray());

            return $creditNote->load($this->relations);
        });
    }

    public function delete(CreditNote $creditNote): void
    {
        if (!$creditNote->isDeletable()) {
            throw new WorkflowException('Credit Note cannot be deleted in its current status.');
        }

        $creditNote->delete();
        $creditNote->logEvent('deleted', null, null);
    }

    public function restore(CreditNote $creditNote): void
    {
        $creditNote->restore();
        $creditNote->logEvent('restored', null, null);
    }

    public function forceDelete(CreditNote $creditNote): void
    {
        $creditNote->forceDelete();
    }

    public function submitForApproval(CreditNote $creditNote, ?string $note = null): CreditNote
    {
        if (!$creditNote->canTransitionTo('submitted')) {
            throw new WorkflowException('Credit Note cannot be submitted from status: ' . $creditNote->status);
        }

        return DB::transaction(function () use ($creditNote, $note) {
            $creditNote->markSubmitted();

            if ($note) {
                $creditNote->addComment($note, true);
            }

            $creditNote->logEvent('submitted', null, ['status' => $creditNote->status]);

            return $creditNote;
        });
    }

    public function review(CreditNote $creditNote, ?string $note = null, ?int $userId = null): CreditNote
    {
        if (!$creditNote->isReviewable()) {
            throw new WorkflowException('Credit Note is not in a reviewable state.');
        }

        return DB::transaction(function () use ($creditNote, $note, $userId) {
            $creditNote->markReviewed($userId);
            $creditNote->changeStatus('reviewed', $note);

            if ($note) {
                $creditNote->addComment($note, true);
            }

            $creditNote->logEvent('reviewed', null, ['reviewed_by' => $userId ?? Auth::id()]);

            return $creditNote;
        });
    }

    public function approve(CreditNote $creditNote, ?string $note = null, ?int $userId = null): CreditNote
    {
        if (!$creditNote->isApprovable()) {
            throw new WorkflowException('Credit Note is not in an approvable state.');
        }

        return DB::transaction(function () use ($creditNote, $note, $userId) {
            $creditNote->markApproved($userId);
            $creditNote->changeStatus('approved', $note);

            if ($note) {
                $creditNote->addComment($note, true);
            }

            $creditNote->logEvent('approved', null, ['approved_by' => $userId ?? Auth::id()]);

            $fresh = $creditNote->fresh();
            event(new \App\Events\Voucher\VoucherApproved([
                'reference_type' => get_class($fresh),
                'reference_id' => $fresh->id,
                'document_type' => 'credit_note',
                'organization_id' => $fresh->organization_id ?? null,
                'branch_id' => $fresh->branch_id ?? null,
                'created_by' => $userId ?? $fresh->created_by ?? null,
                'items_count' => method_exists($fresh, 'items') ? $fresh->items()->count() : null,
            ]));

            return $creditNote;
        });
    }

    public function reject(CreditNote $creditNote, string $reason, ?string $note = null, ?int $userId = null): CreditNote
    {
        if (!$creditNote->canTransitionTo('rejected')) {
            throw new WorkflowException('Credit Note cannot be rejected from status: ' . $creditNote->status);
        }

        return DB::transaction(function () use ($creditNote, $reason, $note, $userId) {
            $creditNote->markRejected($reason, null, $userId);

            if ($note) {
                $creditNote->addComment($note, true);
            }

            $creditNote->logEvent('rejected', null, [
                'reason' => $reason,
                'rejected_by' => $userId ?? Auth::id(),
            ]);

            return $creditNote;
        });
    }

    public function cancel(CreditNote $creditNote, string $reason): CreditNote
    {
        if (!$creditNote->canTransitionTo('cancelled')) {
            throw new WorkflowException('Credit Note cannot be cancelled from status: ' . $creditNote->status);
        }

        return DB::transaction(function () use ($creditNote, $reason) {
            $creditNote->changeStatus('cancelled', $reason);

            $creditNote->logEvent('cancelled', null, ['reason' => $reason]);

            $fresh = $creditNote->fresh();
            event(new \App\Events\Voucher\VoucherCancelled([
                'reference_type' => get_class($fresh),
                'reference_id' => $fresh->id,
                'document_type' => 'credit_note',
                'organization_id' => $fresh->organization_id ?? null,
                'branch_id' => $fresh->branch_id ?? null,
                'created_by' => $fresh->created_by ?? null,
                'reason' => $reason,
            ]));

            return $creditNote;
        });
    }

    public function post(int $id): ?CreditNote
    {
        $creditNote = CreditNote::find($id);
        if (!$creditNote) {
            return null;
        }

        $creditNote->update(['status' => 'posted']);

        $fresh = $creditNote->fresh();

        event(new \App\Events\CreditNotePosted([
            'reference_type' => get_class($fresh),
            'reference_id' => $fresh->id,
            'document_type' => 'credit_note',
            'organization_id' => $fresh->organization_id ?? null,
            'branch_id' => $fresh->branch_id ?? null,
            'created_by' => $fresh->created_by ?? null,
            'items_count' => method_exists($fresh, 'items') ? $fresh->items()->count() : null,
        ]));

        return $fresh;
    }
}
