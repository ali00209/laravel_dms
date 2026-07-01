<?php

namespace App\Services\Voucher;

use App\Exceptions\NotFoundException;
use App\Exceptions\WorkflowException;
use App\Models\Vouchers\PurchaseOrder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
    protected array $relations = [
        'organization',
        'branch',
        'supplier',
        'voucherType',
        'createdBy',
        'items',
    ];

    public function paginate(?array $filters, int $perPage): LengthAwarePaginator
    {
        return PurchaseOrder::query()
            ->with($this->relations)
            ->when(isset($filters['organization_id']), fn($q) => $q->where('organization_id', $filters['organization_id']))
            ->when(isset($filters['branch_id']), fn($q) => $q->where('branch_id', $filters['branch_id']))
            ->when(isset($filters['warehouse_id']), fn($q) => $q->where('warehouse_id', $filters['warehouse_id']))
            ->when(isset($filters['outlet_id']), fn($q) => $q->where('outlet_id', $filters['outlet_id']))
            ->when(isset($filters['supplier_id']), fn($q) => $q->where('supplier_id', $filters['supplier_id']))
            ->when(isset($filters['status']), fn($q) => $q->where('status', $filters['status']))
            ->when(isset($filters['date_from']), fn($q) => $q->whereDate('order_date', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn($q) => $q->whereDate('order_date', '<=', $filters['date_to']))
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

    public function find(int $id, ?int $orgId = null, bool $withTrashed = false): PurchaseOrder
    {
        $query = PurchaseOrder::query();
        $query->with($this->relations);

        if ($withTrashed) {
            $query->withTrashed();
        }

        if ($orgId !== null) {
            $query->where('organization_id', $orgId);
        }

        return $query->find($id) ?? throw new NotFoundException('Purchase Order not found.');
    }

    public function create(array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($data) {
            $purchaseOrder = PurchaseOrder::create($data);

            $purchaseOrder->logEvent('created', null, $data);

            return $purchaseOrder->load($this->relations);
        });
    }

    public function update(PurchaseOrder $purchaseOrder, array $data): PurchaseOrder
    {
        if (!$purchaseOrder->isEditable()) {
            throw new WorkflowException('Purchase Order cannot be edited in its current status: ' . $purchaseOrder->status);
        }

        return DB::transaction(function () use ($purchaseOrder, $data) {
            $oldData = $purchaseOrder->toArray();

            $purchaseOrder->update($data);
            $purchaseOrder->logEvent('updated', $oldData, $purchaseOrder->toArray());

            return $purchaseOrder->load($this->relations);
        });
    }

    public function delete(PurchaseOrder $purchaseOrder): void
    {
        if (!$purchaseOrder->isDeletable()) {
            throw new WorkflowException('Purchase Order cannot be deleted in its current status.');
        }

        $purchaseOrder->delete();
        $purchaseOrder->logEvent('deleted', null, null);
    }

    public function restore(PurchaseOrder $purchaseOrder): void
    {
        $purchaseOrder->restore();
        $purchaseOrder->logEvent('restored', null, null);
    }

    public function forceDelete(PurchaseOrder $purchaseOrder): void
    {
        $purchaseOrder->forceDelete();
    }

    public function submitForApproval(PurchaseOrder $purchaseOrder, ?string $note = null): PurchaseOrder
    {
        if (!$purchaseOrder->canTransitionTo('submitted')) {
            throw new WorkflowException('Purchase Order cannot be submitted from status: ' . $purchaseOrder->status);
        }

        return DB::transaction(function () use ($purchaseOrder, $note) {
            $purchaseOrder->markSubmitted();

            if ($note) {
                $purchaseOrder->addComment($note, true);
            }

            $purchaseOrder->logEvent('submitted', null, ['status' => $purchaseOrder->status]);

            return $purchaseOrder;
        });
    }

    public function review(PurchaseOrder $purchaseOrder, ?string $note = null, ?int $userId = null): PurchaseOrder
    {
        if (!$purchaseOrder->isReviewable()) {
            throw new WorkflowException('Purchase Order is not in a reviewable state.');
        }

        return DB::transaction(function () use ($purchaseOrder, $note, $userId) {
            $purchaseOrder->markReviewed($userId);
            $purchaseOrder->changeStatus('reviewed', $note);

            if ($note) {
                $purchaseOrder->addComment($note, true);
            }

            $purchaseOrder->logEvent('reviewed', null, ['reviewed_by' => $userId ?? Auth::id()]);

            return $purchaseOrder;
        });
    }

    public function approve(PurchaseOrder $purchaseOrder, ?string $note = null, ?int $userId = null): PurchaseOrder
    {
        if (!$purchaseOrder->isApprovable()) {
            throw new WorkflowException('Purchase Order is not in an approvable state.');
        }

        return DB::transaction(function () use ($purchaseOrder, $note, $userId) {
            $purchaseOrder->markApproved($userId);
            $purchaseOrder->changeStatus('approved', $note);

            if ($note) {
                $purchaseOrder->addComment($note, true);
            }

            $purchaseOrder->logEvent('approved', null, ['approved_by' => $userId ?? Auth::id()]);

            $fresh = $purchaseOrder->fresh();
            event(new \App\Events\Voucher\VoucherApproved([
                'reference_type' => get_class($fresh),
                'reference_id' => $fresh->id,
                'document_type' => 'purchase_order',
                'organization_id' => $fresh->organization_id ?? null,
                'branch_id' => $fresh->branch_id ?? null,
                'created_by' => $userId ?? $fresh->created_by ?? null,
                'items_count' => method_exists($fresh, 'items') ? $fresh->items()->count() : null,
            ]));

            return $purchaseOrder;
        });
    }

    public function reject(PurchaseOrder $purchaseOrder, string $reason, ?string $note = null, ?int $userId = null): PurchaseOrder
    {
        if (!$purchaseOrder->canTransitionTo('rejected')) {
            throw new WorkflowException('Purchase Order cannot be rejected from status: ' . $purchaseOrder->status);
        }

        return DB::transaction(function () use ($purchaseOrder, $reason, $note, $userId) {
            $purchaseOrder->markRejected($reason, null, $userId);

            if ($note) {
                $purchaseOrder->addComment($note, true);
            }

            $purchaseOrder->logEvent('rejected', null, [
                'reason' => $reason,
                'rejected_by' => $userId ?? Auth::id(),
            ]);

            return $purchaseOrder;
        });
    }

    public function cancel(PurchaseOrder $purchaseOrder, string $reason): PurchaseOrder
    {
        if (!$purchaseOrder->canTransitionTo('cancelled')) {
            throw new WorkflowException('Purchase Order cannot be cancelled from status: ' . $purchaseOrder->status);
        }

        return DB::transaction(function () use ($purchaseOrder, $reason) {
            $purchaseOrder->changeStatus('cancelled', $reason);

            $purchaseOrder->logEvent('cancelled', null, ['reason' => $reason]);

            $fresh = $purchaseOrder->fresh();
            event(new \App\Events\Voucher\VoucherCancelled([
                'reference_type' => get_class($fresh),
                'reference_id' => $fresh->id,
                'document_type' => 'purchase_order',
                'organization_id' => $fresh->organization_id ?? null,
                'branch_id' => $fresh->branch_id ?? null,
                'created_by' => $fresh->created_by ?? null,
                'reason' => $reason,
            ]));

            return $purchaseOrder;
        });
    }
}
